<?php

namespace App\Http\Controllers;

use App\Models\Layer;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

        return view('layers.show', compact('layer'));
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

    private function authorizeLayer(Layer $layer): void
    {
        abort_unless((int) $layer->user_id === (int) auth()->id(), 403);
    }
}
