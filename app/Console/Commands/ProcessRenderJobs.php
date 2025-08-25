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
	 * It is designed to be run on a schedule (e.g., every minute) by the Laravel Cron Scheduler.
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
		 * Execute the console command.
		 *
		 * @return int
		 */
		public function handle()
		{
			$this->info('Starting render job processor...');

			// MODIFICATION START: The command is now designed to run and exit, not loop forever.
			try {
				// First, find and fail any jobs that have been stuck in a processing state for too long.
				$this->handleStuckJobs();

				// Next, process a new batch of pending jobs.
				$this->generateImages();
			} catch (Throwable $e) {
				$this->error('A critical error occurred: ' . $e->getMessage());
				report($e); // Log the full exception to the application log.
				return Command::FAILURE;
			}

			$this->info('Render job processor finished.');
			return Command::SUCCESS;
			// MODIFICATION END
		}

		/**
		 * Finds prompts that are stuck in a processing status and marks them as failed.
		 * This is a stateless way to prevent jobs from being stuck indefinitely.
		 */
		private function handleStuckJobs(): void
		{
			// MODIFICATION START: New method to handle stuck jobs.
			$stuckTime = now()->subMinutes(15); // A job is "stuck" if it's been processing for over 15 minutes.

			$stuckCount = Prompt::whereIn('render_status', [1, 3]) // 1: processing, 3: retrying
			->where('updated_at', '<', $stuckTime)
				->update(['render_status' => 4]); // 4: failed

			if ($stuckCount > 0) {
				$this->warn("Marked {$stuckCount} stuck prompts as failed.");
			}
			// MODIFICATION END
		}


		/**
		 * Fetches and processes pending prompts.
		 */
		private function generateImages(): void
		{
			$this->info('Fetching pending prompts...');

			// MODIFICATION START: The query now only fetches new, pending jobs (status 0).
			// Stuck jobs (status 1, 3) are handled by the handleStuckJobs() method.
			$promptsPerUser = 3;

			$prompts = Prompt::where('render_status', 0) // Only fetch status 0 (pending).
			->orderBy('id', 'desc')
				->get()
				->groupBy('user_id')
				->flatMap(function ($userPrompts) use ($promptsPerUser) {
					return $userPrompts->take($promptsPerUser);
				});
			// MODIFICATION END

			if ($prompts->isEmpty()) {
				$this->info('No pending prompts found.');
				return;
			}

			$this->info("Found {$prompts->count()} prompts to process.");
			foreach ($prompts as $idx => $prompt) {
				$this->processPrompt($prompt, $idx + 1);
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

			$this->info("Processing prompt #{$idx} (ID: {$prompt->id}) - model: {$prompt->model} - user: {$prompt->user_id}");

			try {
				$outputFilename = "{$prompt->generation_type}_" . Str::slug($prompt->model, '-') . "_{$prompt->id}_{$prompt->user_id}.png";
				$s3FilePath = "images/{$outputFilename}";
				$localTempPath = sys_get_temp_dir() . '/' . $outputFilename;

				// MODIFICATION: Removed the stateful stuck job counter logic.

				// Mark as processing (status 1). This also updates the `updated_at` timestamp.
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
							// If not uploading to S3, save to a local directory defined in .env.
							$outputDir = env('OUTPUT_DIR', storage_path('app/public/images'));
							if (!is_dir($outputDir)) {
								mkdir($outputDir, 0755, true);
							}
							$finalLocalPath = $outputDir . '/' . $outputFilename;
							copy($localTempPath, $finalLocalPath);
							$this->updateFilename($prompt, $finalLocalPath);
						}
						// Clean up the temporary file.
						@unlink($localTempPath);
					} else {
						$this->error("Failed to download the generated image for prompt {$prompt->id}.");
						$this->updateRenderStatus($prompt, 4);
					}
				} else {
					$this->error("Image generation failed for prompt {$prompt->id}. No URL returned.");
					$this->updateRenderStatus($prompt, 4);
				}

				// MODIFICATION: Removed sleep(6) as the cron schedule provides the delay.
			} catch (Throwable $e) {
				$this->error("CRITICAL ERROR processing prompt {$prompt->id}: {$e->getMessage()}");
				report($e);
				$this->updateRenderStatus($prompt, 4);
			}
		}

		// ... The rest of the helper methods (generateWithFal, generateWithMinimax, etc.) remain unchanged ...
		// NOTE: I'm omitting the unchanged helper methods for brevity. They should be kept as they were in the previous answer.
		/**
		 * Generate an image using the Fal.ai asynchronous API.
		 * NOTE: Requires a FAL_KEY in your .env file.
		 */
		private function generateWithFal(string $modelName, Prompt $prompt): ?string
		{
			$this->info("Sending to Fal/{$modelName}...");
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
				$response = Http::withHeaders([
					'Authorization' => 'Key ' . $falKey,
					'Content-Type' => 'application/json'
				])->post("https://fal.run/{$modelName}", $arguments);

				$response->throw();
				$responseData = $response->json();
				$requestId = $responseData['request_id'] ?? null;

				if (!$requestId) {
					$this->error("Fal.ai initial request failed to return a request_id.");
					return null;
				}

				$statusUrl = "https://fal.run/requests/{$requestId}/status";
				$resultUrl = "https://fal.run/requests/{$requestId}";

				$startTime = time();
				while (time() - $startTime < $falTimeout) {
					sleep(3);
					$statusResponse = Http::withHeaders(['Authorization' => 'Key ' . $falKey])->get($statusUrl);
					$statusData = $statusResponse->json();

					if (($statusData['status'] ?? 'UNKNOWN') === 'COMPLETED') {
						$resultResponse = Http::withHeaders(['Authorization' => 'Key ' . $falKey])->get($resultUrl);
						$resultData = $resultResponse->json();
						return $resultData['images'][0]['url'] ?? null;
					}

					if (in_array($statusData['status'] ?? 'UNKNOWN', ['FAILED', 'ERROR'])) {
						$this->error("Fal.ai job failed with status: " . $statusData['status']);
						return null;
					}
				}

				$this->error("ERROR: Timeout calling {$modelName} after {$falTimeout} seconds.");
				return null;
			} catch (Throwable $e) {
				$this->error("ERROR: An unexpected error occurred calling Fal.ai for {$modelName}: {$e->getMessage()}");
				report($e);
				return null;
			}
		}

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

				$response->throw();

				return $response->json('data.image_urls.0');
			} catch (Throwable $e) {
				$this->error("Error calling Minimax API: " . $e->getMessage());
				report($e);
				return null;
			}
		}

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

		private function uploadToS3(string $localFile, string $s3File): ?string
		{
			try {
				$path = Storage::disk('s3')->put($s3File, fopen($localFile, 'r'), 'public');
				if ($path) {
					$cdnUrl = env('AWS_CLOUDFRONT_URL');
					if ($cdnUrl) {
						return rtrim($cdnUrl, '/') . '/' . ltrim($s3File, '/');
					}
					return Storage::disk('s3')->url($s3File);
				}
				return null;
			} catch (Throwable $e) {
				$this->error("Error uploading to S3: {$e->getMessage()}");
				report($e);
				return null;
			}
		}

		private function updateFilename(Prompt $prompt, string $filePath): void
		{
			$prompt->filename = $filePath;
			$prompt->render_status = 2;
			$prompt->save();
			$this->info("Updated prompt {$prompt->id} with path {$filePath}");
		}

		private function updateRenderStatus(Prompt $prompt, int $status): void
		{
			$prompt->render_status = $status;
			$prompt->save();
			$this->info("Updated render status for prompt {$prompt->id} to {$status}");
		}
	}
