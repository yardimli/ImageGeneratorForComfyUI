<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use App\Models\UserUpscaleSetting; // Import
	use App\Models\UpscaleModel; // Import
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Rolandstarke\Thumbnail\Facades\Thumbnail;

	class UpscaleAndNotesController extends Controller
	{

		public function upscaleImage(Request $request, Prompt $prompt)
		{
			// MODIFICATION START: Fetch user settings
			$userSetting = UserUpscaleSetting::where('user_id', auth()->id())
				->where('is_active', true)
				->with('model')
				->first();

			if ($userSetting) {
				$model = $userSetting->model;
				$input = $userSetting->settings;
				$version = $model->replicate_version_id;

				// Map the image URL to the specific key required by the model (image vs img)
				$input[$model->image_input_key] = $request->filename;
			} else {
				// Fallback to default High Res ControlNet if no settings found
				$version = "4af11083a13ebb9bf97a88d7906ef21cf79d1f2e5fa9d87b70739ce6b8113d29";
				$input = [
					"hdr" => 0.1,
					"image" => $request->filename,
					"prompt" => "4k, enhance, high detail",
					"creativity" => 0.3,
					"guess_mode" => true,
					"resolution" => 2560,
					"resemblance" => 1,
					"guidance_scale" => 5,
					"negative_prompt" => ""
				];
			}
			// MODIFICATION END

			$client = new Client();
			$response = $client->post('https://api.replicate.com/v1/predictions', [
				'headers' => [
					'Authorization' => 'Bearer ' . env('REPLICATE_API_TOKEN'),
					'Content-Type' => 'application/json',
				],
				'json' => [
					"version" => $version,
					"input" => $input
				]
			]);

			$body = $response->getBody();
			$content = $body->getContents();


			$upscale_result = $content ?? '{"result":"Error or no result"}';
			$json_result = json_decode($upscale_result, true);

			if ($upscale_result === '{"result":"Error or no result"}') {
				return response()->json(['message' => 'Error or no result from the upscale API.']);
			}

			// Assuming the response has a result URL or some indication of the upscale result
			$prompt->upscale_result = $upscale_result;
			$prompt->upscale_prediction_id = $json_result['id'] ?? null;
			$prompt->upscale_status_url = $json_result['urls']['get'] ?? null;
			$prompt->upscale_status = 1;
			$prompt->save();

			Log::info($prompt->upscale_result);

			return response()->json(['message' => 'Image upscaled successfully.', 'upscale_result' => $json_result, 'prediction_id' => $json_result['id'] ?? null, 'status_url' => $json_result['urls']['get'] ?? null]);
		}

		// ... rest of the file remains unchanged
		public function checkUpscaleStatusOperation(Prompt $prompt, $prediction_id)
		{
			$response = Http::withHeaders([
				'Authorization' => 'Bearer ' . env('REPLICATE_API_TOKEN'),
				'Content-Type' => 'application/json',
			])->get("https://api.replicate.com/v1/predictions/{$prediction_id}");

			$body = $response->json();
			Log::info($body);

			if ($body['status'] === 'succeeded') {
				if (!isset($body['output'])) { // Changed from checking output[0] to output generally first
					$prompt->upscale_result = json_encode($body);
					$prompt->upscale_status = 3;
					$prompt->save();
					return ['message' => 'Image upscale succeeded but no output URL found.'];
				}

				// Handle different output formats (GFPGAN returns string, ControlNet returns array)
				$upscaledImageUrl = is_array($body['output']) ? $body['output'][0] : $body['output'];

				if (!$upscaledImageUrl) {
					$prompt->upscale_result = json_encode($body);
					$prompt->upscale_status = 3;
					$prompt->save();
					return ['message' => 'Image upscale succeeded but no output URL found.'];
				}

				$imageName = "{$prompt->id}_upscaled.jpg"; // You might want to detect extension based on output
				$storagePath = "public/upscaled/{$imageName}";

				// Download and save the file
				$contents = file_get_contents($upscaledImageUrl);
				Storage::put($storagePath, $contents);

				// Update database with final upscale result and name
				$prompt->upscale_result = json_encode($body);
				$prompt->upscale_url = $imageName; // Assuming you want to save the image name here as well
				$prompt->upscale_status = 2;
				$prompt->save();

				return ['message' => 'Image upscaled successfully.', 'upscale_result' => asset("storage/upscaled/{$imageName}")];
			} else if ($body['status'] === 'failed') {
				return ['message' => 'Image upscale failed.', 'error' => $body['error']];
			}

			// If the status is neither succeeded nor failed, it's still in progress
			return ['message' => 'Upscale in progress.', 'status' => $body['logs']];
		}

		public function checkUpscaleStatus(Request $request, Prompt $prompt, $prediction_id)
		{
			$response = self::checkUpscaleStatusOperation($prompt, $prediction_id);
			return response()->json($response);
		}

		public function updateNotes(Request $request, Prompt $prompt)
		{
			$prompt->notes = $request->notes;
			$prompt->save();

			return response()->json([
				'message' => 'Image details updated successfully.',
				'notes' => $prompt->notes,
			]);
		}
	}
