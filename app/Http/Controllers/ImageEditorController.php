<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;
	use Illuminate\Support\Facades\Http;

	/**
	 * Controller to handle the image editor functionality.
	 */
	class ImageEditorController extends Controller
	{
		/**
		 * Display the image editor view.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\View\View
		 */
		public function index(Request $request)
		{
			// Pass the return URL to the view, so it can be submitted back.
			$returnUrl = $request->query('return_url', url('/'));
			return view('image-editor.index', ['return_url' => $returnUrl]);
		}

		/**
		 * Save the edited image from a base64 string.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function save(Request $request)
		{
			try {
				$validated = $request->validate([
					'imageData' => 'required|string',
					'return_url' => 'required|url',
				]);

				$imageData = $validated['imageData'];

				// Decode base64 string.
				if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
					$imageData = substr($imageData, strpos($imageData, ',') + 1);
					$type = strtolower($type[1]); // jpg, png, gif

					if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
						return response()->json(['success' => false, 'message' => 'Invalid image type.'], 400);
					}

					$imageData = str_replace(' ', '+', $imageData);
					$decodedImage = base64_decode($imageData);

					if ($decodedImage === false) {
						return response()->json(['success' => false, 'message' => 'Base64 decode failed.'], 500);
					}
				} else {
					return response()->json(['success' => false, 'message' => 'Did not match data URI with image data.'], 400);
				}

				// Generate a unique filename and save to public storage.
				$filename = 'editor_output_' . time() . '_' . Str::random(5) . '.' . $type;
				$path = 'uploads/' . $filename; // Path within storage/app/public

				Storage::disk('public')->put($path, $decodedImage);

				// Get the full public URL of the saved image.
				$imageUrl = Storage::disk('public')->url($path);

				// Construct the redirect URL with the new image path as a query parameter.
				$returnUrl = $validated['return_url'];
				$redirectUrl = $returnUrl . (parse_url($returnUrl, PHP_URL_QUERY) ? '&' : '?') . 'edited_image_url=' . urlencode($imageUrl);

				return response()->json([
					'success' => true,
					'message' => 'Image saved successfully.',
					'path' => $imageUrl,
					'redirect_url' => $redirectUrl,
				]);
			} catch (\Exception $e) {
				Log::error('Image Editor save error: ' . $e->getMessage());
				return response()->json([
					'success' => false,
					'message' => 'An internal server error occurred.',
				], 500);
			}
		}

		/**
		 * Fetches an image from an external URL, saves it locally, and returns the local URL.
		 * This is used to bypass browser CORS restrictions (tainted canvas).
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function proxyImage(Request $request)
		{
			$validated = $request->validate([
				'url' => 'required|url',
			]);

			$imageUrl = $validated['url'];

			try {
				// Use Laravel's HTTP client to fetch the image. It's more robust than file_get_contents.
				$response = Http::timeout(30)->get($imageUrl);

				if ($response->failed()) {
					return response()->json(['success' => false, 'message' => 'Failed to download image from the provided URL.'], 400);
				}

				// Generate a unique filename.
				$originalExtension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
				$extension = in_array($originalExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? $originalExtension : 'jpg';
				$filename = 'proxied_' . uniqid() . '.' . $extension;

				// Save the image to a temporary public directory.
				$tempPath = 'temp/' . $filename;
				Storage::disk('public')->put($tempPath, $response->body());

				// Get the full public URL of the temporary local image.
				$localUrl = Storage::disk('public')->url($tempPath);

				return response()->json([
					'success' => true,
					'local_url' => $localUrl,
				]);
			} catch (\Exception $e) {
				Log::error('Image proxy error for URL ' . $imageUrl . ': ' . $e->getMessage());
				return response()->json([
					'success' => false,
					'message' => 'An error occurred while processing the image.',
				], 500);
			}
		}
	}
