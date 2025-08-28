<?php

	namespace App\Console\Commands;

	use App\Models\Prompt;
	use Illuminate\Console\Command;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;
	use Throwable;

	/**
	 * This command processes pending image generation prompts by calling external APIs.
	 * It is a PHP-based replacement for the original python/render-jobs.py script.
	 */
	class ProcessRenderJobs extends Command
	{
		/**
		 * The name and signature of the console command.
		 *
		 * @var string
		 */
		protected $signature = 'app:process-render-jobs';

		/**
		 * The console command description.
		 *
		 * @var string
		 */
		protected $description = 'Fetches pending prompts from the database and generates images using remote APIs.';

		/**
		 * Counter to track how long a prompt has been in a processing state to prevent infinite loops.
		 * @var array<int, int>
		 */
		private array $promptStatusCounter = [];

		/**
		 * A list of all available model names from the JSON file.
		 * @var array<string>|null
		 */
		private ?array $availableModels = null;

		/**
		 * Execute the console command.
		 *
		 * @return int
		 */
		public function handle()
		{
			$this->info('Starting render job processor...');

			// This infinite loop mimics the behavior of the original Python script.
			// It should be managed by a process controller like Supervisor.
			while (true) {
				try {
					$this->generateImages();
				} catch (Throwable $e) {
					$this->error('A critical error occurred in the main loop: ' . $e->getMessage());
					report($e); // Log the full exception to the application log.
				}

				// Wait for 5 seconds before the next cycle, same as the Python script.
				sleep(5);
			}
		}

		/**
		 * Fetches and processes pending prompts.
		 */
		private function generateImages(): void
		{
			$this->info('Fetching pending prompts...');

			$statuses = [0, 1, 3]; // 0: pending, 1: processing, 3: retrying
			$promptsPerUser = 3;

			$prompts = Prompt::whereIn('render_status', $statuses)
				->orderBy('id', 'desc')
				->get()
				->groupBy(['render_status', 'user_id']) // Group by status, then by user
				->flatMap(function ($userGroups) use ($promptsPerUser) {
					return $userGroups->flatMap(function ($userPrompts) use ($promptsPerUser) {
						return $userPrompts->take($promptsPerUser);
					});
				});

			if ($prompts->isEmpty()) {
				$this->info('No pending prompts found. Waiting...');
				return;
			}

			foreach ($prompts as $idx => $prompt) {
				$this->processPrompt($prompt, $idx);
			}
		}

		/**
		 * Loads the list of available model names from the models.json file.
		 * Caches the result in a property to avoid repeated file reads.
		 */
		private function loadAvailableModels(): void
		{
			if ($this->availableModels !== null) {
				return;
			}

			try {
				$jsonPath = resource_path('text-to-image-models/models.json');
				if (!file_exists($jsonPath)) {
					$this->error("Model definition file not found at: {$jsonPath}");
					$this->availableModels = []; // Set to empty to prevent re-trying
					return;
				}

				$jsonString = file_get_contents($jsonPath);
				$allModels = json_decode($jsonString, true);

				if (json_last_error() !== JSON_ERROR_NONE) {
					$this->error('Failed to parse models.json: ' . json_last_error_msg());
					$this->availableModels = [];
					return;
				}

				// Extract just the 'name' field from each model entry.
				$this->availableModels = array_column($allModels, 'name');
			} catch (Throwable $e) {
				$this->error('A critical error occurred while loading models.json: ' . $e->getMessage());
				report($e);
				$this->availableModels = [];
			}
		}

		/**
		 * Returns a mapping of short model names (from DB) to full Fal.ai API model names.
		 *
		 * @return array<string, string>
		 */
		private function getModelMapping(): array
		{
			// Mapping from DB short name to the full model identifier for the API
			return [
				'schnell' => 'flux-1/schnell',
				'dev' => 'flux-1/dev',
				'minimax' => 'minimax/image-01',
				'minimax-expand' => 'minimax/image-01',
				'imagen3' => 'imagen4/preview/ultra',
				'aura-flow' => 'aura-flow',
				'ideogram-v2a' => 'ideogram/v2a',
				'luma-photon' => 'luma-photon',
				'recraft-20b' => 'recraft-20b',
				'fal-ai/qwen-image' => 'qwen-image',
			];
		}

		/**
		 * Process a single prompt.
		 */
		private function processPrompt(Prompt $prompt, int $idx): void
		{
			// MODIFICATION START: Load models from JSON and validate against it.
			$this->loadAvailableModels(); // Ensure the model list is loaded.

			$modelMapping = $this->getModelMapping();

			// The model from DB could be a short name ('schnell') or a full API name ('flux-1/schnell').
			// Resolve it to the full API name for validation and API calls.
			$modelName = $modelMapping[$prompt->model] ?? $prompt->model;

			// Filter for prompts this worker can handle.
			$isKnownModel = in_array($modelName, $this->availableModels, true);
			// Allow specific models like the image editor to pass through even if not in models.json
			$isAllowedOverride = in_array($modelName, ['gemini-25-flash-image/edit'], true);

			if ($prompt->generation_type !== "prompt" || !($isKnownModel || $isAllowedOverride)) {
				return; // Skip this prompt.
			}
			// MODIFICATION END

			$this->info("Processing prompt #{$idx} (ID: {$prompt->id}) - model: {$prompt->model} ({$modelName}) - status: {$prompt->render_status} - user: {$prompt->user_id}");

			try {
				$outputFilename = "{$prompt->generation_type}_" . Str::slug($prompt->model, '-') . "_{$prompt->id}_{$prompt->user_id}.png";
				$s3FilePath = "images/{$outputFilename}";
				$localTempPath = sys_get_temp_dir() . '/' . $outputFilename;

				// Handle jobs that might be stuck in a processing state.
				if (in_array($prompt->render_status, [1, 3])) {
					$this->promptStatusCounter[$prompt->id] = ($this->promptStatusCounter[$prompt->id] ?? 0) + 1;
					if ($this->promptStatusCounter[$prompt->id] > 20) {
						$this->warn("Prompt {$prompt->id} has been stuck for too long. Marking as failed.");
						$this->updateRenderStatus($prompt, 4); // 4: failed
						unset($this->promptStatusCounter[$prompt->id]);
					}
					return;
				}

				// Mark as processing (status 1).
				$this->updateRenderStatus($prompt, 1);

				// --- Image Generation Logic ---
				// $modelName is already resolved and validated above.
				$imageUrl = $this->generateWithFal($modelName, $prompt);

				// --- Download, Save, and Upload ---
				if ($imageUrl) {
					if ($this->downloadImage($imageUrl, $localTempPath)) {
						if ($prompt->upload_to_s3) {
							$s3Url = $this->uploadToS3($localTempPath, $s3FilePath);
							if ($s3Url) {
								$this->updateFilename($prompt, $s3Url);
							} else {
								$this->error("S3 upload failed for prompt {$prompt->id}.");
								$this->updateRenderStatus($prompt, 4);
							}
						} else {
							$outputDir = env('OUTPUT_DIR', storage_path('app/public/images'));
							if (!is_dir($outputDir)) {
								mkdir($outputDir, 0755, true);
							}
							$finalLocalPath = $outputDir . '/' . $outputFilename;
							copy($localTempPath, $finalLocalPath);
							$this->updateFilename($prompt, $finalLocalPath);
						}
						@unlink($localTempPath);
					} else {
						$this->error("Failed to download the generated image for prompt {$prompt->id}.");
						$this->updateRenderStatus($prompt, 4);
					}
				} else {
					$this->error("Image generation failed for prompt {$prompt->id}. No URL returned.");
					$this->updateRenderStatus($prompt, 4);
				}

				sleep(6);
			} catch (Throwable $e) {
				$this->error("CRITICAL ERROR processing prompt {$prompt->id}: {$e->getMessage()}");
				report($e);
				$this->updateRenderStatus($prompt, 4);
			}
		}

		/**
		 * Generate an image using the Fal.ai asynchronous queue API.
		 * This method now submits the job, then polls for completion.
		 */
		private function generateWithFal(string $modelName, Prompt $prompt): ?string
		{
			$this->info("Submitting job to Fal queue: {$modelName}...");
			$falTimeout = (int) env('FAL_TIMEOUT', 180);
			$falKey = env('FAL_KEY');

			if (!$falKey) {
				$this->error('FAL_KEY is not set in the .env file.');
				return null;
			}

			$arguments = ['prompt' => $prompt->generated_prompt];
			$arguments['image_size'] = ['width' => $prompt->width ?? 1024, 'height' => $prompt->height ?? 1024];

			// START MODIFICATION: Handle input images for image editing models
			if (!empty($prompt->input_images)) {
				$this->info("Processing " . count($prompt->input_images) . " input images for prompt {$prompt->id}...");
				$imageBase64Urls = [];
				foreach ($prompt->input_images as $imagePath) {
					try {
						$imageData = null;
						if (Str::startsWith($imagePath, ['http://', 'https://'])) {
							$response = Http::timeout(30)->get($imagePath);
							if ($response->successful()) {
								$imageData = $response->body();
							}
						} elseif (Str::startsWith($imagePath, '/storage/')) {
							$localPath = Str::after($imagePath, '/storage/');
							if (Storage::disk('public')->exists($localPath)) {
								$imageData = Storage::disk('public')->get($localPath);
							}
						} elseif (file_exists($imagePath)) { // Check absolute path
							$imageData = file_get_contents($imagePath);
						}

						if ($imageData) {
							$finfo = new \finfo(FILEINFO_MIME_TYPE);
							$mime = $finfo->buffer($imageData);
							$base64 = base64_encode($imageData);
							$imageBase64Urls[] = "data:{$mime};base64,{$base64}";
						} else {
							$this->warn("Could not read input image for prompt {$prompt->id}: {$imagePath}");
						}
					} catch (Throwable $e) {
						$this->warn("Error processing input image for prompt {$prompt->id} at path {$imagePath}: " . $e->getMessage());
						report($e);
					}
				}
				if (!empty($imageBase64Urls)) {
					$arguments['image_urls'] = $imageBase64Urls;
					// Image editing models might not use width/height, but we'll leave it in the payload.
					// The API should ignore unused parameters.
					$this->info("Added " . count($imageBase64Urls) . " base64 images to the request.");
				}
			}
			// END MODIFICATION

			try {
				// Step 1: Submit the job to the queue endpoint.
				$submitUrl = "https://queue.fal.run/fal-ai/{$modelName}";
				$response = Http::withHeaders([
					'Authorization' => 'Key ' . $falKey,
					'Content-Type' => 'application/json',
				])->post($submitUrl, $arguments);

				if ($response->failed()) {
					$this->error("Fal.ai API error on submit: " . $response->body());
					return null;
				}

				$data = $response->json();
				$requestId = $data['request_id'] ?? null;

				if (!$requestId) {
					$this->error("Fal.ai API did not return a request_id: " . $response->body());
					return null;
				}

				$this->info("Job submitted successfully. Request ID: {$requestId}. Polling for result...");

				// Step 2: Poll the status URL until the job is complete or times out.
				// explode $modelName by "/" and use the first part only for the status URL
				if (str_contains($modelName, '/')) {
					$modelName = explode('/', $modelName)[0];
				}

				$statusUrl = "https://queue.fal.run/fal-ai/{$modelName}/requests/{$requestId}/status";
				$resultUrl = "https://queue.fal.run/fal-ai/{$modelName}/requests/{$requestId}";
				$startTime = time();
				$error_405_counter = 0;

				while (time() - $startTime < $falTimeout) {
					sleep(3); // Poll every 3 seconds.
					$statusResponse = Http::withHeaders(['Authorization' => 'Key ' . $falKey])->get($statusUrl);

					if ($statusResponse->failed()) {
						if ($statusResponse->status() === 405) {
							$error_405_counter++;
							if ($error_405_counter >= 5) {
								$this->error("Polling stopped for request {$requestId}. Received multiple 405 Method Not Allowed errors. The job has likely expired.");
								$this->error("Final 405 response: " . $statusResponse->body());
								return null;
							}
						}

						$this->warn("Fal.run status check failed for {$requestId}, retrying...");
						$this->warn($statusResponse->body());
						continue;
					}

					$statusData = $statusResponse->json();
					$jobStatus = $statusData['status'] ?? 'UNKNOWN';

					if ($jobStatus === 'COMPLETED') {
						// Step 3: Fetch the final result.
						$this->info("Job {$requestId} completed. Fetching result...");
						$resultResponse = Http::withHeaders(['Authorization' => 'Key ' . $falKey])->get($resultUrl);

						if ($resultResponse->failed()) {
							$this->error("Fal.run result fetch failed for {$requestId}: " . $resultResponse->body());
							return null;
						}

						$resultData = $resultResponse->json();
						//check for images as array and get the first image url, if that fails check if there's a single image url
						$imageUrl = $resultData['images'][0]['url'] ?? null;
						if (!$imageUrl) {
							$imageUrl = $resultData['image']['url'] ?? null;
						}

						if (!$imageUrl) {
							$this->error("Fal.run result for {$requestId} did not contain an image URL.");
							$this->error($resultResponse->body());
						}
						return $imageUrl;
					}

					if (in_array($jobStatus, ['FAILED', 'ERROR'])) {
						$this->error("Fal.ai job {$requestId} failed with status: {$jobStatus}");
						return null;
					}
					// If status is IN_PROGRESS or IN_QUEUE, the loop will continue.
				}

				$this->error("ERROR: Timeout calling {$modelName} after {$falTimeout} seconds for request {$requestId}.");
				return null;
			} catch (Throwable $e) {
				$this->error("ERROR: An unexpected error occurred calling Fal.ai for {$modelName}: {$e->getMessage()}");
				report($e);
				return null;
			}
		}

		/**
		 * Download an image from a URL to a local path.
		 */
		private function downloadImage(string $url, string $outputPath): bool
		{
			try {
				$response = Http::timeout(60)->get($url);
				if ($response->successful()) {
					file_put_contents($outputPath, $response->body());
					$this->info("Successfully downloaded image from {$url}");
					return true;
				}
				$this->error("Failed to download image from {$url}. Status: " . $response->status());
				return false;
			} catch (Throwable $e) {
				$this->error("Error downloading image from {$url}: {$e->getMessage()}");
				report($e);
				return false;
			}
		}

		/**
		 * Upload a file to S3 using Laravel's built-in Storage facade.
		 */
		private function uploadToS3(string $localFile, string $s3File): ?string
		{
			try {
				$s3Config = config('filesystems.disks.s3');
				$this->info("--- S3 Upload Diagnostics ---");
				$this->info("Attempting to upload to bucket: " . ($s3Config['bucket'] ?? 'NOT SET'));
				$this->info("Using region: " . ($s3Config['region'] ?? 'NOT SET'));
				// Do NOT log the secret key. Just check if it's loaded.
				$this->info("AWS Key Loaded: " . (!empty($s3Config['key']) ? 'Yes' : 'No'));
				$this->info("AWS Secret Loaded: " . (!empty($s3Config['secret']) ? 'Yes' : 'No'));
				$this->info("-----------------------------");

				if (empty($s3Config['bucket']) || empty($s3Config['key']) || empty($s3Config['secret'])) {
					$this->error("S3 configuration is missing from the application config. Please run 'php artisan config:clear'.");
					return null;
				}

				if (!file_exists($localFile) || !is_readable($localFile)) {
					$this->error("Local file does not exist or is not readable at: {$localFile}");
					return null;
				}

				$fileStream = fopen($localFile, 'r');
				if (!$fileStream) {
					$this->error("Failed to open file stream for: {$localFile}");
					return null;
				}

				// The 'public' visibility requires the s3:PutObjectAcl permission.
				$path = Storage::disk('s3')->put($s3File, $fileStream);

				if (is_resource($fileStream)) {
					fclose($fileStream);
				}

				if ($path) {
					$this->info("Successfully uploaded {$localFile} to S3 path: {$s3File}");
					$cdnUrl = env('AWS_CLOUDFRONT_URL');
					if ($cdnUrl) {
						return rtrim($cdnUrl, '/') . '/' . ltrim($s3File, '/');
					}
					return Storage::disk('s3')->url($s3File);
				}

				$this->error("Storage::put returned a falsy value, indicating upload failure.");
				return null;
			} catch (Throwable $e) {
				$this->error("CRITICAL S3 UPLOAD ERROR: {$e->getMessage()}");
				report($e);
				return null;
			}
		}

		/**
		 * Update the prompt record with the final image filename/URL and set status to completed.
		 */
		private function updateFilename(Prompt $prompt, string $filePath): void
		{
			$prompt->filename = $filePath;
			$prompt->render_status = 2; // 2: completed
			$prompt->save();
			$this->info("Updated prompt {$prompt->id} with path {$filePath}");
		}

		/**
		 * Update the render status of a prompt.
		 */
		private function updateRenderStatus(Prompt $prompt, int $status): void
		{
			$prompt->render_status = $status;
			$prompt->save();
			$this->info("Updated render status for prompt {$prompt->id} to {$status}");
		}
	}
