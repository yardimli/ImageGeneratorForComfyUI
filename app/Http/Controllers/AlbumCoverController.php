<?php

	namespace App\Http\Controllers;

	use App\Models\GoodAlbumCover;
	use App\Services\ChatGPTService;
	use Exception;
	use Illuminate\Http\Request;
	use Illuminate\Pagination\LengthAwarePaginator;
	use Illuminate\Pagination\Paginator;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;

	class AlbumCoverController extends Controller
	{
		/**
		 * NOTE: Ensure you have a disk named 's3_covers' configured in your `config/filesystems.php`
		 * with the credentials from your .env file.
		 *
		 * 's3_covers' => [
		 * 'driver' => 's3',
		 * 'key' => env('COVERS_AWS_ACCESS_KEY_ID'),
		 * 'secret' => env('COVERS_AWS_SECRET_ACCESS_KEY'),
		 * 'region' => env('COVERS_AWS_DEFAULT_REGION'),
		 * 'bucket' => env('COVERS_AWS_BUCKET'),
		 * ],
		 */
		public function index(Request $request)
		{
			$folder = $request->query('folder');
			$s3 = Storage::disk('s3_covers');
			$cloudfrontUrl = rtrim(env('COVERS_AWS_CLOUDFRONT_URL'), '/');

			if (!$folder) {
				// List directories inside 'album-covers'
				$directories = $s3->directories('album-covers');

				// Get counts of liked images for each folder for the current user
				$likedCounts = GoodAlbumCover::where('user_id', auth()->id())
					->where('liked', true)
					->select('album_path')
					->get()
					->groupBy(function ($item) {
						// Extract the folder name from 'folder/image.jpg'
						return explode('/', $item->album_path)[0];
					})
					->map->count();

				return view('album-covers.index', compact('directories', 'likedCounts'));
			}

			// List images in the selected folder with pagination
			$allFiles = $s3->files($folder);

			// Filter for image files
			$imageFiles = array_filter($allFiles, function ($file) {
				return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif']);
			});
			$imageFiles = array_values($imageFiles); // Re-index array

			//remove the 'album-covers/' prefix from the paths
			$imageFiles = array_map(function ($file) use ($folder) {
				return str_replace("album-covers/", '', $file);
			}, $imageFiles);

			$perPage = 96;
			$currentPage = Paginator::resolveCurrentPage('page');
			$currentPageItems = array_slice($imageFiles, ($currentPage - 1) * $perPage, $perPage);

			$paginator = new LengthAwarePaginator($currentPageItems, count($imageFiles), $perPage, $currentPage, [
				'path' => Paginator::resolveCurrentPath(),
				'pageName' => 'page'
			]);
			$paginator->appends($request->except('page'));

			// Get liked status for the images on the current page for the current user
			$likedImages = GoodAlbumCover::where('user_id', auth()->id())
				->whereIn('album_path', $currentPageItems)
				->where('liked', true)
				->pluck('album_path')
				->all();

			return view('album-covers.index', compact('paginator', 'folder', 'cloudfrontUrl', 'likedImages'));
		}

		public function updateLiked(Request $request)
		{
			$request->validate([
				'all_images_on_page' => 'required|array',
				'liked_images' => 'nullable|array',
			]);

			$allImagesOnPage = $request->input('all_images_on_page', []);
			$likedImages = $request->input('liked_images', []);
			$userId = auth()->id();

			foreach ($allImagesOnPage as $path) {
				GoodAlbumCover::updateOrCreate(
					['album_path' => $path, 'user_id' => $userId],
					[
						'liked' => in_array($path, $likedImages),
						'image_source' => 's3' // These are always from S3 browsing
					]
				);
			}

			return response()->json(['success' => true, 'message' => 'Liked images updated successfully.']);
		}

		/**
		 * Display all liked album covers for the current user.
		 */
		public function showLiked(Request $request)
		{
			$cloudfrontUrl = rtrim(env('COVERS_AWS_CLOUDFRONT_URL'), '/');

			// Handle sorting
			$sort = $request->query('sort', 'updated_at'); // Default to updated_at
			if (!in_array($sort, ['created_at', 'updated_at'])) {
				$sort = 'updated_at'; // Fallback to default if invalid
			}

			$likedImages = GoodAlbumCover::where('user_id', auth()->id())
				->where('liked', true)
				->orderBy($sort, 'desc') // Use the dynamic sort column
				->paginate(48);

			$likedImages->appends($request->except('page')); // Append all query params to pagination links

			// Define the default prompt text here to pass to the view for the modal
			$defaultPromptText = "describe modifications to the cover image to make it different like change the color or objects on the image. Only output 3 changes. Only list the changes like \"make the mans hair long\", \"make the trees taller\" or \"change the background to a city\" etc.. Do not describe the image itself.";

			return view('album-covers.liked', compact('likedImages', 'cloudfrontUrl', 'defaultPromptText', 'sort'));
		}

		/**
		 * Handle user-uploaded album covers.
		 */
		public function upload(Request $request)
		{
			$request->validate([
				'cover_file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480', // 20MB max
			]);

			try {
				$file = $request->file('cover_file');
				$userId = auth()->id();

				// Store in 'public/uploaded_covers' which links to 'storage/app/public/uploaded_covers'
				$path = $file->store('public/uploaded_covers');

				// Get path relative to the storage disk's public root
				$storagePath = str_replace('public/', '', $path);

				GoodAlbumCover::create([
					'user_id' => $userId,
					'album_path' => $storagePath,
					'image_source' => 'upload',
					'liked' => true,
				]);

				return response()->json(['success' => true, 'message' => 'Cover uploaded successfully.']);
			} catch (Exception $e) {
				Log::error("Failed to upload album cover: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred during upload.'], 500);
			}
		}

		/**
		 * Generate prompts for liked album covers using OpenAI Vision.
		 */
		public function generatePrompts(Request $request, ChatGPTService $chatGPTService)
		{
			$request->validate([
				'cover_ids' => 'required|array|min:1',
				'cover_ids.*' => 'exists:goodalbumcovers,id',
				'prompt_text' => 'required|string|max:1000',
			]);

			try {
				$coverIds = $request->input('cover_ids');
				$promptText = $request->input('prompt_text');

				// Ensure user owns the covers they are trying to process
				$coversToProcess = GoodAlbumCover::where('user_id', auth()->id())
					->whereIn('id', $coverIds)->get();

				if ($coversToProcess->isEmpty()) {
					return response()->json(['success' => false, 'message' => 'No valid covers selected for processing.'], 404);
				}

				$cloudfrontUrl = rtrim(env('COVERS_AWS_CLOUDFRONT_URL'), '/');
				$processedCount = 0;

				foreach ($coversToProcess as $cover) {
					try {
						// Dynamically determine image URL based on source
						$imageUrl = $cover->image_source === 's3'
							? $cloudfrontUrl . '/' . $cover->album_path
							: asset(Storage::url($cover->album_path));

						// Download image
						$imageData = @file_get_contents($imageUrl);
						if ($imageData === false) {
							Log::warning("Failed to download image for cover ID {$cover->id}: {$imageUrl}");
							continue;
						}

						// Get image mime type
						$finfo = new \finfo(FILEINFO_MIME_TYPE);
						$mimeType = $finfo->buffer($imageData);

						// Resize image to 512x512
						$sourceImage = imagecreatefromstring($imageData);
						if (!$sourceImage) {
							Log::warning("Failed to create image from data for cover ID {$cover->id}");
							continue;
						}

						$resizedImage = imagescale($sourceImage, 512, 512);
						ob_start();
						switch ($mimeType) {
							case 'image/png':
								imagepng($resizedImage);
								break;
							case 'image/webp':
								imagewebp($resizedImage);
								break;
							default:
								imagejpeg($resizedImage);
								break;
						}
						$resizedImageData = ob_get_clean();
						imagedestroy($sourceImage);
						imagedestroy($resizedImage);

						// Base64 encode
						$base64Image = base64_encode($resizedImageData);

						// Call OpenAI
						$generatedPrompt = $chatGPTService->generatePromptFromImage($promptText, $base64Image, $mimeType);

						// Update DB
						$cover->update(['mix_prompt' => $generatedPrompt]);
						$processedCount++;
					} catch (Exception $e) {
						Log::error("Error processing cover ID {$cover->id}: " . $e->getMessage());
						// Continue to the next image
						continue;
					}
				}

				return response()->json(['success' => true, 'message' => "Successfully generated prompts for {$processedCount} covers."]);
			} catch (Exception $e) {
				Log::error("Failed to generate prompts for covers: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
			}
		}

		public function generateKontext(Request $request)
		{
			$request->validate([
				'cover_id' => 'required|exists:goodalbumcovers,id',
				'model_type' => 'required|in:dev,pro,max',
			]);

			$cover = GoodAlbumCover::where('id', $request->input('cover_id'))
				->where('user_id', auth()->id())
				->firstOrFail();

			$cloudfrontUrl = rtrim(env('COVERS_AWS_CLOUDFRONT_URL'), '/');

			if (empty($cover->mix_prompt)) {
				return response()->json(['success' => false, 'message' => 'This cover does not have a mix prompt.'], 422);
			}

			if ($cover->kontext_path) {
				Storage::disk('public')->delete($cover->kontext_path);
				$cover->kontext_path = null;
				$cover->save();
			}

			$modelEndpoints = [
				'dev' => 'fal-ai/flux-kontext/dev',
				'pro' => 'fal-ai/flux-pro/kontext',
				'max' => 'fal-ai/flux-pro/kontext/max',
			];
			$endpoint = 'https://queue.fal.run/' . $modelEndpoints[$request->input('model_type')];

			$apiKey = env('FAL_KEY');
			if (!$apiKey) {
				Log::error('FAL_KEY is not set in the environment file.');
				return response()->json(['success' => false, 'message' => 'Server configuration error.'], 500);
			}

			try {
				// Dynamically determine image URL based on source
				$imageUrl = $cover->image_source === 's3'
					? $cloudfrontUrl . '/' . $cover->album_path
					: asset(Storage::url($cover->album_path));

				$response = Http::withHeaders([
					'Authorization' => "Key {$apiKey}",
					'Content-Type' => 'application/json',
				])->post($endpoint, [
					'prompt' => "Remove the texts, " . $cover->mix_prompt,
					'image_url' => $imageUrl,
					'safety_tolerance' => 5,
				]);

				if ($response->failed()) {
					Log::error('Fal.run API error on submit: ' . $response->body());
					return response()->json(['success' => false, 'message' => 'Failed to submit job to the API.'], 502);
				}

				$data = $response->json();
				$requestId = $data['request_id'] ?? null;

				if (!$requestId) {
					Log::error('Fal.run API did not return a request_id: ' . $response->body());
					return response()->json(['success' => false, 'message' => 'API did not return a request ID.'], 502);
				}

				return response()->json(['success' => true, 'request_id' => $requestId]);
			} catch (Exception $e) {
				Log::error('Exception during Fal.run API call: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
			}
		}

		public function checkKontextStatus(Request $request)
		{
			$request->validate([
				'request_id' => 'required|string',
				'model_type' => 'required|in:dev,pro,max',
				'cover_id' => 'required|exists:goodalbumcovers,id',
			]);

			$requestId = $request->input('request_id');
			$modelType = $request->input('model_type');
			$coverId = $request->input('cover_id');

			// Security check: ensure the cover belongs to the user
			$cover = GoodAlbumCover::where('id', $coverId)
				->where('user_id', auth()->id())
				->first();

			if (!$cover) {
				return response()->json(['status' => 'error', 'message' => 'Cover not found or access denied.'], 404);
			}

			$modelBasePaths = [
				'dev' => 'fal-ai/flux-kontext',
				'pro' => 'fal-ai/flux-pro',
				'max' => 'fal-ai/flux-pro', // max uses the same base path as pro
			];
			$basePath = $modelBasePaths[$modelType];
			$statusUrl = "https://queue.fal.run/{$basePath}/requests/{$requestId}/status";
			$resultUrl = "https://queue.fal.run/{$basePath}/requests/{$requestId}";
			$apiKey = env('FAL_KEY');

			try {
				// Check status first
				$statusResponse = Http::withHeaders(['Authorization' => "Key {$apiKey}"])->get($statusUrl);

				if ($statusResponse->failed()) {
					Log::error("Fal.run status check failed for {$requestId}: " . $statusResponse->body());
					return response()->json(['status' => 'error', 'message' => 'Failed to get job status.']);
				}

				$statusData = $statusResponse->json();
				$jobStatus = $statusData['status'] ?? 'UNKNOWN';

				if ($jobStatus === 'COMPLETED') {
					// ** THE FIX IS HERE **
					// If we already have a path, the job was processed by a previous poll.
					// Just return the existing data and stop to prevent re-downloading.
					if ($cover && $cover->kontext_path) {
						return response()->json([
							'status' => 'completed',
							'image_url' => Storage::url($cover->kontext_path)
						]);
					}

					// Fetch the final result
					$resultResponse = Http::withHeaders(['Authorization' => "Key {$apiKey}"])->get($resultUrl);

					if ($resultResponse->failed()) {
						Log::error("Fal.run result fetch failed for {$requestId}: " . $resultResponse->body());
						return response()->json(['status' => 'error', 'message' => 'Failed to fetch completed job result.']);
					}

					$resultData = $resultResponse->json();
					$imageUrl = $resultData['images'][0]['url'] ?? null;

					if (!$imageUrl) {
						Log::error("Fal.run result for {$requestId} did not contain an image URL: " . $resultResponse->body());
						return response()->json(['status' => 'error', 'message' => 'Job completed but no image URL found.']);
					}

					// Download and store the image
					$imageContents = @file_get_contents($imageUrl);
					if ($imageContents === false) {
						Log::error("Failed to download image from {$imageUrl}");
						return response()->json(['status' => 'error', 'message' => 'Failed to download the generated image.']);
					}

					$filename = 'kontext_' . $coverId . '_' . Str::random(10) . '.jpg';
					$storagePath = 'public/kontext/' . $filename;
					Storage::put($storagePath, $imageContents);

					// Update the database
					$cover->kontext_path = 'kontext/' . $filename;
					$cover->save();

					return response()->json([
						'status' => 'completed',
						'image_url' => Storage::url($cover->kontext_path)
					]);
				} elseif (in_array($jobStatus, ['IN_PROGRESS', 'IN_QUEUE'])) {
					return response()->json(['status' => 'processing']);
				} else {
					Log::warning("Fal.run job {$requestId} has unhandled status '{$jobStatus}': " . $statusResponse->body());
					return response()->json(['status' => 'error', 'message' => "Job failed or has an unknown status: {$jobStatus}"]);
				}
			} catch (Exception $e) {
				Log::error("Exception checking Fal.run status for {$requestId}: " . $e->getMessage());
				return response()->json(['status' => 'error', 'message' => 'An unexpected server error occurred.']);
			}
		}

		public function updateMixPrompt(Request $request, GoodAlbumCover $cover)
		{
			if ($cover->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$request->validate([
				'prompt_text' => 'required|string|max:2000',
			]);

			try {
				$cover->update([
					'mix_prompt' => $request->input('prompt_text')
				]);
				return response()->json(['success' => true, 'message' => 'Prompt updated successfully.']);
			} catch (Exception $e) {
				Log::error("Failed to update mix_prompt for cover ID {$cover->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
			}
		}

		public function updateNotes(Request $request, GoodAlbumCover $cover)
		{
			if ($cover->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$request->validate([
				'notes' => 'nullable|string|max:5000',
			]);

			try {
				$cover->update([
					'notes' => $request->input('notes')
				]);
				return response()->json(['success' => true, 'message' => 'Notes updated successfully.']);
			} catch (Exception $e) {
				Log::error("Failed to update notes for cover ID {$cover->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
			}
		}

		public function upscaleCover(Request $request, GoodAlbumCover $cover)
		{
			if ($cover->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			// VALIDATION: Ensure there is a Kontext image to upscale.
			if (!$cover->kontext_path) {
				return response()->json(['success' => false, 'message' => 'A Kontext image must be generated before upscaling.'], 422);
			}

			// Use the generated Kontext image as the source for upscaling.
			// We need a full, public URL for the Replicate API.
			$imageUrl = asset(Storage::url($cover->kontext_path));

			try {
				$response = Http::withHeaders([
					'Authorization' => 'Bearer ' . env('REPLICATE_API_TOKEN'),
					'Content-Type' => 'application/json',
				])->post('https://api.replicate.com/v1/predictions', [
					"version" => "4af11083a13ebb9bf97a88d7906ef21cf79d1f2e5fa9d87b70739ce6b8113d29",
					"input" => [
						"hdr" => 0.1,
						"image" => $imageUrl,
						"prompt" => "4k, enhance, high detail",
						"creativity" => 0.3,
						"guess_mode" => true,
						"resolution" => 2560,
						"resemblance" => 1,
						"guidance_scale" => 5,
						"negative_prompt" => ""
					]
				]);

				if ($response->failed()) {
					Log::error('Replicate API error on upscale submit: ' . $response->body());
					return response()->json(['success' => false, 'message' => 'Error starting upscale process.'], 502);
				}

				$json_result = $response->json();

				if (isset($json_result['id'])) {
					$cover->upscale_prediction_id = $json_result['id'];
					$cover->upscale_status_url = $json_result['urls']['get'] ?? null;
					$cover->upscale_status = 1; // In progress
					$cover->save();

					return response()->json([
						'success' => true,
						'message' => 'Upscale process started.',
						'prediction_id' => $json_result['id'],
						'status_url' => $json_result['urls']['get'] ?? null
					]);
				} else {
					Log::error('Replicate API did not return an ID: ' . $response->body());
					return response()->json(['success' => false, 'message' => 'Error starting upscale process.'], 502);
				}
			} catch (Exception $e) {
				Log::error("Exception during cover upscale for ID {$cover->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
			}
		}

		public function checkUpscaleStatus(Request $request, GoodAlbumCover $cover, $prediction_id)
		{
			if ($cover->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			if (!$cover->upscale_status_url) {
				return response()->json(['status' => 'error', 'message' => 'No status URL found for this job.']);
			}

			try {
				$response = Http::withHeaders([
					'Authorization' => 'Bearer ' . env('REPLICATE_API_TOKEN'),
					'Content-Type' => 'application/json',
				])->get($cover->upscale_status_url);

				if ($response->failed()) {
					Log::error("Replicate status check failed for {$prediction_id}: " . $response->body());
					return response()->json(['status' => 'error', 'message' => 'Failed to get job status.']);
				}

				$body = $response->json();
				$jobStatus = $body['status'] ?? 'unknown';

				if ($jobStatus === 'succeeded') {
					if (empty($body['output'][0])) {
						Log::warning("Replicate job {$prediction_id} succeeded but no output URL found: " . $response->body());
						$cover->upscale_status = 3; // Failed
						$cover->save();
						return response()->json(['status' => 'error', 'message' => 'Upscale succeeded but no output URL found.']);
					}

					$upscaledImageUrl = $body['output'][0];
					$imageContents = @file_get_contents($upscaledImageUrl);

					if ($imageContents === false) {
						Log::error("Failed to download upscaled image from {$upscaledImageUrl}");
						return response()->json(['status' => 'error', 'message' => 'Failed to download the generated image.']);
					}

					// The path for the final upscaled image.
					$filename = 'upscaled_kontext_' . $cover->id . '_' . Str::random(10) . '.png';
					$storagePath = 'public/album-covers/upscaled/' . $filename;
					Storage::put($storagePath, $imageContents);

					// We use the 'upscaled_path' field to store the final result.
					$cover->upscaled_path = 'album-covers/upscaled/' . $filename;
					$cover->upscale_status = 2; // Completed
					$cover->save();

					return response()->json([
						'status' => 'completed',
						'image_url' => Storage::url($cover->upscaled_path)
					]);
				} elseif ($jobStatus === 'failed' || $jobStatus === 'canceled') {
					Log::error("Replicate job {$prediction_id} failed: " . ($body['error'] ?? 'Unknown error'));
					$cover->upscale_status = 3; // Failed
					$cover->save();
					return response()->json(['status' => 'error', 'message' => "Job failed: " . ($body['error'] ?? 'Unknown error')]);
				} else {
					// Still processing (in_progress, starting, etc.)
					return response()->json(['status' => 'processing']);
				}
			} catch (Exception $e) {
				Log::error("Exception checking Replicate status for {$prediction_id}: " . $e->getMessage());
				return response()->json(['status' => 'error', 'message' => 'An unexpected server error occurred.']);
			}
		}
	}
