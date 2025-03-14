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
				]);

				// Store the settings
				$promptSetting = PromptSetting::create([
					'user_id' => auth()->id(),
					'generation_type' => 'mix',
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
				$inputImages2 = json_decode($request->input_images_2, true);

				foreach ($inputImages1 as $image1) {
					foreach ($inputImages2 as $image2) {

						// For each combination of images, create the prompt
						for ($i = 0; $i < $request->render_each_prompt_times; $i++) {
							Prompt::create([
								'user_id' => auth()->id(),
								'generation_type' => 'mix',
								'prompt_setting_id' => $promptSetting->id,
								'original_prompt' => '', // Empty for image mix
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
			$perPage = 20;

			// Get all prompt settings for this user that have input images
			$settings = PromptSetting::where('user_id', auth()->id())
				->where('generation_type', 'mix')
				->where(function($query) {
					$query->whereNotNull('input_images_1')
						->orWhereNotNull('input_images_2');
				})
				->select('input_images_1', 'input_images_2')
				->get();

			$images = [];
			foreach ($settings as $setting) {
				if ($setting->input_images_1) {
					$inputImages1 = json_decode($setting->input_images_1, true);
					foreach ($inputImages1 as $img) {
						if (isset($img['path'])) {
							$images[] = [
								'path' => $img['path'],
								'name' => basename($img['path']),
								'prompt' => $img['prompt'] ?? ''
							];
						}
					}
				}
				if ($setting->input_images_2) {
					$inputImages2 = json_decode($setting->input_images_2, true);
					foreach ($inputImages2 as $img) {
						if (isset($img['path'])) {
							$images[] = [
								'path' => $img['path'],
								'name' => basename($img['path']),
								'prompt' => ''
							];
						}
					}
				}
			}

			// Get unique images
			$uniqueImages = [];
			$paths = [];
			foreach ($images as $image) {
				if (!in_array($image['path'], $paths)) {
					$paths[] = $image['path'];
					$uniqueImages[] = $image;
				}
			}
			$images = $uniqueImages;

			// Sort by name (timestamp is usually in the filename)
			usort($images, function($a, $b) {
				return strcmp($b['name'], $a['name']); // newest first
			});

			// Pagination
			$totalImages = count($images);
			$totalPages = ceil($totalImages / $perPage);
			$startIndex = ($page - 1) * $perPage;
			$currentPageImages = array_slice($images, $startIndex, $perPage);

			return response()->json([
				'images' => $currentPageImages,
				'pagination' => [
					'current_page' => (int)$page,
					'total_pages' => $totalPages,
					'total_images' => $totalImages
				]
			]);
		}

	}
