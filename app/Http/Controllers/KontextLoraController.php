<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Rolandstarke\Thumbnail\Facades\Thumbnail;

	/**
	 * New Controller for the Kontext Lora feature.
	 */
	class KontextLoraController extends Controller
	{
		/**
		 * Display the Kontext Lora tool page.
		 *
		 * @return \Illuminate\View\View
		 */
		public function index()
		{
			//  Load Lora data from JSON file.
			$storagePath = 'public/kontext-loras.json';
			$publicPath = resource_path('lora/kontext-loras.json');
			$loras = [];

			// Check if the file exists in storage, if not, copy it from the public directory.
			if (!Storage::exists($storagePath)) {
				if (File::exists($publicPath)) {
					$content = File::get($publicPath);
					Storage::put($storagePath, $content);
				} else {
					Log::error('Source kontext-loras.json not found in public directory.');
				}
			}

			// Read the file from storage.
			if (Storage::exists($storagePath)) {
				$jsonContent = Storage::get($storagePath);
				$loras = json_decode($jsonContent, true);

				// Handle potential JSON decoding errors.
				if (json_last_error() !== JSON_ERROR_NONE) {
					Log::error('Error decoding kontext-loras.json: ' . json_last_error_msg());
					$loras = [];
				}
			}

			return view('kontext-lora.index', compact('loras'));

		}

		/**
		 * Store a new Kontext Lora generation job.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function store(Request $request)
		{
			try {
				$validated = $request->validate([
					'image_url' => 'required|string|max:2048',
					'prompt' => 'required|string|max:4000',
					'render_each_prompt_times' => 'required|integer|min:1',
					'upload_to_s3' => 'required|in:0,1,true,false',
					// Add validation for new Lora fields
					'lora_name' => 'required|string|max:255',
					'strength_model' => 'required|numeric|min:0',
					'guidance' => 'required|numeric|min:0',
				]);

				// Create a JSON structure for the input image, similar to ImageMixController
				$inputImage = [
					[
						'path' => $validated['image_url'],
						'prompt' => '', // No prompt associated with the image itself here
						'strength' => 5 // Default strength
					]
				];

				// Store the settings
				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => 'kontext-lora',
					'template_path' => '',
					'prompt_template' => '',
					'original_prompt' => $validated['prompt'],
					'precision' => 'Normal',
					'count' => 1,
					'render_each_prompt_times' => $validated['render_each_prompt_times'],
					'width' => 1024, // Default width for kontext
					'height' => 1024, // Default height for kontext
					'model' => 'dev', // Hardcoded as per request
					// Add new Lora fields to settings
					'lora_name' => $validated['lora_name'],
					'strength_model' => $validated['strength_model'],
					'guidance' => $validated['guidance'],
					'upload_to_s3' => filter_var($validated['upload_to_s3'], FILTER_VALIDATE_BOOLEAN),
					'aspect_ratio' => '1:1',
					'prepend_text' => '',
					'append_text' => '',
					'generate_original_prompt' => false,
					'append_to_prompt' => false,
					'input_images_1' => json_encode($inputImage),
					'input_images_2' => '',
				]);

				// Create prompts based on the number of times to render
				for ($i = 0; $i < $validated['render_each_prompt_times']; $i++) {
					Prompt::create([
						'user_id' => auth()->id(),
						'generation_type' => 'kontext-lora',
						'prompt_setting_id' => $promptSetting->id,
						'original_prompt' => $validated['prompt'],
						'generated_prompt' => $validated['prompt'],
						'width' => 1024,
						'height' => 1024,
						'model' => 'dev',
						// Add new Lora fields to prompt
						'lora_name' => $validated['lora_name'],
						'strength_model' => $validated['strength_model'],
						'guidance' => $validated['guidance'],
						'upload_to_s3' => filter_var($validated['upload_to_s3'], FILTER_VALIDATE_BOOLEAN),
						'input_image_1' => $validated['image_url'],
						'input_image_1_strength' => 5, // Default strength
						'input_image_2' => '',
						'input_image_2_strength' => 0, // No second image
					]);
				}

				return response()->json([
					'success' => true,
					'setting_id' => $promptSetting->id,
					'message' => 'Kontext Lora job queued successfully.'
				]);
			} catch (\Exception $e) {
				Log::error('Kontext Lora store error: ' . $e->getMessage());
				return response()->json([
					'success' => false,
					'error' => $e->getMessage(),
				], 500);
			}
		}

		/**
		 * Get previously rendered images for the user.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function getRenderHistory(Request $request)
		{
			$query = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename')
				->where('filename', '!=', '');

			// Sorting
			$sort = $request->query('sort', 'newest');
			if ($sort === 'oldest') {
				$query->orderBy('created_at', 'asc');
			} else {
				$query->orderBy('created_at', 'desc'); // Default to newest
			}

			$perPage = $request->query('perPage', 12);
			$allowedPerPages = [12, 24, 48, 96];
			if (!in_array($perPage, $allowedPerPages)) {
				$perPage = 12;
			}

			$renders = $query->paginate($perPage);

			// Add thumbnail URL to each prompt
			$renders->getCollection()->transform(function ($prompt) {
				if ($prompt->filename) {
					// Check if it's a full URL (S3) or just a filename
					$isS3Url = filter_var($prompt->filename, FILTER_VALIDATE_URL);
					$imageUrl = $isS3Url ? $prompt->filename : asset('storage/images/' . $prompt->filename);

					$prompt->image_url = $imageUrl;
					try {
						$prompt->thumbnail_url = Thumbnail::src($imageUrl)
							->preset('thumbnail_450_jpg')
							->url();
					} catch (\Exception $e) {
						// Handle cases where thumbnail generation might fail
						$prompt->thumbnail_url = $imageUrl; // Fallback to full image
						Log::warning("Could not generate thumbnail for: " . $imageUrl . " Error: " . $e->getMessage());
					}
				}
				return $prompt;
			});

			return response()->json($renders);
		}
	}
