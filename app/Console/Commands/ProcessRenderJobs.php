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
		 * Process a single prompt.
		 */
		private function processPrompt(Prompt $prompt, int $idx): void
		{
			// Define remote models handled by this worker.
			$remoteFalModels = [
				"imagen3" => "fal-ai/imagen4/preview/ultra",
				"aura-flow" => "fal-ai/aura-flow",
				"ideogram-v2a" => "fal-ai/ideogram/v2a",
				"luma-photon" => "fal-ai/luma-photon",
				"recraft-20b" => "fal-ai/recraft-20b",
				"fal-ai/qwen-image" => "fal-ai/qwen-image"
			];
			$remoteOtherModels = ["minimax", "minimax-expand"];

			// Filter for prompts this worker can handle.
			if ($prompt->generation_type !== "prompt" || (!array_key_exists($prompt->model, $remoteFalModels) && !in_array($prompt->model, $remoteOtherModels))) {
				return; // Skip this prompt.
			}

			$this->info("Processing prompt #{$idx} (ID: {$prompt->id}) - model: {$prompt->model} - status: {$prompt->render_status} - user: {$prompt->user_id}");

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

				$imageUrl = null;

				// --- Image Generation Logic ---
				if (array_key_exists($prompt->model, $remoteFalModels)) {
					$imageUrl = $this->generateWithFal($remoteFalModels[$prompt->model], $prompt);
				} elseif (in_array($prompt->model, $remoteOtherModels)) {
					$imageUrl = $this->generateWithMinimax($prompt);
				}

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

		// MODIFICATION START: Replaced the entire method with the correct asynchronous flow.
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
			if ($modelName === 'fal-ai/qwen-image') {
				$arguments['image_size'] = ['width' => $prompt->width, 'height' => $prompt->height];
			}

			try {
				// Step 1: Submit the job to the queue endpoint.
				$submitUrl = "https://queue.fal.run/{$modelName}";
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
				$statusUrl = "https://queue.fal.run/{$modelName}/requests/{$requestId}/status";
				$resultUrl = "https://queue.fal.run/{$modelName}/requests/{$requestId}";
				$startTime = time();

				while (time() - $startTime < $falTimeout) {
					sleep(3); // Poll every 3 seconds.
					$statusResponse = Http::withHeaders(['Authorization' => 'Key ' . $falKey])->get($statusUrl);

					if ($statusResponse->failed()) {
						$this->warn("Fal.run status check failed for {$requestId}, retrying...");
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
						$imageUrl = $resultData['images'][0]['url'] ?? null;

						if (!$imageUrl) {
							$this->error("Fal.run result for {$requestId} did not contain an image URL.");
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
		// MODIFICATION END

		/**
		 * Generate an image using the Minimax API.
		 */
		private function generateWithMinimax(Prompt $prompt): ?string
		{
			$this->info("Sending to Minimax...");
			try {
				$payload = [
					"model" => "image-01",
					"prompt" => $prompt->generated_prompt,
					"aspect_ratio" => $this->getAspectRatio($prompt->width, $prompt->height),
					"response_format" => "url",
					"n" => 1,
					"prompt_optimizer" => ($prompt->model === "minimax-expand")
				];

				$response = Http::withToken(env('MINIMAX_KEY'))
					->timeout(120)
					->post(env('MINIMAX_KEY_URL'), $payload);

				$response->throw(); // Throw an exception for 4xx/5xx responses.

				return $response->json('data.image_urls.0');
			} catch (Throwable $e) {
				$this->error("Error calling Minimax API: " . $e->getMessage());
				report($e);
				return null;
			}
		}

		/**
		 * Find the closest standard aspect ratio string for the Minimax API.
		 */
		private function getAspectRatio(int $width, int $height): string
		{
			$standardRatios = [
				"1:1" => 1.0, "16:9" => 16 / 9, "4:3" => 4 / 3, "3:2" => 3 / 2,
				"2:3" => 2 / 3, "3:4" => 3 / 4, "9:16" => 9 / 16, "21:9" => 21 / 9
			];

			if ($height === 0) {
				return "1:1";
			}

			$actualRatio = $width / $height;
			$closestRatioName = "1:1";
			$smallestDiff = PHP_INT_MAX;

			foreach ($standardRatios as $name => $ratio) {
				$diff = abs($ratio - $actualRatio);
				if ($diff < $smallestDiff) {
					$smallestDiff = $diff;
					$closestRatioName = $name;
				}
			}
			return $closestRatioName;
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
				// MODIFICATION START: Add diagnostic logging before the attempt.
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
				// MODIFICATION END

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