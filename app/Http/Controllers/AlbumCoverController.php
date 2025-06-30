<?php

	namespace App\Http\Controllers;

	use App\Models\GoodAlbumCover;
	use App\Services\ChatGPTService;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Pagination\Paginator;
	use Illuminate\Pagination\LengthAwarePaginator;
	use Illuminate\Support\Facades\Log;
	use Exception;

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

				// Get counts of liked images for each folder
				$likedCounts = GoodAlbumCover::where('liked', true)
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

			$perPage = 48;
			$currentPage = Paginator::resolveCurrentPage('page');
			$currentPageItems = array_slice($imageFiles, ($currentPage - 1) * $perPage, $perPage);
			$paginator = new LengthAwarePaginator($currentPageItems, count($imageFiles), $perPage, $currentPage, [
				'path' => Paginator::resolveCurrentPath(),
				'pageName' => 'page'
			]);
			$paginator->appends($request->except('page'));

			// Get liked status for the images on the current page
			$likedImages = GoodAlbumCover::whereIn('album_path', $currentPageItems)
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

			foreach ($allImagesOnPage as $path) {
				GoodAlbumCover::updateOrCreate(
					['album_path' => $path],
					['liked' => in_array($path, $likedImages)]
				);
			}

			return response()->json(['success' => true, 'message' => 'Liked images updated successfully.']);
		}

		/**
		 * Display all liked album covers.
		 */
		public function showLiked(Request $request)
		{
			$cloudfrontUrl = rtrim(env('COVERS_AWS_CLOUDFRONT_URL'), '/');
			$likedImages = GoodAlbumCover::where('liked', true)
				->orderBy('updated_at', 'desc')
				->paginate(48);

			// Define the default prompt text here to pass to the view for the modal
			$defaultPromptText = "describe modifications to the cover image to make it different like change the color or objects on the image. Only output 3 changes. Only list the changes like \"make the mans hair long\", \"make the trees taller\" or \"change the background to a city\" etc.. Do not describe the image itself.";

			return view('album-covers.liked', compact('likedImages', 'cloudfrontUrl', 'defaultPromptText'));
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

				$coversToProcess = GoodAlbumCover::whereIn('id', $coverIds)->get();

				if ($coversToProcess->isEmpty()) {
					return response()->json(['success' => false, 'message' => 'No valid covers selected for processing.'], 404);
				}

				$cloudfrontUrl = rtrim(env('COVERS_AWS_CLOUDFRONT_URL'), '/');
				$processedCount = 0;

				foreach ($coversToProcess as $cover) {
					try {
						$imageUrl = $cloudfrontUrl . '/' . $cover->album_path;

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
				Log::error("Failed to generate prompts for album covers: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
			}
		}
	}
