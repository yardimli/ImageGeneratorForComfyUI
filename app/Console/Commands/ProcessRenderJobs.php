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

	/*
Of course. Here is a comprehensive, step-by-step guide on how to set up and use Supervisor on Ubuntu to run your new Laravel Artisan command as a background process.
Supervisor is the perfect tool for this job. It's a process control system that will:
Start your app:process-render-jobs command automatically when the server boots.
Monitor the command and automatically restart it if it ever crashes.
Provide simple commands to start, stop, and check the status of your process.
Capture the output and error logs from your command.
Step 1: Install Supervisor
First, update your package list and install Supervisor using apt.
code
Bash
sudo apt-get update
sudo apt-get install supervisor
Once installed, the Supervisor service will start automatically. You can verify its status with:
code
Bash
sudo systemctl status supervisor
You should see an active (running) status.
Step 2: Create a Configuration File for Your Worker
Supervisor's configuration files live in /etc/supervisor/conf.d/. You create a new .conf file for each process you want to manage. Let's create one for your image rendering command.
Create a new file named render-worker.conf:
code
Bash
sudo nano /etc/supervisor/conf.d/render-worker.conf
Now, paste the following configuration into the file. You must change the paths and user to match your server's setup.
code
Ini
[program:render-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/your-project-path/artisan app:process-render-jobs
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/your-project-path/storage/logs/render-worker.log
stopwaitsecs=3600
Explanation of Each Line:
[program:render-worker]: This is the name of your program block. You'll use render-worker to manage this process.
process_name=%(program_name)s_%(process_num)02d: A unique name for each process instance. If numprocs is 1, this will be render-worker_00.
command=/usr/bin/php /var/www/your-project-path/artisan app:process-render-jobs: This is the most important line.
/usr/bin/php: Use the full path to your PHP executable. You can find it by running which php.
/var/www/your-project-path/artisan: The absolute path to your Laravel project's artisan file. Replace /var/www/your-project-path with the actual path.
app:process-render-jobs: The signature of the command you created.
autostart=true: Automatically start this process when Supervisor starts (e.g., on server boot).
autorestart=true: Automatically restart the process if it exits unexpectedly (e.g., if it crashes).
user=www-data: Very important for permissions. Run the command as this user. www-data is the standard user for Nginx/Apache on Ubuntu. This ensures your script can write to log files and storage directories without permission errors.
numprocs=1: How many instances of this process to run. For your specific script, 1 is correct.
redirect_stderr=true: Redirect any standard error output (like PHP errors) to the standard output file.
stdout_logfile=/var/www/your-project-path/storage/logs/render-worker.log: The path where all echo, info(), error() output from your command will be logged. Make sure this path is correct and the user (www-data) has permission to write to it.
stopwaitsecs=3600: When stopping the process, wait up to this many seconds for it to finish its current task gracefully before killing it.
Save the file and exit nano (press Ctrl+X, then Y, then Enter).
Step 3: Activate the New Configuration
After creating or changing a configuration file, you need to tell Supervisor to read it and apply the changes. This is a two-step process.
Reread: Tell Supervisor to check the conf.d directory for any new or changed files.
code
Bash
sudo supervisorctl reread
You should see output like: render-worker: available
Update: Tell Supervisor to enact any changes. This will start any new programs.
code
Bash
sudo supervisorctl update
You should see output like: render-worker: added process group
Your worker is now running!
Step 4: Managing Your Worker Process
You can manage your worker using the supervisorctl command.
Check the status of all processes:
code
Bash
sudo supervisorctl status
You should see your worker in the list with a RUNNING status.
code
Code
render-worker:render-worker_00   RUNNING   pid 12345, uptime 0:01:23
Stop the worker:
code
Bash
sudo supervisorctl stop render-worker:*
Start the worker:
code
Bash
sudo supervisorctl start render-worker:*
Restart the worker (useful after deploying new code):
code
Bash
sudo supervisorctl restart render-worker:*
Step 5: Checking the Logs
Since your command runs in the background, you can't see its output directly. The log file you configured is essential for debugging.
To view the log file in real-time, use the tail command with the -f (follow) flag:
code
Bash
tail -f /var/www/your-project-path/storage/logs/render-worker.log
You will now see the output from your command's $this->info() and $this->error() calls as they happen. This is the primary way you will monitor and debug your running process. Press Ctrl+C to stop tailing the log.
You have now successfully converted your Python script to a robust, auto-restarting Laravel background process managed by Supervisor.

	 */
