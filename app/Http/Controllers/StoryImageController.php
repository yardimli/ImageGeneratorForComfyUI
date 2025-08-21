<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use App\Models\StoryPage;
	use App\Models\StoryCharacter;
	use App\Models\StoryPlace;
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
			//  Removed authorization check.
			

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
					'model' => 'dev',
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

		/**
		 * Checks the status of the latest image generation for a story page.
		 *
		 * @param StoryPage $storyPage
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function checkStatus(StoryPage $storyPage)
		{
			try {
				// Find the latest queued prompt for this page.
				// We check for prompts created in the last hour to avoid picking up old, stuck jobs.
				$prompt = Prompt::where('story_page_id', $storyPage->id)
					->where('created_at', '>=', now()->subHour())
					->latest()
					->first();

				if ($prompt && $prompt->filename) {
					// Image is ready
					return response()->json([
						'success' => true,
						'status' => 'ready',
						'filename' => $prompt->filename,
						'thumbnail' => $prompt->thumbnail,
						'prompt_id' => $prompt->id,
						'upscale_status' => $prompt->upscale_status,
						'upscale_url' => $prompt->upscale_url,
					]);
				}

				// Image not ready yet
				return response()->json(['success' => true, 'status' => 'pending']);
			} catch (Throwable $e) {
				Log::error('Failed to check story image status: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while checking status.'], 500);
			}
		}

		/**
		 * Creates a prompt to queue an image for a character.
		 *
		 * @param Request $request
		 * @param StoryCharacter $character
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateForCharacter(Request $request, StoryCharacter $character)
		{
			return $this->doGenerate($request, $character, 'character');
		}

		/**
		 * Checks the status of the latest image generation for a character.
		 *
		 * @param StoryCharacter $character
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function checkCharacterStatus(StoryCharacter $character)
		{
			//  Removed authorization check.
			
			return $this->doCheckStatus('story_character_id', $character->id);
		}

		/**
		 * Creates a prompt to queue an image for a place.
		 *
		 * @param Request $request
		 * @param StoryPlace $place
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateForPlace(Request $request, StoryPlace $place)
		{
			return $this->doGenerate($request, $place, 'place');
		}

		/**
		 * Checks the status of the latest image generation for a place.
		 *
		 * @param StoryPlace $place
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function checkPlaceStatus(StoryPlace $place)
		{
			//  Removed authorization check.
			
			return $this->doCheckStatus('story_place_id', $place->id);
		}

		/**
		 * Private helper to handle image generation logic for any asset type.
		 *
		 * @param Request $request
		 * @param \Illuminate\Database\Eloquent\Model $asset
		 * @param string $assetType
		 * @return \Illuminate\Http\JsonResponse
		 */
		private function doGenerate(Request $request, $asset, string $assetType)
		{
			$validated = $request->validate([
				'model' => 'required|string|max:255',
				'width' => 'required|integer|min:1',
				'height' => 'required|integer|min:1',
				'upload_to_s3' => 'required|boolean',
				'aspect_ratio' => 'required|string',
			]);

			try {
				$promptText = $asset->image_prompt;
				if (empty($promptText)) {
					return response()->json(['success' => false, 'message' => "Image prompt for this {$assetType} is empty."], 422);
				}

				$foreignKey = 'story_' . $assetType . '_id';

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
					'model' => 'dev',
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
					$foreignKey => $asset->id,
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
					$foreignKey => $asset->id,
				]);

				return response()->json(['success' => true, 'message' => 'Image generation has been queued successfully.']);
			} catch (Throwable $e) {
				Log::error("Failed to queue story {$assetType} image generation: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while queueing the image.'], 500);
			}
		}

		/**
		 * Private helper to check image status for any asset type.
		 *
		 * @param string $foreignKey
		 * @param int $assetId
		 * @return \Illuminate\Http\JsonResponse
		 */
		private function doCheckStatus(string $foreignKey, int $assetId)
		{
			try {
				$prompt = Prompt::where($foreignKey, $assetId)
					->where('created_at', '>=', now()->subHour())
					->latest()
					->first();

				if ($prompt && $prompt->filename) {
					return response()->json([
						'success' => true,
						'status' => 'ready',
						'filename' => $prompt->filename,
						'thumbnail' => $prompt->thumbnail,
						'prompt_id' => $prompt->id,
						'upscale_status' => $prompt->upscale_status,
						'upscale_url' => $prompt->upscale_url,
					]);
				}

				return response()->json(['success' => true, 'status' => 'pending']);
			} catch (Throwable $e) {
				Log::error('Failed to check story asset image status: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while checking status.'], 500);
			}
		}
	}
