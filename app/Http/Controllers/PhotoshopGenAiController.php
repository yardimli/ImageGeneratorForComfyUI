<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptSetting;
use App\Services\FalModelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            $models = $falModels->models('image-to-image');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Image-to-image models are currently unavailable.',
            ], 503);
        }

        $allowedModels = array_column($models, 'endpoint_id');
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'model' => ['required', 'string', Rule::in($allowedModels)],
            'width' => ['required', 'integer', 'min:8', 'max:8192', 'multiple_of:8'],
            'height' => ['required', 'integer', 'min:8', 'max:8192', 'multiple_of:8'],
            'resolution' => ['required', 'string', 'max:40'],
            'parameters' => ['nullable', 'json', 'max:20000'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:20480'],
        ]);

        $model = collect($models)->firstWhere('endpoint_id', $validated['model']);
        $usesSingleImage = array_key_exists('image_url', $model['parameters']);
        $imageDefinition = $model['parameters']['image_urls'] ?? $model['parameters']['image_url'] ?? [];
        $maxImages = max(1, (int) ($imageDefinition['max_items'] ?? ($usesSingleImage ? 1 : 10)));
        if (count($request->file('images', [])) > $maxImages) {
            throw ValidationException::withMessages([
                'images' => "The selected model accepts at most {$maxImages} input image".($maxImages === 1 ? '.' : 's.'),
            ]);
        }
        $parameters = $this->normalizeModelParameters(
            json_decode($validated['parameters'] ?? '{}', true, 512, JSON_THROW_ON_ERROR),
            $model['parameters'],
        );

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
            $parameters,
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
        array $modelParameters = [],
    ): Prompt {
        return DB::transaction(function () use ($text, $model, $width, $height, $resolution, $images, $marker, $modelParameters) {
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
                'model_parameters' => $modelParameters,
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
                'model_parameters' => $modelParameters,
            ]);
        });
    }

    /** @param array<string, mixed> $values
     *  @param array<string, array<string, mixed>> $definitions
     *  @return array<string, mixed>
     */
    private function normalizeModelParameters(array $values, array $definitions): array
    {
        $managed = ['prompt', 'image_url', 'image_urls', 'sync_mode'];
        $allowed = array_diff(array_keys($definitions), $managed);
        $unknown = array_diff(array_keys($values), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'parameters' => 'Unsupported model parameter: '.reset($unknown).'.',
            ]);
        }

        $normalized = [];
        foreach ($allowed as $name) {
            $definition = $definitions[$name];
            $hasValue = array_key_exists($name, $values) && $values[$name] !== '' && $values[$name] !== null;
            if (! $hasValue) {
                if (array_key_exists('default', $definition)) {
                    $normalized[$name] = $definition['default'];
                }
                continue;
            }

            $value = $values[$name];
            $type = (string) ($definition['type'] ?? 'string');
            if (str_contains($type, 'integer')) {
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages(["parameters.{$name}" => 'Must be an integer.']);
                }
                $value = (int) $value;
            } elseif (str_contains($type, 'float')) {
                if (! is_numeric($value)) {
                    throw ValidationException::withMessages(["parameters.{$name}" => 'Must be a number.']);
                }
                $value = (float) $value;
            } elseif (str_contains($type, 'boolean')) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    throw ValidationException::withMessages(["parameters.{$name}" => 'Must be true or false.']);
                }
            } elseif (str_contains($type, 'array')) {
                if (! is_array($value)) {
                    throw ValidationException::withMessages(["parameters.{$name}" => 'Must be an array.']);
                }
            } elseif (! is_string($value)) {
                throw ValidationException::withMessages(["parameters.{$name}" => 'Must be a string.']);
            }

            if (isset($definition['allowed_values']) && ! in_array($value, $definition['allowed_values'], true)) {
                throw ValidationException::withMessages(["parameters.{$name}" => 'Choose one of the available values.']);
            }
            if (isset($definition['min']) && $value < $definition['min']) {
                throw ValidationException::withMessages(["parameters.{$name}" => "Must be at least {$definition['min']}."]);
            }
            if (isset($definition['max']) && $value > $definition['max']) {
                throw ValidationException::withMessages(["parameters.{$name}" => "Must not exceed {$definition['max']}."]);
            }
            if (is_array($value) && isset($definition['max_items']) && count($value) > $definition['max_items']) {
                throw ValidationException::withMessages(["parameters.{$name}" => "Must contain no more than {$definition['max_items']} items."]);
            }

            $normalized[$name] = $value;
        }

        return $normalized;
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
