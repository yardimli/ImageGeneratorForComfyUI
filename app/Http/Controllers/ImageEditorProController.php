<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class ImageEditorProController extends Controller
{
    private const MODEL = 'bytedance/seedream/v5/pro/edit';

    private const COLORS = ['red', 'green', 'yellow', 'blue', 'purple'];

    public function index()
    {
        return view('image-editor-pro.index');
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'general_prompt' => ['nullable', 'string', 'max:5000'],
            'general_position' => ['required', Rule::in(['beginning', 'end'])],
            'image' => ['required', 'string', 'max:200000'],
            'width' => ['required', 'integer', 'min:1', 'max:6000'],
            'height' => ['required', 'integer', 'min:1', 'max:6000'],
            'areas' => ['present', 'array', 'max:5'],
            'areas.*.color' => ['required', Rule::in(self::COLORS)],
            'areas.*.prompt' => ['required', 'string', 'max:2000'],
        ]);

        $areaLines = collect($validated['areas'])
            ->map(fn (array $area) => ucfirst($area['color']).' frame: '.trim($area['prompt']))
            ->all();
        $generalPrompt = trim($validated['general_prompt'] ?? '');
        $parts = $validated['general_position'] === 'beginning'
            ? array_merge($generalPrompt !== '' ? [$generalPrompt] : [], $areaLines)
            : array_merge($areaLines, $generalPrompt !== '' ? [$generalPrompt] : []);
        $combinedPrompt = implode("\n", array_map(fn (string $part) => '• '.$part, $parts));

        if ($combinedPrompt === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter a general prompt or add at least one prompted area.',
            ], 422);
        }

        try {
            $prompt = DB::transaction(function () use ($validated, $combinedPrompt) {
                $setting = PromptSetting::create([
                    'user_id' => auth()->id(),
                    'generation_type' => 'prompt',
                    'template_path' => '',
                    'prompt_template' => '',
                    'original_prompt' => $combinedPrompt,
                    'precision' => 'Normal',
                    'count' => 1,
                    'render_each_prompt_times' => 1,
                    'width' => $validated['width'],
                    'height' => $validated['height'],
                    'model' => self::MODEL,
                    'upload_to_s3' => true,
                    'aspect_ratio' => 'auto_2K',
                    'prepend_text' => '',
                    'append_text' => '',
                    'generate_original_prompt' => false,
                    'append_to_prompt' => false,
                    'input_images_1' => '',
                    'input_images_2' => '',
                    'input_images' => [$validated['image']],
                ]);

                return Prompt::create([
                    'user_id' => auth()->id(),
                    'prompt_setting_id' => $setting->id,
                    'generation_type' => 'prompt',
                    'original_prompt' => $combinedPrompt,
                    'generated_prompt' => $combinedPrompt,
                    'model' => self::MODEL,
                    'width' => $validated['width'],
                    'height' => $validated['height'],
                    'upload_to_s3' => true,
                    'input_images' => [$validated['image']],
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Seedream Pro edit queued.',
                'prompt_id' => $prompt->id,
                'combined_prompt' => $combinedPrompt,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to queue Seedream Pro edit.', ['message' => $exception->getMessage()]);
            report($exception);

            return response()->json(['success' => false, 'message' => 'Could not queue the image edit.'], 500);
        }
    }

    public function status(Prompt $prompt)
    {
        abort_unless((int) $prompt->user_id === (int) auth()->id(), 403);

        return response()->json([
            'success' => true,
            'status' => match ((int) $prompt->render_status) {
                2 => 'ready',
                4 => 'failed',
                default => 'pending',
            },
            'preview_url' => $prompt->preview_url,
        ]);
    }
}
