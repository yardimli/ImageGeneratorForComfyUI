<?php

namespace App\Services;

use App\Models\Prompt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RenderJobService
{
    private const WORKER_LOCK = 'render-queue.ajax-worker.v1';

    private const FAIRNESS_KEY = 'render-queue.fairness.v1';

    private const JOBS_PER_USER = 3;

    private const LOCK_GRACE_SECONDS = 600;

    private const RESULT_WAIT_TIMEOUT_SECONDS = 30;

    /**
     * Claim and render one job from the shared queue.
     *
     * @return array<string, mixed>
     */
    public function processNext(): array
    {
        $timeout = self::RESULT_WAIT_TIMEOUT_SECONDS;
        $lock = Cache::lock(self::WORKER_LOCK, $timeout + self::LOCK_GRACE_SECONDS);

        if (! $lock->get()) {
            return ['state' => 'busy', 'message' => 'Another browser is already rendering a job.'];
        }

        try {
            ignore_user_abort(true);
            @set_time_limit($timeout + self::LOCK_GRACE_SECONDS);
            $this->recoverStaleJobs();
            $prompt = $this->claimNextPrompt();

            if (! $prompt) {
                return ['state' => 'idle', 'message' => 'The render queue is empty.'];
            }

            $this->render($prompt);
            $prompt->refresh();

            return [
                'state' => $prompt->render_status === 2 ? 'completed' : 'failed',
                'job' => $this->presentJob($prompt),
            ];
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $this->recoverStaleJobs();

        $baseQuery = Prompt::query()->where('generation_type', 'prompt');
        $counts = [
            'queued' => (clone $baseQuery)->where('render_status', 0)->count(),
            'processing' => (clone $baseQuery)->where('render_status', 1)->count(),
            'retrying' => (clone $baseQuery)->where('render_status', 3)->count(),
            'failed' => (clone $baseQuery)->where('render_status', 4)->count(),
        ];

        $jobs = (clone $baseQuery)
            ->whereIn('render_status', [0, 1, 3])
            ->orderByRaw('CASE WHEN render_status = 1 THEN 0 WHEN render_status = 3 THEN 1 ELSE 2 END')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Prompt $prompt) => $this->presentJob($prompt))
            ->values()
            ->all();

        $workerProbe = Cache::lock(self::WORKER_LOCK, 1);
        $workerBusy = ! $workerProbe->get();
        if (! $workerBusy) {
            $workerProbe->release();
        }

        return [
            'counts' => $counts,
            'active_count' => $counts['queued'] + $counts['processing'] + $counts['retrying'],
            'jobs' => $jobs,
            'worker_busy' => $workerBusy,
        ];
    }

    /** @return array<string, mixed> */
    public function cancel(Prompt $prompt): array
    {
        if ($prompt->generation_type !== 'prompt' || (int) $prompt->render_status !== 1) {
            return [
                'state' => 'not_cancelled',
                'message' => 'This render job is no longer processing.',
                'job' => $this->presentJob($prompt),
            ];
        }

        $cancelled = Prompt::query()
            ->whereKey($prompt->id)
            ->where('generation_type', 'prompt')
            ->where('render_status', 1)
            ->update(['render_status' => 4]);

        $prompt->refresh();

        return [
            'state' => $cancelled === 1 ? 'cancelled' : 'not_cancelled',
            'message' => $cancelled === 1
                ? 'The current render job was cancelled.'
                : 'This render job is no longer processing.',
            'job' => $this->presentJob($prompt),
        ];
    }

    private function recoverStaleJobs(): void
    {
        Prompt::query()
            ->where('generation_type', 'prompt')
            ->where('render_status', 1)
            ->where('updated_at', '<=', now()->subSeconds(self::RESULT_WAIT_TIMEOUT_SECONDS))
            ->update(['render_status' => 4]);
    }

    private function claimNextPrompt(): ?Prompt
    {
        return DB::transaction(function () {
            $eligible = Prompt::query()
                ->where('generation_type', 'prompt')
                ->whereIn('render_status', [0, 3])
                ->whereNotNull('model');

            $userIds = (clone $eligible)
                ->select('user_id')
                ->distinct()
                ->orderBy('user_id')
                ->pluck('user_id')
                ->values();

            if ($userIds->isEmpty()) {
                return null;
            }

            $fairness = Cache::get(self::FAIRNESS_KEY, ['user_id' => null, 'count' => 0]);
            $lastUserId = $fairness['user_id'] ?? null;
            $burstCount = (int) ($fairness['count'] ?? 0);
            $chosenUserId = $lastUserId;

            if (! $userIds->contains($lastUserId) || $burstCount >= self::JOBS_PER_USER) {
                $lastIndex = $userIds->search($lastUserId);
                $chosenUserId = $lastIndex === false
                    ? $userIds->first()
                    : $userIds[($lastIndex + 1) % $userIds->count()];
                $burstCount = 0;
            }

            $prompt = (clone $eligible)
                ->where('user_id', $chosenUserId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $prompt) {
                return null;
            }

            $prompt->render_status = 1;
            $prompt->save();

            Cache::forever(self::FAIRNESS_KEY, [
                'user_id' => $chosenUserId,
                'count' => $burstCount + 1,
            ]);

            return $prompt;
        }, 3);
    }

    private function render(Prompt $prompt): void
    {
        $modelName = $prompt->model;
        $outputFilename = "{$prompt->generation_type}_".Str::slug($modelName, '-')."_{$prompt->id}_{$prompt->user_id}.png";
        $s3FilePath = "images/{$outputFilename}";
        $localTempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$outputFilename;

        try {
            $falKey = $this->falKeyFor($prompt);
            if (! $falKey) {
                throw new \RuntimeException('FAL_API_KEY or the story-specific fal.ai key is not configured.');
            }

            $imageUrl = $this->generateWithFal($falKey, $modelName, $prompt);
            if ($this->jobWasCancelledOrFailed($prompt)) {
                return;
            }

            if (! $imageUrl || ! $this->downloadImage($imageUrl, $localTempPath)) {
                throw new \RuntimeException('fal.ai did not return a downloadable image.');
            }

            if ($this->jobWasCancelledOrFailed($prompt)) {
                return;
            }

            if ($prompt->upload_to_s3) {
                $filePath = $this->uploadToS3($localTempPath, $s3FilePath);
                if (! $filePath) {
                    throw new \RuntimeException('The generated image could not be uploaded to S3.');
                }
            } else {
                $outputDirectory = env('OUTPUT_DIR', storage_path('app/public/images'));
                if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
                    throw new \RuntimeException('The local image output directory could not be created.');
                }

                $filePath = rtrim($outputDirectory, '\\/').DIRECTORY_SEPARATOR.$outputFilename;
                if (! copy($localTempPath, $filePath)) {
                    throw new \RuntimeException('The generated image could not be saved locally.');
                }
            }

            $prompt->forceFill([
                'filename' => $filePath,
                'render_status' => 2,
            ])->save();
        } catch (Throwable $exception) {
            Log::error('AJAX render job failed.', [
                'prompt_id' => $prompt->id,
                'model' => $modelName,
                'message' => $exception->getMessage(),
            ]);
            report($exception);
            $prompt->forceFill(['render_status' => 4])->save();
        } finally {
            if (is_file($localTempPath)) {
                @unlink($localTempPath);
            }
        }
    }

    private function falKeyFor(Prompt $prompt): ?string
    {
        $usesStoryKey = (int) $prompt->story_page_id !== 0
            || $prompt->story_character_id !== null
            || $prompt->story_place_id !== null
            || $prompt->prompt_dictionary_entry_id !== null;

        if ($usesStoryKey) {
            return env('FAL_KEY_FOR_STORY') ?: config('services.fal.api_key');
        }

        return config('services.fal.api_key');
    }

    private function generateWithFal(string $falKey, string $modelName, Prompt $prompt): ?string
    {
        $deadline = microtime(true) + self::RESULT_WAIT_TIMEOUT_SECONDS;
        $arguments = [
            'prompt' => $prompt->generated_prompt,
            'image_size' => [
                'width' => $prompt->width ?? 1024,
                'height' => $prompt->height ?? 1024,
            ],
        ];

        $imageReferences = $this->prepareInputImages($prompt);
        if ($imageReferences !== []) {
            $arguments['image_urls'] = $imageReferences;
        }

        $queueEndpoint = Str::startsWith($modelName, 'fal-ai/') ? $modelName : "fal-ai/{$modelName}";
        $submitUrl = "https://queue.fal.run/{$queueEndpoint}";
        $headers = ['Authorization' => 'Key '.$falKey];

        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->timeout(self::RESULT_WAIT_TIMEOUT_SECONDS)
            ->post($submitUrl, $arguments);

        if (microtime(true) >= $deadline || $response->failed()) {
            Log::warning('fal.ai render submission failed.', [
                'prompt_id' => $prompt->id,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            return null;
        }

        $data = $response->json();
        $requestId = $data['request_id'] ?? null;
        if (! $requestId) {
            return null;
        }

        $statusUrl = $data['status_url'] ?? "{$submitUrl}/requests/{$requestId}/status";
        $resultUrl = $data['response_url'] ?? "{$submitUrl}/requests/{$requestId}";
        $methodNotAllowedCount = 0;

        while (microtime(true) < $deadline) {
            if ($this->jobWasCancelledOrFailed($prompt)) {
                return null;
            }

            $remainingBeforePoll = $deadline - microtime(true);
            usleep((int) (min(3, max(0, $remainingBeforePoll)) * 1_000_000));
            if (microtime(true) >= $deadline) {
                return null;
            }

            $requestTimeout = max(1, min(30, (int) ceil($deadline - microtime(true))));
            $statusResponse = Http::withHeaders($headers)->timeout($requestTimeout)->get($statusUrl);

            if (microtime(true) >= $deadline || $this->jobWasCancelledOrFailed($prompt)) {
                return null;
            }

            if ($statusResponse->failed()) {
                if ($statusResponse->status() === 405 && ++$methodNotAllowedCount >= 5) {
                    return null;
                }
                continue;
            }

            $jobStatus = $statusResponse->json('status', 'UNKNOWN');
            if ($jobStatus === 'COMPLETED') {
                $resultResponse = Http::withHeaders($headers)->timeout(30)->get($resultUrl);
                if ($resultResponse->failed()) {
                    return null;
                }

                return $resultResponse->json('images.0.url')
                    ?? $resultResponse->json('image.url');
            }

            if (in_array($jobStatus, ['FAILED', 'ERROR'], true)) {
                return null;
            }
        }

        return null;
    }

    private function jobWasCancelledOrFailed(Prompt $prompt): bool
    {
        $prompt->refresh();

        return (int) $prompt->render_status === 4;
    }

    /** @return array<int, string> */
    private function prepareInputImages(Prompt $prompt): array
    {
        if (empty($prompt->input_images)) {
            return [];
        }

        $format = env('FAL_INPUT_IMAGE_FORMAT', 'base64');
        $references = [];

        foreach ($prompt->input_images as $imagePath) {
            try {
                if ($format === 'url') {
                    if (Str::startsWith($imagePath, ['http://', 'https://'])) {
                        $references[] = $imagePath;
                    } elseif (Str::startsWith($imagePath, '/storage/')) {
                        $references[] = url($imagePath);
                    }
                    continue;
                }

                $imageData = $this->readImage($imagePath);
                if ($imageData === null) {
                    continue;
                }

                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($imageData);
                $references[] = "data:{$mime};base64,".base64_encode($imageData);
            } catch (Throwable $exception) {
                Log::warning('Could not prepare a render input image.', [
                    'prompt_id' => $prompt->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $references;
    }

    private function readImage(string $imagePath): ?string
    {
        if (Str::startsWith($imagePath, ['http://', 'https://'])) {
            $response = Http::timeout(30)->get($imagePath);

            return $response->successful() ? $response->body() : null;
        }

        if (Str::startsWith($imagePath, '/storage/')) {
            $localPath = Str::after($imagePath, '/storage/');

            return Storage::disk('public')->exists($localPath)
                ? Storage::disk('public')->get($localPath)
                : null;
        }

        return is_file($imagePath) ? file_get_contents($imagePath) : null;
    }

    private function downloadImage(string $url, string $outputPath): bool
    {
        $response = Http::timeout(60)->get($url);

        return $response->successful()
            && file_put_contents($outputPath, $response->body()) !== false;
    }

    private function uploadToS3(string $localFile, string $s3File): ?string
    {
        $s3Config = config('filesystems.disks.s3');
        if (empty($s3Config['bucket']) || empty($s3Config['key']) || empty($s3Config['secret'])) {
            return null;
        }

        $fileStream = fopen($localFile, 'r');
        if (! $fileStream) {
            return null;
        }

        try {
            if (! Storage::disk('s3')->put($s3File, $fileStream)) {
                return null;
            }
        } finally {
            fclose($fileStream);
        }

        $cdnUrl = env('AWS_CLOUDFRONT_URL');

        return $cdnUrl
            ? rtrim($cdnUrl, '/').'/'.ltrim($s3File, '/')
            : Storage::disk('s3')->url($s3File);
    }

    /** @return array<string, mixed> */
    private function presentJob(Prompt $prompt): array
    {
        return [
            'id' => $prompt->id,
            'user_id' => $prompt->user_id,
            'model' => $prompt->model,
            'status' => match ((int) $prompt->render_status) {
                0 => 'queued',
                1 => 'processing',
                2 => 'completed',
                3 => 'retrying',
                4 => 'failed',
                default => 'unknown',
            },
            'created_at' => optional($prompt->created_at)->toIso8601String(),
            'updated_at' => optional($prompt->updated_at)->toIso8601String(),
            'preview_url' => $prompt->preview_url,
        ];
    }
}
