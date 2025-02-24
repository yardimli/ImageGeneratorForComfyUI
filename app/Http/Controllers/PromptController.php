<?php
	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use App\Models\UserTemplate;
	use App\Services\ChatGPTService;
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Rolandstarke\Thumbnail\Facades\Thumbnail;

	class PromptController extends Controller
	{
		protected $chatgpt;

		public function __construct(ChatGPTService $chatgpt)
		{
			$this->chatgpt = $chatgpt;
		}

		public function index()
		{
			$templates = $this->getTemplates(resource_path('templates'));
			$settings = PromptSetting::where('user_id', auth()->id())->
			orderBy('created_at', 'desc')->get();

			return view('prompts.index', compact('templates', 'settings'));
		}

		public function generate(Request $request)
		{
			try {
				$validated = $request->validate([
					'prompt_template' => 'nullable',
					'precision' => 'required',
					'count' => 'required|integer|min:1',
					'render_each_prompt_times' => 'required|integer|min:1',
					'width' => 'required|integer',
					'height' => 'required|integer',
					'model' => 'required|in:schnell,dev,outpaint',
					'upload_to_s3' => 'required|in:0,1,true,false', // Modified this line
					'aspect_ratio' => 'required|string',
					'original_prompt' => 'required',
					'template_path' => 'nullable', // Add this line
					'prepend_text' => 'nullable',
					'append_text' => 'nullable',
					'generate_original_prompt' => 'nullable|boolean',
					'append_to_prompt' => 'nullable|boolean',
				]);

				$results = [];

				if ($request->generate_original_prompt && $request->original_prompt) {
					$results[] = $request->original_prompt;
				}

				// Only generate prompts if prompt field is not empty
				if (!empty($request->prompt_template)) {
					$generatedPrompts = $this->chatgpt->generatePrompts(
						$request->prompt_template,
						(int)$request->count,
						$request->precision,
						$request->original_prompt
					);
				} else {
					// If prompt is empty, use original_prompt as the only result
					$generatedPrompts = [$request->original_prompt];
				}

				// Store the settings
				PromptSetting::create([
					'user_id' => auth()->id(),
					'template_path' => $request->template_path ?? '',
					'prompt_template' => $request->prompt_template ?? '',
					'original_prompt' => $request->original_prompt,
					'precision' => $request->precision,
					'count' => $request->count,
					'render_each_prompt_times' => $request->render_each_prompt_times,
					'width' => $request->width,
					'height' => $request->height,
					'model' => $request->model,
					'upload_to_s3' => $request->boolean('upload_to_s3'),
					'aspect_ratio' => $request->aspect_ratio,
					'prepend_text' => $request->prepend_text,
					'append_text' => $request->append_text,
					'generate_original_prompt' => $request->boolean('generate_original_prompt'),
					'append_to_prompt' => $request->boolean('append_to_prompt'),
				]);

				$prompt_setting_id = PromptSetting::latest()->first()->id;


				foreach ($generatedPrompts as $generatedPrompt) {
					$finalPrompt = '';

					if ($request->append_to_prompt && $request->original_prompt) {
						$finalPrompt .= $request->original_prompt . ', ';
					}

					if ($request->prepend_text) {
						$finalPrompt .= $request->prepend_text . ' ';
					}

					$finalPrompt .= $generatedPrompt;

					if ($request->append_text) {
						$finalPrompt .= ' ' . $request->append_text;
					}

					$finalPrompt = trim($finalPrompt);
					$results[] = $finalPrompt;

					for ($i = 0; $i < $request->render_each_prompt_times; $i++) {
						// Store the prompt
						Prompt::create([
							'user_id' => auth()->id(),
							'prompt_setting_id' => $prompt_setting_id,
							'original_prompt' => $request->original_prompt,
							'generated_prompt' => $finalPrompt,
							'width' => $request->width,
							'height' => $request->height,
							'model' => $request->model,
							'upload_to_s3' => $request->boolean('upload_to_s3'),
						]);
					}
				}

				return response()->json([
					'success' => true,
					'prompts' => $results,
					'settings' => [
						'count' => $request->count,
						'precision' => $request->precision,
					],
				]);

			} catch (\Exception $e) {
				return response()->json([
					'success' => false,
					'error' => $e->getMessage(),
				]);
			}
		}

		public function loadSettings($id)
		{
			$settings = PromptSetting::findOrFail($id);
			$prompts = Prompt::where('prompt_setting_id', $id)->get();

			$prompts = Prompt::where('prompt_setting_id', $id)->get()->map(function($prompt) {
				if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
					$prompt->thumbnail = Thumbnail::src($prompt->filename)
						->preset('thumbnail_450_jpg')
						->url();
				}
				return $prompt;
			});

			return response()->json([
				'template_path' => $settings->template_path ?? '',
				'prompt_template' => $settings->prompt_template ?? '',
				'original_prompt' => $settings->original_prompt,
				'precision' => $settings->precision,
				'count' => $settings->count,
				'render_each_prompt_times' => $settings->render_each_prompt_times,
				'width' => $settings->width,
				'height' => $settings->height,
				'model' => $settings->model,
				'upload_to_s3' => $settings->upload_to_s3,
				'aspect_ratio' => $settings->aspect_ratio,
				'prepend_text' => $settings->prepend_text,
				'append_text' => $settings->append_text,
				'generate_original_prompt' => $settings->generate_original_prompt,
				'append_to_prompt' => $settings->append_to_prompt,
				'prompts' => $prompts
			]);
		}

		public function saveTemplate(Request $request)
		{
			$validated = $request->validate([
				'name' => 'required|string|max:255',
				'content' => 'required|string'
			]);

			$template = UserTemplate::create([
				'user_id' => auth()->id(),
				'name' => $validated['name'],
				'content' => $validated['content']
			]);

			return response()->json([
				'success' => true,
				'template' => $template
			]);
		}

		protected function getTemplates($directory)
		{
			$templates = [];

			$files = glob($directory . '/**/*.txt', GLOB_BRACE);

			foreach ($files as $file) {
				$type = (basename(dirname($file)) === 'append') ? 'A' : 'R';
				$name = pathinfo($file, PATHINFO_FILENAME);
				$content = file_get_contents($file);
				$templates[] = [
					'name' => "$type - $name",
					'path' => $file,
					'content' => $content,
				];
			}

			// Get user templates
			$userTemplates = UserTemplate::where('user_id', auth()->id())->get();
			foreach ($userTemplates as $template) {
				$templates[] = [
					'name' => "U - " . $template->name,
					'path' => null,
					'content' => $template->content,
					'type' => 'user',
					'id' => $template->id
				];
			}

			usort($templates, function ($a, $b) {
				return strcmp($a['name'], $b['name']);
			});

			usort($templates, function ($a, $b) {
				return strcmp($a['name'], $b['name']);
			});

			return $templates;
		}

		public function getLatestSetting()
		{
			$setting = PromptSetting::where('user_id', auth()->id())
				->latest()
				->first();

			if ($setting) {
				return response()->json([
					'success' => true,
					'setting' => [
						'id' => $setting->id,
						'created_at' => $setting->created_at->format('Y-m-d H:i'),
						'width' => $setting->width,
						'height' => $setting->height,
						'template_path' => $setting->template_path,
						'count' => $setting->count,
						'render_each_prompt_times' => $setting->render_each_prompt_times,
					]
				]);
			}

			return response()->json([
				'success' => false,
				'message' => 'No settings found'
			]);
		}

	}
