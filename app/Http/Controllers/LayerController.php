<?php

namespace App\Http\Controllers;

use App\Models\Layer;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LayerController extends Controller
{
    private const MODEL = 'bytedance/seedream/v5/pro/layerize';

    public function history()
    {
        $layers = Layer::query()->where('user_id', auth()->id())->latest()->limit(50)->get();

        return response()->json([
            'success' => true,
            'jobs' => $layers->map(fn (Layer $layer) => $this->layerPayload($layer))->values(),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->hasFile('image')) {
            $request->validate(['image' => ['required', 'image', 'max:20480']]);
            $path = $request->file('image')->store('layerize-inputs/'.auth()->id(), 'public');
            $image = Storage::disk('public')->url($path);
        } else {
            $validated = $request->validate(['image' => ['required', 'string', 'max:200000']]);
            $image = $validated['image'];
        }

        $layer = DB::transaction(function () use ($image) {
            $layer = Layer::create([
                'user_id' => auth()->id(),
                'model' => self::MODEL,
                'input_image' => $image,
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
                'input_images' => [$image],
            ]);

            $layer->update(['prompt_id' => $prompt->id]);

            return $layer;
        });

        return response()->json([
            'success' => true,
            'message' => 'Layerize job queued.',
            'id' => $layer->id,
            'statusUrl' => route('layers.status', $layer),
        ]);
    }

    public function status(Layer $layer)
    {
        $this->authorizeLayer($layer);
        $layer->refresh();

        return response()->json(['success' => true] + $this->layerPayload($layer));

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

    private function layerPayload(Layer $layer): array
    {
        $status = match ((int) $layer->status) { 2 => 'ready', 4 => 'failed', default => 'pending' };
        $results = collect($layer->layers ?? [])->map(fn (array $item, int $index) => [
            'index' => $index,
            'name' => data_get($item, 'name', $index === 0 ? 'Base image' : "Layer {$index}"),
            'description' => data_get($item, 'description'),
            'zIndex' => (int) data_get($item, 'z_index', $index),
            'bounds' => data_get($item, 'bounding_box.absolute'),
            'width' => data_get($item, 'image.width'),
            'height' => data_get($item, 'image.height'),
            'url' => route('layers.download', [$layer, $index]),
        ])->values();

        return [
            'id' => $layer->id,
            'status' => $status,
            'createdAt' => $layer->created_at?->toIso8601String(),
            'error' => $layer->error,
            'previewUrl' => $status === 'ready' && $results->isNotEmpty() ? $results->first()['url'] : $layer->input_image,
            'layers' => $status === 'ready' ? $results : [],
        ];
    }
    private function authorizeLayer(Layer $layer): void
    {
        abort_unless((int) $layer->user_id === (int) auth()->id(), 403);
    }
}
