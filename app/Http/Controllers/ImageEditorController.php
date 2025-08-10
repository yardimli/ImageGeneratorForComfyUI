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

			$backgroundUrl = $request->query('bg_url');
			$overlayUrls = $request->query('overlay_urls', []);

			return view('image-editor.index', [
				'return_url' => $returnUrl,
				'background_url' => $backgroundUrl,
				'overlay_urls' => $overlayUrls,
			]);
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
		/**
		 * Fetches an image from an external URL or a data URL, saves it locally, and returns the local URL.
		 * This is used to bypass browser CORS restrictions (tainted canvas).
		 */
		public function proxyImage(Request $request)
		{
			$validated = $request->validate([
				// Accept either a standard URL or a data URL
				'url' => ['required', 'string'],
			]);

			$input = $validated['url'];

			try {
				if ($this->isDataUrl($input)) {
					// Handle data:image/...;base64,....
					[$binary, $mime] = $this->decodeDataUrl($input);
					if (!$binary) {
						return response()->json(['success' => false, 'message' => 'Invalid base64 data URL.'], 422);
					}

					// Verify it is an image and normalize MIME/ext
					[$ok, $mimeVerified] = $this->verifyImageBytes($binary, $mime);
					if (!$ok) {
						return response()->json(['success' => false, 'message' => 'Provided data is not a valid image.'], 422);
					}
					$extension = $this->extensionFromMime($mimeVerified) ?? 'jpg';

					$filename = 'proxied_' . uniqid() . '.' . $extension;
					$tempPath = 'temp/' . $filename;
					Storage::disk('public')->put($tempPath, $binary);

					return response()->json([
						'success'   => true,
						'local_url' => Storage::disk('public')->url($tempPath),
					]);
				}

				// Otherwise treat as HTTP(S) URL
				if (!filter_var($input, FILTER_VALIDATE_URL)) {
					return response()->json(['success' => false, 'message' => 'Invalid URL.'], 422);
				}

				$response = Http::timeout(30)
					->accept('image/avif,image/webp,image/apng,image/*;q=0.8,*/*;q=0.5')
					->get($input);

				if ($response->failed()) {
					return response()->json(['success' => false, 'message' => 'Failed to download image from the provided URL.'], 400);
				}

				$body = $response->body();
				if ($body === '' || $body === null) {
					return response()->json(['success' => false, 'message' => 'Empty response body.'], 400);
				}

				// Verify bytes are an image
				[$ok, $mimeVerified] = $this->verifyImageBytes($body, $response->header('Content-Type'));
				if (!$ok) {
					return response()->json(['success' => false, 'message' => 'Downloaded content is not a valid image.'], 415);
				}

				// Prefer MIME from bytes; fall back to path extension
				$extension = $this->extensionFromMime($mimeVerified);
				if (!$extension) {
					$originalExtension = strtolower(pathinfo(parse_url($input, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
					$extension = in_array($originalExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'apng'], true)
						? ($originalExtension === 'jpeg' ? 'jpg' : $originalExtension)
						: 'jpg';
				}

				$filename = 'proxied_' . uniqid() . '.' . $extension;
				$tempPath = 'temp/' . $filename;
				Storage::disk('public')->put($tempPath, $body);

				return response()->json([
					'success'   => true,
					'local_url' => Storage::disk('public')->url($tempPath),
				]);
			} catch (\Throwable $e) {
				Log::error('Image proxy error for input ' . Str::limit($input, 200) . ': ' . $e->getMessage());
				return response()->json([
					'success' => false,
					'message' => 'An error occurred while processing the image.',
				], 500);
			}
		}

		private function isDataUrl(string $s): bool
		{
			return Str::startsWith($s, 'data:image/');
		}

		/**
		 * @return array{0:string|false,1:string|null} [binary, declared mime]
		 */
		private function decodeDataUrl(string $dataUrl): array
		{
			// Example: data:image/png;base64,AAAA...
			if (!preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $dataUrl, $m)) {
				return [false, null];
			}
			$mime = strtolower($m[1]);
			$binary = base64_decode($m[2], true);
			return [$binary, $mime];
		}

		/**
		 * Verify bytes are an image; prefer MIME detected from bytes.
		 * @return array{0:bool,1:string|null} [ok, normalized mime]
		 */
		private function verifyImageBytes(string $bytes, ?string $hintMime): array
		{
			$info = @getimagesizefromstring($bytes);
			if ($info === false) {
				return [false, null];
			}
			// getimagesizefromstring() returns 'mime' like 'image/png'
			$mimeDetected = isset($info['mime']) ? strtolower($info['mime']) : null;

			// Normalize
			$mime = $mimeDetected ?: ($hintMime ? strtolower(trim(explode(';', $hintMime)[0])) : null);
			// Keep only common/allowed image mimes
			$allowed = [
				'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
				'image/webp', 'image/bmp', 'image/avif', 'image/apng',
			];
			if ($mime && in_array($mime, $allowed, true)) {
				// Normalize jpg
				if ($mime === 'image/jpg') $mime = 'image/jpeg';
				return [true, $mime];
			}
			// If mime not in allowed but getimagesize succeeded, treat as jpeg fallback
			return [true, $mime ?: 'image/jpeg'];
		}

		private function extensionFromMime(?string $mime): ?string
		{
			return match ($mime) {
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
				'image/bmp'  => 'bmp',
				'image/avif' => 'avif',
				'image/apng' => 'png', // save APNG as PNG (keeps first frame)
				default      => null,
			};
		}
	}
