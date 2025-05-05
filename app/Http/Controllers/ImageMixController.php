<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\PromptSetting;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;

	class ImageMixController extends Controller
	{
		public function index()
		{
			// Get saved settings with generation_type 'mix'
			$settings = PromptSetting::where('user_id', auth()->id())
				->where('generation_type', 'mix')
				->orWhere('generation_type', 'mix-one')
				->orderBy('created_at', 'desc')
				->get();

			return view('image-mix.index', compact('settings'));
		}

		public function store(Request $request)
		{
			try {
				$validated = $request->validate([
					'input_images_1' => 'required|json',
					'input_images_2' => 'required|json',
					'width' => 'required|integer',
					'height' => 'required|integer',
					'model' => 'required|in:schnell,dev,outpaint',
					'upload_to_s3' => 'required|in:0,1,true,false',
					'aspect_ratio' => 'required|string',
					'render_each_prompt_times' => 'required|integer|min:1',
					'generation_type' => 'required|in:mix,mix-one',
				]);

				// Store the settings
				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => $request->generation_type,
					'template_path' => '',
					'prompt_template' => '',
					'original_prompt' => '',
					'precision' => 'Normal',
					'count' => 1,
					'render_each_prompt_times' => $request->render_each_prompt_times,
					'width' => $request->width,
					'height' => $request->height,
					'model' => $request->model,
					'upload_to_s3' => filter_var($request->upload_to_s3, FILTER_VALIDATE_BOOLEAN),
					'aspect_ratio' => $request->aspect_ratio,
					'prepend_text' => '',
					'append_text' => '',
					'generate_original_prompt' => false,
					'append_to_prompt' => false,
					'input_images_1' => $request->input_images_1,
					'input_images_2' => $request->input_images_2,
				]);

				// Create prompts based on the number of times to render
				$inputImages1 = json_decode($request->input_images_1, true);

				if ($request->generation_type === 'mix') {
					$inputImages2 = json_decode($request->input_images_2, true);

					foreach ($inputImages1 as $image1) {
						foreach ($inputImages2 as $image2) {
							// For each combination of images, create the prompt
							for ($i = 0; $i < $request->render_each_prompt_times; $i++) {
								Prompt::create([
									'user_id' => auth()->id(),
									'generation_type' => 'mix',
									'prompt_setting_id' => $promptSetting->id,
									'original_prompt' => '',
									'generated_prompt' => $image1['prompt'],
									'width' => $request->width,
									'height' => $request->height,
									'model' => $request->model,
									'upload_to_s3' => filter_var($request->upload_to_s3, FILTER_VALIDATE_BOOLEAN),
									'input_image_1' => $image1['path'],
									'input_image_1_strength' => $image1['strength'],
									'input_image_2' => $image2['path'],
									'input_image_2_strength' => $image2['strength'],
								]);
							}
						}
					}
				} else {
					// Updated mix-one logic
					$inputPrompts = json_decode($request->input_images_2, true);

					foreach ($inputImages1 as $image1) {
						foreach ($inputPrompts as $prompt) {
							if (isset($prompt['text']) && $prompt['text'] !== '') {
								for ($i = 0; $i < $request->render_each_prompt_times; $i++) {
									Prompt::create([
										'user_id' => auth()->id(),
										'generation_type' => 'mix-one',
										'prompt_setting_id' => $promptSetting->id,
										'original_prompt' => '',
										'generated_prompt' => $prompt['text'],
										'width' => $request->width,
										'height' => $request->height,
										'model' => $request->model,
										'upload_to_s3' => filter_var($request->upload_to_s3, FILTER_VALIDATE_BOOLEAN),
										'input_image_1' => $image1['path'],
										'input_image_1_strength' => $image1['strength'],
										'input_image_2' => '',
										'input_image_2_strength' => 5,
									]);
								}
							}
						}
					}
				}


				return response()->json([
					'success' => true,
					'setting_id' => $promptSetting->id,
				]);
			} catch (\Exception $e) {
				return response()->json([
					'success' => false,
					'error' => $e->getMessage(),
				]);
			}
		}

		public function uploadImage(Request $request)
		{
			try {
				$request->validate([
					'image' => 'required|image|max:10240', // 10MB max
				]);

				if ($request->hasFile('image')) {
					$image = $request->file('image');
					$filename = time() . '_' . $image->getClientOriginalName();

					// Store the file
					$path = $image->storeAs('public/uploads', $filename);

					return response()->json([
						'success' => true,
						'path' => asset('storage/uploads/' . $filename),
						'filename' => $filename,
					]);
				}

				return response()->json([
					'success' => false,
					'error' => 'No image found',
				]);
			} catch (\Exception $e) {
				return response()->json([
					'success' => false,
					'error' => $e->getMessage(),
				]);
			}
		}

		public function getUploadedImages(Request $request)
		{
			$page = $request->query('page', 1);
			// Get sort and perPage parameters with defaults
			$sort = $request->query('sort', 'newest'); // Default to newest
			$perPage = $request->query('perPage', 12); // Default to 12

			// Validate perPage to be one of the allowed values
			$allowedPerPages = [12, 24, 48, 96];
			if (!in_array($perPage, $allowedPerPages)) {
				$perPage = 12; // Reset to default if invalid
			}
			$perPage = (int)$perPage; // Cast to integer

			// --- Start: Existing logic to gather images and usage counts ---
			// Get all prompt settings for this user that have input images (mix type)
			$prompts = Prompt::where('user_id', auth()->id())
				->where('generation_type', 'mix')
				->whereNotNull('input_image_1')
				->WhereNotNull('input_image_2')
				->select('input_image_1', 'input_image_2')
				->get();

			$usageCounts = []; // Track usage count for each image path
			foreach ($prompts as $prompt) {
				if (!empty($prompt->input_image_1)) {
					if (!isset($usageCounts[$prompt->input_image_1])) {
						$usageCounts[$prompt->input_image_1] = 0;
					}
					$usageCounts[$prompt->input_image_1]++;
				}
				if (!empty($prompt->input_image_2)) {
					if (!isset($usageCounts[$prompt->input_image_2])) {
						$usageCounts[$prompt->input_image_2] = 0;
					}
					$usageCounts[$prompt->input_image_2]++;
				}
			}

			// Get all prompt settings for this user that have input images (mix and mix-one)
			// Note: We might need to adjust this if mix-one also stores images differently
			$settings = PromptSetting::where('user_id', auth()->id())
				->whereIn('generation_type', ['mix', 'mix-one']) // Include both types if needed
				->where(function ($query) {
					$query->whereNotNull('input_images_1')
						->orWhereNotNull('input_images_2');
				})
				->select('input_images_1', 'input_images_2', 'generation_type') // Select generation_type
				->get();

			$images = [];
			foreach ($settings as $setting) {
				if ($setting->input_images_1) {
					$inputImages1 = json_decode($setting->input_images_1, true);
					foreach ($inputImages1 as $img) {
						if (isset($img['path']) && !empty($img['path'])) {
							$images[] = [
								'path' => $img['path'],
								'name' => basename($img['path']),
								'prompt' => $img['prompt'] ?? ''
							];
						}
					}
				}
				// Only process input_images_2 if it's for 'mix' type (contains images)
				if ($setting->generation_type === 'mix' && $setting->input_images_2) {
					$inputImages2 = json_decode($setting->input_images_2, true);
					foreach ($inputImages2 as $img) {
						// Ensure it's an image structure, not a prompt structure from mix-one
						if (isset($img['path']) && !empty($img['path'])) {
							$images[] = [
								'path' => $img['path'],
								'name' => basename($img['path']),
								'prompt' => '' // Right side images don't have prompts in this structure
							];
						}
					}
				}
			}

			// Get unique images and add usage count
			$uniqueImages = [];
			$paths = [];
			foreach ($images as $image) {
				if (!in_array($image['path'], $paths)) {
					$paths[] = $image['path'];
					// Add usage count to the image data
					$image['usage_count'] = $usageCounts[$image['path']] ?? 0;
					$uniqueImages[] = $image;
				}
			}
			$images = $uniqueImages;
			// --- End: Existing logic ---

			// Apply sorting based on the 'sort' parameter
			usort($images, function ($a, $b) use ($sort) {
				switch ($sort) {
					case 'oldest':
						// Assuming filenames contain sortable timestamps (like time())
						return strcmp($a['name'], $b['name']);
					case 'count_desc':
						// Sort by usage count descending, then by name descending as tie-breaker
						$countComparison = $b['usage_count'] <=> $a['usage_count'];
						return $countComparison !== 0 ? $countComparison : strcmp($b['name'], $a['name']);
					case 'newest':
					default:
						// Assuming filenames contain sortable timestamps (like time())
						return strcmp($b['name'], $a['name']); // Default newest first
				}
			});

			// Pagination - Use the dynamic $perPage
			$totalImages = count($images);
			$totalPages = ceil($totalImages / $perPage);
			$startIndex = ($page - 1) * $perPage;
			$currentPageImages = array_slice($images, $startIndex, $perPage);

			return response()->json([
				'images' => $currentPageImages,
				'pagination' => [
					'current_page' => (int)$page,
					'total_pages' => $totalPages,
					'total_images' => $totalImages,
					'per_page' => $perPage // Return per_page setting
				]
			]);
		}

	}
