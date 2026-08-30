<?php

namespace App\Http\Controllers;

use App\Models\Layer;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class LayerController extends Controller
{
    private const MODEL = 'bytedance/seedream/v5/pro/layerize';

    public function index()
    {
        $layers = Layer::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('layers.index', compact('layers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'string', 'max:200000'],
        ]);

        $layer = DB::transaction(function () use ($validated) {
            $layer = Layer::create([
                'user_id' => auth()->id(),
                'model' => self::MODEL,
                'input_image' => $validated['image'],
                'status' => 0,
            ]);

            $prompt = Prompt::create([
                'user_id' => auth()->id(),
                'generation_type' => 'layerize',
                'original_prompt' => '',
                'generated_prompt' => '',
                'model' => self::MODEL,
                'width' => 1024,
                'height' => 1024,
                'upload_to_s3' => false,
                'input_images' => [$validated['image']],
            ]);

            $layer->update(['prompt_id' => $prompt->id]);

            return $layer;
        });

        return response()->json([
            'success' => true,
            'message' => 'Layerize job queued.',
            'id' => $layer->id,
            'url' => route('layers.show', $layer),
        ]);
    }

    public function show(Layer $layer)
    {
        $this->authorizeLayer($layer);

        $exportLayers = collect($layer->layers ?? [])->map(function (array $item, int $index) use ($layer) {
            return [
                'index' => $index,
                'name' => data_get($item, 'name', $index === 0 ? 'Base image' : "Layer {$index}"),
                'description' => data_get($item, 'description'),
                'zIndex' => (int) data_get($item, 'z_index', $index),
                'bounds' => data_get($item, 'bounding_box.absolute'),
                'width' => data_get($item, 'image.width'),
                'height' => data_get($item, 'image.height'),
                'url' => route('layers.download', [$layer, $index]),
            ];
        })->values();

        return view('layers.show', compact('layer', 'exportLayers'));
    }

    public function status(Layer $layer)
    {
        $this->authorizeLayer($layer);
        $layer->refresh();

        return response()->json([
            'success' => true,
            'status' => match ((int) $layer->status) {
                2 => 'ready',
                4 => 'failed',
                default => 'pending',
            },
            'url' => route('layers.show', $layer),
            'error' => $layer->error,
        ]);
    }

    public function download(Layer $layer, int $index)
    {
        $this->authorizeLayer($layer);
        abort_unless((int) $layer->status === 2, 404);

        $item = $layer->layers[$index] ?? null;
        $url = data_get($item, 'image.url');
        abort_unless(is_string($url) && $url !== '', 404);

        $response = Http::timeout(60)->get($url);
        abort_unless($response->successful(), 502);

        $filename = Str::slug(data_get($item, 'name', $index === 0 ? 'base-image' : "layer-{$index}"));
        $extension = match ($response->header('Content-Type')) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'image/png'),
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$extension.'"',
        ]);
    }

    public function downloadAll(Layer $layer)
    {
        $this->authorizeLayer($layer);
        abort_unless((int) $layer->status === 2, 404);
        abort_unless(class_exists(ZipArchive::class), 500, 'The PHP ZIP extension is not installed.');

        $temporaryPath = tempnam(sys_get_temp_dir(), "layerize-{$layer->id}-");
        abort_unless(is_string($temporaryPath), 500, 'Could not create the ZIP archive.');

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            abort(500, 'Could not open the ZIP archive.');
        }

        try {
            $metadataLayers = [];

            foreach ($layer->layers ?? [] as $index => $item) {
                $url = data_get($item, 'image.url');
                abort_unless(is_string($url) && $url !== '', 404, "Layer {$index} has no image URL.");

                $response = Http::timeout(60)->get($url);
                abort_unless($response->successful(), 502, "Layer {$index} could not be downloaded.");

                $name = data_get($item, 'name', $index === 0 ? 'base-image' : "layer-{$index}");
                $extension = $this->imageExtension($response->header('Content-Type'));
                $filename = sprintf(
                    '%02d-z%02d-%s.%s',
                    $index + 1,
                    (int) data_get($item, 'z_index', $index),
                    Str::slug($name) ?: "layer-{$index}",
                    $extension
                );

                $zip->addFromString($filename, $response->body());
                $metadataLayers[] = [
                    'index' => $index,
                    'z_index' => (int) data_get($item, 'z_index', $index),
                    'name' => $name,
                    'description' => data_get($item, 'description'),
                    'bounding_box' => data_get($item, 'bounding_box'),
                    'image' => array_merge(data_get($item, 'image', []), ['file' => $filename]),
                ];
            }

            $metadata = [
                'layerize_job_id' => $layer->id,
                'model' => $layer->model,
                'created_at' => $layer->created_at?->toIso8601String(),
                'input_image' => $layer->input_image,
                'layers' => $metadataLayers,
            ];
            $zip->addFromString(
                'metadata.json',
                json_encode(
                    $metadata,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
            );
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($temporaryPath);

            throw $exception;
        }
        $zip->close();

        return response()
            ->download($temporaryPath, "layerize-{$layer->id}.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function imageExtension(?string $contentType): string
    {
        return match (Str::before((string) $contentType, ';')) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'png',
        };
    }

    private function authorizeLayer(Layer $layer): void
    {
        abort_unless((int) $layer->user_id === (int) auth()->id(), 403);
    }
}
