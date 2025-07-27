<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use App\Models\StoryPage;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Throwable;

	/**
	 * Handles image generation requests originating from the story editor.
	 */
	class StoryImageController extends Controller
	{
		/**
		 * Creates prompt settings and a prompt to queue an image for generation.
		 *
		 * @param Request $request
		 * @param StoryPage $storyPage
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generate(Request $request, StoryPage $storyPage)
		{
			// Authorization check
			if ($storyPage->story->user_id !== auth()->id()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
			}

			$validated = $request->validate([
				'model' => 'required|string|max:255',
				'width' => 'required|integer|min:1',
				'height' => 'required|integer|min:1',
				'upload_to_s3' => 'required|boolean',
				'aspect_ratio' => 'required|string',
			]);

			try {
				// The prompt is the image_prompt from the story page
				$promptText = $storyPage->image_prompt;
				if (empty($promptText)) {
					return response()->json(['success' => false, 'message' => 'Image prompt for this page is empty.'], 422);
				}

				// Create a PromptSetting entry
				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => 'prompt',
					'template_path' => '',
					'prompt_template' => '',
					'original_prompt' => $promptText,
					'precision' => 'Normal',
					'count' => 1,
					'render_each_prompt_times' => 1,
					'width' => $validated['width'],
					'height' => $validated['height'],
					'model' => $validated['model'],
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
					'story_page_id' => $storyPage->id,
				]);

				// Create a Prompt entry
				Prompt::create([
					'user_id' => auth()->id(),
					'prompt_setting_id' => $promptSetting->id,
					'generation_type' => 'prompt',
					'original_prompt' => $promptText,
					'generated_prompt' => $promptText,
					'model' => $validated['model'],
					'width' => $validated['width'],
					'height' => $validated['height'],
					'upload_to_s3' => $validated['upload_to_s3'],
					'story_page_id' => $storyPage->id, // Link to the story page
				]);

				return response()->json(['success' => true, 'message' => 'Image generation has been queued successfully.']);

			} catch (Throwable $e) {
				Log::error('Failed to queue story image generation: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while queueing the image.'], 500);
			}
		}
	}
