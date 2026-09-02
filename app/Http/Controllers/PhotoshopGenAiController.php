<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PhotoshopGenAiController extends Controller
{
    private const MODEL = 'bytedance/seedream/v5/pro/edit';
    private const COLORS = ['red', 'green', 'yellow', 'blue', 'purple'];

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

        $prompt = DB::transaction(function () use ($validated, $combinedPrompt, $image) {
            $setting = PromptSetting::create([
                'user_id' => auth()->id(), 'generation_type' => 'prompt', 'template_path' => '', 'prompt_template' => '',
                'original_prompt' => $combinedPrompt, 'precision' => 'Normal', 'count' => 1, 'render_each_prompt_times' => 1,
                'width' => $validated['width'], 'height' => $validated['height'], 'model' => self::MODEL,
                'upload_to_s3' => false, 'aspect_ratio' => 'auto_2K', 'prepend_text' => '', 'append_text' => '',
                'generate_original_prompt' => false, 'append_to_prompt' => false, 'input_images_1' => '', 'input_images_2' => '',
                'input_images' => [$image],
            ]);

            return Prompt::create([
                'user_id' => auth()->id(), 'prompt_setting_id' => $setting->id, 'generation_type' => 'prompt',
                'original_prompt' => $combinedPrompt, 'generated_prompt' => $combinedPrompt, 'model' => self::MODEL,
                'width' => $validated['width'], 'height' => $validated['height'], 'upload_to_s3' => false,
                'input_images' => [$image], 'notes' => 'photoshop-gen-ai',
            ]);
        });

        return response()->json(['success' => true, 'id' => $prompt->id, 'statusUrl' => route('photoshop.gen-ai.status', $prompt)]);
    }

    public function status(Prompt $prompt)
    {
        $this->authorizePrompt($prompt);

        return response()->json(['success' => true] + $this->payload($prompt->fresh()));
    }

    public function history()
    {
        $jobs = Prompt::query()->where('user_id', auth()->id())->where('notes', 'photoshop-gen-ai')->latest()->limit(50)->get();

        return response()->json(['success' => true, 'jobs' => $jobs->map(fn (Prompt $prompt) => $this->payload($prompt))->values()]);
    }

    public function download(Prompt $prompt)
    {
        $this->authorizePrompt($prompt);
        abort_unless((int) $prompt->render_status === 2, 404);
        $filename = $prompt->getRawOriginal('filename');
        abort_unless(is_string($filename) && is_file($filename), 404);

        return response()->file($filename);
    }

    private function payload(Prompt $prompt): array
    {
        $status = match ((int) $prompt->render_status) { 2 => 'ready', 4 => 'failed', default => 'pending' };

        return ['id' => $prompt->id, 'status' => $status, 'prompt' => $prompt->original_prompt,
            'createdAt' => $prompt->created_at?->toIso8601String(),
            'previewUrl' => $status === 'ready' ? route('photoshop.gen-ai.download', $prompt) : null];
    }

    private function authorizePrompt(Prompt $prompt): void
    {
        abort_unless((int) $prompt->user_id === (int) auth()->id() && $prompt->notes === 'photoshop-gen-ai', 403);
    }
}
