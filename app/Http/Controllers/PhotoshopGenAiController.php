<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptSetting;
use App\Services\FalModelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class PhotoshopGenAiController extends Controller
{
    private const MODEL = 'bytedance/seedream/v5/pro/edit';
    private const COLORS = ['red', 'green', 'yellow', 'blue', 'purple'];
    private const GEN_AI_MARKER = 'photoshop-gen-ai';
    private const IMAGE_TO_IMAGE_MARKER = 'photoshop-image-to-image';

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'image' => ['required', 'image', 'max:20480'],
            'width' => ['required', 'integer', 'min:1', 'max:8192'],
            'height' => ['required', 'integer', 'min:1', 'max:8192'],
            'colors' => ['required', 'array', 'min:1', 'max:5'],
            'colors.*' => ['required', Rule::in(self::COLORS)],
        ]);

        $path = $request->file('image')->store('photoshop-gen-ai-inputs/'.auth()->id(), 'public');
        $image = Storage::disk('public')->url($path);
        $colors = collect($validated['colors'])->map(fn (string $color) => ucfirst($color))->join(', ');
        $combinedPrompt = trim($validated['prompt'])."\nColored frames mark the only edit regions: {$colors}. Apply the instruction inside every marked frame and preserve everything outside them.";

        $prompt = $this->createPrompt(
            $combinedPrompt,
            self::MODEL,
            (int) $validated['width'],
            (int) $validated['height'],
            'auto_2K',
            [$image],
            self::GEN_AI_MARKER,
        );

        return response()->json([
            'success' => true,
            'id' => $prompt->id,
            'statusUrl' => route('photoshop.gen-ai.status', $prompt),
        ]);
    }

    public function storeImageToImage(Request $request, FalModelService $falModels)
    {
        try {
            $allowedModels = array_column($falModels->models('image-to-image'), 'endpoint_id');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Image-to-image models are currently unavailable.',
            ], 503);
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'model' => ['required', 'string', Rule::in($allowedModels)],
            'width' => ['required', 'integer', 'min:8', 'max:8192', 'multiple_of:8'],
            'height' => ['required', 'integer', 'min:8', 'max:8192', 'multiple_of:8'],
            'resolution' => ['required', 'string', 'max:40'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:20480'],
        ]);

        $images = collect($request->file('images'))
            ->map(function ($image) {
                $path = $image->store('photoshop-image-to-image-inputs/'.auth()->id(), 'public');

                return Storage::disk('public')->url($path);
            })
            ->values()
            ->all();

        $prompt = $this->createPrompt(
            trim($validated['prompt']),
            $validated['model'],
            (int) $validated['width'],
            (int) $validated['height'],
            $validated['resolution'],
            $images,
            self::IMAGE_TO_IMAGE_MARKER,
        );

        return response()->json([
            'success' => true,
            'id' => $prompt->id,
            'statusUrl' => route('photoshop.image-to-image.status', $prompt),
        ]);
    }

    public function status(Prompt $prompt)
    {
        $this->authorizePrompt($prompt, self::GEN_AI_MARKER);

        return response()->json(['success' => true] + $this->payload($prompt->fresh(), self::GEN_AI_MARKER));
    }

    public function imageToImageStatus(Prompt $prompt)
    {
        $this->authorizePrompt($prompt, self::IMAGE_TO_IMAGE_MARKER);

        return response()->json(['success' => true] + $this->payload($prompt->fresh(), self::IMAGE_TO_IMAGE_MARKER));
    }

    public function history()
    {
        return $this->historyResponse(self::GEN_AI_MARKER);
    }

    public function imageToImageHistory()
    {
        return $this->historyResponse(self::IMAGE_TO_IMAGE_MARKER);
    }

    public function download(Prompt $prompt)
    {
        return $this->downloadPrompt($prompt, self::GEN_AI_MARKER);
    }

    public function imageToImageDownload(Prompt $prompt)
    {
        return $this->downloadPrompt($prompt, self::IMAGE_TO_IMAGE_MARKER);
    }

    private function createPrompt(
        string $text,
        string $model,
        int $width,
        int $height,
        string $resolution,
        array $images,
        string $marker,
    ): Prompt {
        return DB::transaction(function () use ($text, $model, $width, $height, $resolution, $images, $marker) {
            $setting = PromptSetting::create([
                'user_id' => auth()->id(),
                'generation_type' => 'prompt',
                'template_path' => '',
                'prompt_template' => '',
                'original_prompt' => $text,
                'precision' => 'Normal',
                'count' => 1,
                'render_each_prompt_times' => 1,
                'width' => $width,
                'height' => $height,
                'model' => $model,
                'upload_to_s3' => false,
                'aspect_ratio' => $resolution,
                'prepend_text' => '',
                'append_text' => '',
                'generate_original_prompt' => false,
                'append_to_prompt' => false,
                'input_images_1' => '',
                'input_images_2' => '',
                'input_images' => $images,
            ]);

            return Prompt::create([
                'user_id' => auth()->id(),
                'prompt_setting_id' => $setting->id,
                'generation_type' => 'prompt',
                'original_prompt' => $text,
                'generated_prompt' => $text,
                'model' => $model,
                'width' => $width,
                'height' => $height,
                'upload_to_s3' => false,
                'input_images' => $images,
                'notes' => $marker,
            ]);
        });
    }

    private function historyResponse(string $marker)
    {
        $jobs = Prompt::query()
            ->where('user_id', auth()->id())
            ->where('notes', $marker)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'jobs' => $jobs->map(fn (Prompt $prompt) => $this->payload($prompt, $marker))->values(),
        ]);
    }

    private function downloadPrompt(Prompt $prompt, string $marker)
    {
        $this->authorizePrompt($prompt, $marker);
        abort_unless((int) $prompt->render_status === 2, 404);
        $filename = $prompt->getRawOriginal('filename');
        abort_unless(is_string($filename) && is_file($filename), 404);

        return response()->file($filename);
    }

    private function payload(Prompt $prompt, string $marker): array
    {
        $status = match ((int) $prompt->render_status) {
            2 => 'ready',
            4 => 'failed',
            default => 'pending',
        };
        $downloadRoute = $marker === self::IMAGE_TO_IMAGE_MARKER
            ? 'photoshop.image-to-image.download'
            : 'photoshop.gen-ai.download';

        return [
            'id' => $prompt->id,
            'status' => $status,
            'prompt' => $prompt->original_prompt,
            'model' => $prompt->model,
            'width' => (int) $prompt->width,
            'height' => (int) $prompt->height,
            'createdAt' => $prompt->created_at?->toIso8601String(),
            'previewUrl' => $status === 'ready' ? route($downloadRoute, $prompt) : null,
        ];
    }

    private function authorizePrompt(Prompt $prompt, string $marker): void
    {
        abort_unless(
            (int) $prompt->user_id === (int) auth()->id() && $prompt->notes === $marker,
            403,
        );
    }
}