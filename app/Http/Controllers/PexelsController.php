<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Storage;

	class PexelsController extends Controller
	{
		protected $apiKey;

		public function __construct()
		{
			// Store this in your .env file
			$this->apiKey = env('PEXELS_API_KEY');
		}

		public function search(Request $request)
		{
			$query = $request->input('query');
			$page = $request->input('page', 1);
			$perPage = 12;

			try {
				$response = Http::withHeaders([
					'Authorization' => $this->apiKey
				])->get('https://api.pexels.com/v1/search', [
					'query' => $query,
					'per_page' => $perPage,
					'page' => $page
				]);

				return response()->json($response->json());
			} catch (\Exception $e) {
				return response()->json([
					'success' => false,
					'message' => 'Failed to fetch images from Pexels',
					'error' => $e->getMessage()
				], 500);
			}
		}

		public function download(Request $request)
		{
			try {
				$url = $request->input('url');
				$filename = 'pexels_' . time() . '_' . uniqid() . '.jpg';

				// Download the image
				$imageContent = file_get_contents($url);

				// Save to storage
				Storage::put('public/uploads/' . $filename, $imageContent);

				return response()->json([
					'success' => true,
					'path' => asset('storage/uploads/' . $filename),
					'filename' => $filename
				]);
			} catch (\Exception $e) {
				return response()->json([
					'success' => false,
					'message' => 'Failed to download image',
					'error' => $e->getMessage()
				], 500);
			}
		}
	}
