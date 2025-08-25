<?php

	namespace App\Http\Controllers;

	use App\Http\Controllers\LlmController;
	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use App\Models\UserTemplate;
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Rolandstarke\Thumbnail\Facades\Thumbnail;
	use Exception;

	class PromptController extends Controller
	{
		protected $llmController;

		public function __construct(LlmController $llmController)
		{
			$this->llmController = $llmController;
		}

		// MODIFICATION START: Updated to load all models from JSON, using short names where available.
		/**
		 * Loads and formats the available image generation models for use in views.
		 *
		 * @return array
		 */
		protected function getAvailableImageModels(): array
		{
			try {
				$jsonString = file_get_contents(resource_path('text-to-image-models/models.json'));
				$allModels = json_decode($jsonString, true);
			} catch (Exception $e) {
				Log::error('Failed to load image models from JSON: ' . $e->getMessage());
				return [];
			}

			// This map defines which models get a "short name". Others will use their full name.
			$supportedModelsMap = [
				'schnell' => 'flux-1/schnell',
				'dev' => 'flux-1/dev',
				'minimax' => 'minimax/image-01',
				'imagen3' => 'imagen4/preview/ultra',
				'aura-flow' => 'aura-flow',
				'ideogram-v2a' => 'ideogram/v2a',
				'luma-photon' => 'luma-photon',
				'recraft-20b' => 'recraft-20b',
				'fal-ai/qwen-image' => 'qwen-image',
			];

			$viewModels = [];
			$foundModels = [];

			foreach ($allModels as $modelData) {
				$fullName = $modelData['name'];
				$shortName = array_search($fullName, $supportedModelsMap);

				// The ID for the form value. Use short name if it exists, otherwise full name.
				$id = ($shortName !== false) ? $shortName : $fullName;

				// The name for display in the dropdown.
				$displayNameBase = ($shortName !== false) ? $shortName : $fullName;
				$displayName = ucfirst(str_replace(['-', '_', '/'], ' ', $displayNameBase));
				if (isset($modelData['price'])) {
					$displayName .= " (\${$modelData['price']})";
				}

				$viewModels[] = [
					'id' => $id,
					'name' => $displayName,
				];

				if ($shortName === 'minimax') {
					$foundModels['minimax'] = true;
				}
			}

			// Manually add minimax-expand if minimax was found
			if (isset($foundModels['minimax'])) {
				$viewModels[] = [
					'id' => 'minimax-expand',
					'name' => 'Minimax Expand ($0.01)', // Price is hardcoded as it's a variant
				];
			}

			usort($viewModels, fn($a, $b) => strcmp($a['name'], $b['name']));
			return $viewModels;
		}
		// MODIFICATION END

		public function index()
		{
			$templates = $this->getTemplates(resource_path('templates'));
			$settings = PromptSetting::where('user_id', auth()->id())->
			orderBy('created_at', 'desc')->get();
			// MODIFICATION START: Load image models for the new dropdown.
			$imageModels = $this->getAvailableImageModels();

			return view('prompts.index', compact('templates', 'settings', 'imageModels'));
			// MODIFICATION END
		}

		public function generate(Request $request)
		{
			try {
				// MODIFICATION START: Replaced checkbox validation with a single model dropdown validation.
				$validated = $request->validate([
					'prompt_template' => 'nullable',
					'precision' => 'required',
					'count' => 'required|integer|min:1',
					'render_each_prompt_times' => 'required|integer|min:1',
					'width' => 'required|integer',
					'height' => 'required|integer',
					'upload_to_s3' => 'required|in:0,1,true,false',
					'model' => 'required|string', // New validation for the model dropdown
					'aspect_ratio' => 'required|string',
					'original_prompt' => 'required',
					'template_path' => 'nullable',
					'prepend_text' => 'nullable',
					'append_text' => 'nullable',
					'generate_original_prompt' => 'nullable|boolean',
					'append_to_prompt' => 'nullable|boolean',
				]);
				// MODIFICATION END

				$results = [];
				if ($request->generate_original_prompt && $request->original_prompt) {
					$results[] = $request->original_prompt;
				}

				// Only generate prompts if prompt field is not empty
				if (!empty($request->prompt_template)) {
					$generatedPrompts = $this->llmController->generateChatPrompts(
						$request->prompt_template,
						(int)$request->count,
						$request->precision,
						$request->original_prompt
					);
				} else {
					// If prompt is empty, use original_prompt as the only result
					$generatedPrompts = [$request->original_prompt];
				}

				$finalResults = [];
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
					$finalResults[] = $finalPrompt;
				}

				return response()->json([
					'success' => true,
					'prompts' => $finalResults,
					'settings' => $request->all(),
				]);
			} catch (Exception $e) {
				return response()->json([
					'success' => false,
					'error' => $e->getMessage(),
				]);
			}
		}

		public function storeGeneratedPrompts(Request $request)
		{
			try {
				$validated = $request->validate([
					'prompts' => 'required|array',
					'settings' => 'required|array',
				]);

				$settings = $request->settings;

				// MODIFICATION START: Store settings using the single 'model' field instead of multiple 'create_*' booleans.
				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => 'prompt',
					'template_path' => $settings['template_path'] ?? '',
					'prompt_template' => $settings['prompt_template'] ?? '',
					'original_prompt' => $settings['original_prompt'],
					'precision' => $settings['precision'],
					'count' => $settings['count'],
					'render_each_prompt_times' => $settings['render_each_prompt_times'],
					'width' => $settings['width'],
					'height' => $settings['height'],
					'upload_to_s3' => filter_var($settings['upload_to_s3'] ?? true, FILTER_VALIDATE_BOOLEAN),
					'model' => $settings['model'], // Store the selected model
					'aspect_ratio' => $settings['aspect_ratio'],
					'prepend_text' => $settings['prepend_text'] ?? null,
					'append_text' => $settings['append_text'] ?? null,
					'generate_original_prompt' => filter_var($settings['generate_original_prompt'] ?? false, FILTER_VALIDATE_BOOLEAN),
					'append_to_prompt' => filter_var($settings['append_to_prompt'] ?? false, FILTER_VALIDATE_BOOLEAN),
				]);
				// MODIFICATION END

				$prompt_setting_id = $promptSetting->id;

				// MODIFICATION START: Replaced multiple 'if' blocks with a single loop to create prompts for the selected model.
				foreach ($request->prompts as $finalPrompt) {
					for ($i = 0; $i < $settings['render_each_prompt_times']; $i++) {
						Prompt::create([
							'user_id' => auth()->id(),
							'generation_type' => 'prompt',
							'prompt_setting_id' => $prompt_setting_id,
							'original_prompt' => $settings['original_prompt'],
							'generated_prompt' => $finalPrompt,
							'width' => $settings['width'],
							'height' => $settings['height'],
							'model' => $settings['model'],
							'upload_to_s3' => filter_var($settings['upload_to_s3'], FILTER_VALIDATE_BOOLEAN),
						]);
					}
				}
				// MODIFICATION END

				return response()->json([
					'success' => true,
					'setting_id' => $prompt_setting_id,
				]);
			} catch (Exception $e) {
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

			$prompts = Prompt::where('prompt_setting_id', $id)->get()->map(function ($prompt) {
				if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
					$prompt->thumbnail = Thumbnail::src($prompt->filename)
						->preset('thumbnail_450_jpg')
						->url();
				}
				return $prompt;
			});

			// MODIFICATION START: Removed 'create_*' fields and added 'model' to the response.
			return response()->json([
				'template_path' => $settings->template_path ?? '',
				'prompt_template' => $settings->prompt_template ?? '',
				'original_prompt' => $settings->original_prompt,
				'precision' => $settings->precision,
				'count' => $settings->count,
				'render_each_prompt_times' => $settings->render_each_prompt_times,
				'width' => $settings->width,
				'height' => $settings->height,
				'upload_to_s3' => $settings->upload_to_s3,
				'model' => $settings->model,
				'aspect_ratio' => $settings->aspect_ratio,
				'prepend_text' => $settings->prepend_text,
				'append_text' => $settings->append_text,
				'generate_original_prompt' => $settings->generate_original_prompt,
				'append_to_prompt' => $settings->append_to_prompt,
				'prompts' => $prompts
			]);
			// MODIFICATION END
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

		public function bulkDelete(Request $request)
		{
			$request->validate([
				'prompt_ids' => 'required|array',
				'prompt_ids.*' => 'numeric|exists:prompts,id'
			]);

			$promptIds = $request->prompt_ids;
			$userId = auth()->id();

			// Get prompts that belong to the current user before deleting them
			$prompts = Prompt::where('user_id', $userId)
				->whereIn('id', $promptIds)
				->get();

			$deletedCount = 0;

			foreach ($prompts as $prompt) {
				// Delete image file if exists and is not an S3 URL
				if ($prompt->filename && !str_contains($prompt->filename, 'https://')) {
					Storage::delete('public/images/' . $prompt->filename);
				}

				// Delete upscaled image if exists
				if ($prompt->upscale_url) {
					Storage::delete('public/upscaled/' . $prompt->upscale_url);
				}

				$prompt->delete();
				$deletedCount++;
			}

			return response()->json([
				'success' => true,
				'message' => "Successfully deleted {$deletedCount} images",
				'deleted_count' => $deletedCount
			]);
		}

		public function deletePrompt(Prompt $prompt)
		{
			// Check authorization
			if ($prompt->user_id !== auth()->id()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
			}

			// Delete image file if exists and is not an S3 URL
			if ($prompt->filename && !str_contains($prompt->filename, 'https://')) {
				Storage::delete('public/images/' . $prompt->filename);
			}

			// Delete upscaled image if exists
			if ($prompt->upscale_url) {
				Storage::delete('public/upscaled/' . $prompt->upscale_url);
			}

			$prompt->delete();

			return response()->json(['success' => true, 'message' => 'Image deleted successfully']);
		}

		public function deleteSettingWithImages($id)
		{
			$setting = PromptSetting::findOrFail($id);

			// Check authorization
			if ($setting->user_id !== auth()->id()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
			}

			// Get all associated prompts
			$prompts = Prompt::where('prompt_setting_id', $id)->get();

			// Delete all image files
			foreach ($prompts as $prompt) {
				if ($prompt->filename && !str_contains($prompt->filename, 'https://')) {
					Storage::delete('public/images/' . $prompt->filename);
				}

				if ($prompt->upscale_url) {
					Storage::delete('public/upscaled/' . $prompt->upscale_url);
				}

				$prompt->delete();
			}

			// Delete the setting
			$setting->delete();

			return response()->json(['success' => true, 'message' => 'Settings and images deleted successfully']);
		}

		public function queue()
		{
			// Get queued prompts for the current user
			$queuedPrompts = Prompt::where('user_id', auth()->id())
				->whereIn('render_status', ['queued', 'pending', null]) // Not yet processed
				->orderBy('created_at', 'desc')
				->get();

			$failedPrompts = Prompt::where('user_id', auth()->id())
				->where('render_status', 4) // Status for failed
				->orWhere('render_status', 3) // In progress
				->orWhere('render_status', 1) // In progress
				->orderBy('updated_at', 'desc') // Show most recently failed first
				->get();

			return view('prompts.queue', compact('queuedPrompts', 'failedPrompts'));
		}


		public function deleteQueuedPrompt(Prompt $prompt)
		{
			// Check authorization
			if ($prompt->user_id !== auth()->id()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
			}

			$prompt->delete();
			return response()->json(['success' => true, 'message' => 'Prompt deleted successfully']);
		}

		public function deleteAllQueuedPrompts()
		{
			$userId = auth()->id();

			// Delete all queued prompts for the current user
			$deleted = Prompt::where('user_id', $userId)
				->whereIn('render_status', ['queued', 'pending', null])
				->delete();

			return response()->json([
				'success' => true,
				'message' => "Successfully deleted $deleted queued prompts",
				'deleted_count' => $deleted
			]);
		}

		public function requeuePrompt(Prompt $prompt)
		{
			// Check authorization
			if ($prompt->user_id !== auth()->id()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
			}

			// Check if the prompt actually failed
			if ($prompt->render_status != 4) {
				return response()->json(['success' => false, 'message' => 'Prompt did not fail, cannot requeue'], 400);
			}

			// Reset status to queued (0)
			$prompt->render_status = 0;
			$prompt->save();

			return response()->json([
				'success' => true,
				'message' => 'Prompt requeued successfully',
				'prompt_id' => $prompt->id
			]);
		}


	}
