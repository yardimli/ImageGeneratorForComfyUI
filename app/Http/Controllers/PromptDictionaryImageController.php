<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptDictionaryEntry;
	use App\Models\PromptSetting;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Throwable;

	/**
	 * Handles image generation requests originating from the prompt dictionary editor.
	 */
	class PromptDictionaryImageController extends Controller
	{
		/**
		 * Creates prompt settings and a prompt to queue an image for a dictionary entry.
		 *
		 * @param  Request  $request
		 * @param  PromptDictionaryEntry  $entry
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generate(Request $request, PromptDictionaryEntry $entry)
		{
			if ($entry->user_id !== auth()->id()) {
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
				$promptText = $entry->image_prompt;
				if (empty($promptText)) {
					return response()->json(['success' => false, 'message' => 'Image prompt for this entry is empty.'], 422);
				}

				// Create a PromptSetting entry
				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => 'prompt',
					'original_prompt' => $promptText,
					'precision' => 'Normal',
					'count' => 1,
					'render_each_prompt_times' => 1,
					'width' => $validated['width'],
					'height' => $validated['height'],
					'model' => $validated['model'], // Use the validated model from the request.
					'upload_to_s3' => $validated['upload_to_s3'],
					'aspect_ratio' => $validated['aspect_ratio'],
					'prompt_dictionary_entry_id' => $entry->id,
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
					'prompt_dictionary_entry_id' => $entry->id,
				]);

				return response()->json(['success' => true, 'message' => 'Image generation has been queued successfully.']);
			} catch (Throwable $e) {
				Log::error('Failed to queue dictionary image generation: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while queueing the image.'], 500);
			}
		}

		/**
		 * Checks the status of the latest image generation for a dictionary entry.
		 *
		 * @param  PromptDictionaryEntry  $entry
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function checkStatus(PromptDictionaryEntry $entry)
		{
			if ($entry->user_id !== auth()->id()) {
				return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
			}

			try {
				// Find the latest queued prompt for this entry.
				$prompt = Prompt::where('prompt_dictionary_entry_id', $entry->id)
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
				Log::error('Failed to check dictionary image status: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while checking status.'], 500);
			}
		}
	}
