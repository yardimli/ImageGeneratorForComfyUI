<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use App\Services\FalModelService;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Validation\Rule;
	use Throwable;

	/**
	 * NEW: Handles the "Image Edit" feature.
	 */
	class ImageEditController extends Controller
	{
		/**
		 * Display the image editing page.
		 *
		 * @return \Illuminate\View\View
		 */
		public function index(FalModelService $falModels)
		{
			$modelSyncError = null;

			try {
				$imageModels = $falModels->models('image-to-image');
			} catch (Throwable $exception) {
				report($exception);
				$imageModels = [];
				$modelSyncError = $exception->getMessage();
			}

			return view('image-edit.index', [
				'imageModels' => $imageModels,
				'modelSyncError' => $modelSyncError,
				'modelsUpdatedAt' => $falModels->lastUpdatedAt('image-to-image'),
			]);
		}

		public function refreshModels(FalModelService $falModels)
		{
			try {
				$models = $falModels->refreshModels('image-to-image');

				return response()->json([
					'success' => true,
					'count' => count($models),
				]);
			} catch (Throwable $exception) {
				report($exception);

				return response()->json([
					'success' => false,
					'message' => $exception->getMessage(),
				], 502);
			}
		}

		public function modelPricing(Request $request, FalModelService $falModels)
		{
			$validated = $request->validate([
				'endpoint_id' => ['required', 'string', 'max:255'],
			]);

			try {
				return response()->json([
					'success' => true,
					'price' => $falModels->price($validated['endpoint_id']),
				]);
			} catch (Throwable $exception) {
				report($exception);

				return response()->json([
					'success' => false,
					'message' => $exception->getMessage(),
				], 502);
			}
		}

		/**
		 * Creates a prompt to queue an image using an image editing model.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generate(Request $request, FalModelService $falModels)
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
				'prompt' => 'required|string',
				'model' => ['required', 'string', Rule::in($allowedModels)],
				'width' => 'required|integer|min:1',
				'height' => 'required|integer|min:1',
				'upload_to_s3' => 'required|boolean',
				'aspect_ratio' => 'required|string',
				'input_images' => 'present|array|min:1',
				'input_images.*' => 'string',
			]);

			try {
				$model = $validated['model'];

				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => 'prompt',
					'template_path' => '',
					'prompt_template' => '',
					'original_prompt' => $validated['prompt'],
					'precision' => 'Normal',
					'count' => 1,
					'render_each_prompt_times' => 1,
					'width' => $validated['width'],
					'height' => $validated['height'],
					'model' => $model,
					'lora_name' => '',
					'strength_model' => 0,
					'guidance' => 7.5,
					'upload_to_s3' => $validated['upload_to_s3'],
					'aspect_ratio' => $validated['aspect_ratio'],
					'prepend_text' => '',
					'append_text' => '',
					'generate_original_prompt' => false,
					'append_to_prompt' => false,
					'input_images_1' => '',
					'input_images_2' => '',
					'input_images' => $validated['input_images'],
				]);

				// Create a Prompt entry
				$prompt = Prompt::create([
					'user_id' => auth()->id(),
					'prompt_setting_id' => $promptSetting->id,
					'generation_type' => 'prompt',
					'original_prompt' => $validated['prompt'],
					'generated_prompt' => $validated['prompt'],
					'model' => $model,
					'width' => $validated['width'],
					'height' => $validated['height'],
					'upload_to_s3' => $validated['upload_to_s3'],
					'input_images' => $validated['input_images'],
				]);

				return response()->json(['success' => true, 'message' => 'Image generation has been queued successfully.', 'prompt_id' => $prompt->id]);
			} catch (Throwable $e) {
				Log::error('Failed to queue image edit generation: ' . $e->getMessage(), ['exception' => $e]);
				return response()->json(['success' => false, 'message' => 'An error occurred while queueing the image.'], 500);
			}
		}

		/**
		 * Checks the status of an image generation prompt.
		 *
		 * @param  \App\Models\Prompt  $prompt
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function checkStatus(Prompt $prompt)
		{
			try {
				// Basic authorization check
				if ($prompt->user_id !== auth()->id()) {
					return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
				}

				if ($prompt->filename) {
					// Image is ready
					return response()->json([
						'success' => true,
						'status' => 'ready',
						'filename' => $prompt->filename,
						'thumbnail' => $prompt->thumbnail,
						'preview_url' => $prompt->preview_url,
						'preview_thumbnail_url' => $prompt->preview_thumbnail_url,
					]);
				}

				// Image not ready yet
				return response()->json(['success' => true, 'status' => 'pending']);
			} catch (Throwable $e) {
				Log::error('Failed to check image edit status: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while checking status.'], 500);
			}
		}
	}
