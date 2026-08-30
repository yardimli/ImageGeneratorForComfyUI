<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\Layer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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

    private const HTTP_REQUEST_TIMEOUT_SECONDS = 30;

    private const RESULT_POLL_ATTEMPTS = 20;

    private const RESULT_POLL_INTERVAL_SECONDS = 3;

    /**
     * Claim and render one job from the shared queue.
     *
     * @return array<string, mixed>
     */
    public function processNext(): array
    {
        $timeout = max(
            self::RESULT_POLL_ATTEMPTS * self::RESULT_POLL_INTERVAL_SECONDS,
            (int) config('services.fal.render_timeout', 180)
        );
        $lock = Cache::lock(self::WORKER_LOCK, $timeout + self::LOCK_GRACE_SECONDS);

        if (! $lock->get()) {
            return ['state' => 'busy', 'message' => 'Another browser is already rendering a job.'];
        }

        try {
            ignore_user_abort(true);
            @set_time_limit($timeout + self::LOCK_GRACE_SECONDS);
            $this->recoverStaleJobs(workerLockHeld: true);
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

        $baseQuery = Prompt::query()->whereIn('generation_type', ['prompt', 'layerize']);
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
        if (! in_array($prompt->generation_type, ['prompt', 'layerize'], true) || (int) $prompt->render_status !== 1) {
            return [
                'state' => 'not_cancelled',
                'message' => 'This render job is no longer processing.',
                'job' => $this->presentJob($prompt),
            ];
        }

        $cancelled = Prompt::query()
            ->whereKey($prompt->id)
            ->whereIn('generation_type', ['prompt', 'layerize'])
            ->where('render_status', 1)
            ->update(['render_status' => 4]);

        $prompt->refresh();

        if ($cancelled === 1 && $prompt->generation_type === 'layerize') {
            Layer::query()->where('prompt_id', $prompt->id)->update([
                'status' => 4,
                'error' => 'Cancelled by user.',
            ]);
        }

        return [
            'state' => $cancelled === 1 ? 'cancelled' : 'not_cancelled',
            'message' => $cancelled === 1
                ? 'The current render job was cancelled.'
                : 'This render job is no longer processing.',
            'job' => $this->presentJob($prompt),
        ];
    }

    private function recoverStaleJobs(bool $workerLockHeld = false): void
    {
        // Status requests run in parallel with the long-running AJAX worker.
        // Never let one of those requests mark the active worker's job failed.
        if (! $workerLockHeld) {
            $workerProbe = Cache::lock(self::WORKER_LOCK, 1);
            if (! $workerProbe->get()) {
                return;
            }
            $workerProbe->release();
        }

        $staleAfter = max(
            self::RESULT_POLL_ATTEMPTS * self::RESULT_POLL_INTERVAL_SECONDS,
            (int) config('services.fal.render_timeout', 180)
        ) + self::RESULT_POLL_INTERVAL_SECONDS;

        $staleLayerPromptIds = Prompt::query()
            ->where('generation_type', 'layerize')
            ->where('render_status', 1)
            ->where('updated_at', '<=', now()->subSeconds($staleAfter))
            ->pluck('id');

        Prompt::query()
            ->whereIn('generation_type', ['prompt', 'layerize'])
            ->where('render_status', 1)
            ->where('updated_at', '<=', now()->subSeconds($staleAfter))
            ->update(['render_status' => 4]);

        if ($staleLayerPromptIds->isNotEmpty()) {
            Layer::query()->whereIn('prompt_id', $staleLayerPromptIds)->update([
                'status' => 4,
                'error' => 'The layerize job stopped responding.',
            ]);
        }
    }

    private function claimNextPrompt(): ?Prompt
    {
        return DB::transaction(function () {
            $eligible = Prompt::query()
                ->whereIn('generation_type', ['prompt', 'layerize'])
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

        if ($prompt->generation_type === 'layerize') {
            $this->renderLayerize($prompt);

            return;
        }

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

            if (! $imageUrl) {
                throw new \RuntimeException('fal.ai completed without returning an image URL.');
            }

            if (! $this->downloadImage($imageUrl, $localTempPath, $prompt)) {
                throw new \RuntimeException('The fal.ai image URL could not be downloaded by the server.');
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

    private function generateWithFal(string $falKey, string $modelName, Prompt $prompt): mixed
    {
        $overallTimeout = max(
            self::RESULT_POLL_ATTEMPTS * self::RESULT_POLL_INTERVAL_SECONDS,
            (int) config('services.fal.render_timeout', 180)
        );
        $overallDeadline = microtime(true) + $overallTimeout;
        $imageReferences = $this->prepareInputImages($prompt);
        if ($prompt->generation_type === 'layerize') {
            if ($imageReferences === []) {
                throw new \RuntimeException('The layerize input image could not be prepared.');
            }

            $arguments = [
                'prompt' => '',
                'image_url' => $imageReferences[0],
                'image_size' => 'auto',
                'enable_safety_checker' => true,
                'enhance_prompt_mode' => 'standard',
            ];
        } else {
            $arguments = [
                'prompt' => $prompt->generated_prompt,
                'image_size' => $modelName === 'bytedance/seedream/v5/pro/edit'
                    ? 'auto_2K'
                    : [
                        'width' => $prompt->width ?? 1024,
                        'height' => $prompt->height ?? 1024,
                    ],
            ];

            if ($modelName === 'bytedance/seedream/v5/pro/edit') {
                $arguments['num_images'] = 1;
                $arguments['output_format'] = 'png';
                $arguments['enable_safety_checker'] = true;
            }

            if ($imageReferences !== []) {
                $arguments['image_urls'] = $imageReferences;
            }
        }

        // Keep the queue routing identical to the original ProcessRenderJobs
        // command. Model identifiers stored without the fal-ai/ namespace are
        // submitted below that namespace, including Bytedance edit endpoints.
        $submitUrl = $this->falSubmitUrl($modelName);
        $headers = ['Authorization' => 'Key '.$falKey];

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->timeout(self::HTTP_REQUEST_TIMEOUT_SECONDS)
                ->post($submitUrl, $arguments);
        } catch (ConnectionException $exception) {
            Log::channel('fal_ajax')->notice('fal.ai submit request did not receive a response.', [
                'prompt_id' => $prompt->id,
                'method' => 'POST',
                'url' => $submitUrl,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
        $this->logFalResponse('submit', 'POST', $submitUrl, $response, $prompt);

        if ($response->failed()) {
            Log::warning('fal.ai render submission failed.', [
                'prompt_id' => $prompt->id,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            throw new \RuntimeException("fal.ai rejected the render submission with HTTP {$response->status()}.");
        }

        if (! $this->touchProcessingHeartbeat($prompt)) {
            return null;
        }

        $data = $response->json();
        $requestId = $data['request_id'] ?? null;
        if (! $requestId) {
            Log::warning('fal.ai submission response did not include a request ID.', [
                'prompt_id' => $prompt->id,
                'body' => Str::limit($response->body(), 1000),
            ]);
            throw new \RuntimeException('fal.ai accepted the submission without returning a request ID.');
        }

        $submittedResultUrl = is_string($data['response_url'] ?? null)
            ? $data['response_url']
            : null;

        if ($this->isSeedreamProModel($modelName)) {
            // Seedream Pro is a partner endpoint whose identifier intentionally
            // does not use the fal-ai/ prefix. Keep its full path and prefer the
            // authoritative URLs returned by the submission response.
            $statusUrl = is_string($data['status_url'] ?? null)
                ? $data['status_url']
                : "{$submitUrl}/requests/{$requestId}/status";
            $baseResultUrls = [
                $submittedResultUrl,
                "{$submitUrl}/requests/{$requestId}",
            ];
        } else {
            // Preserve the original ProcessRenderJobs behavior for existing
            // fal-ai models: poll through the first model path segment.
            $pollingBaseUrl = $this->falPollingBaseUrl($modelName, $requestId);
            $statusUrl = "{$pollingBaseUrl}/status";
            $baseResultUrls = [
                $pollingBaseUrl,
                $submittedResultUrl,
            ];
        }
        $methodNotAllowedCount = 0;

        while (microtime(true) < $overallDeadline) {
            if ($this->jobWasCancelledOrFailed($prompt)) {
                return null;
            }

            $remainingBeforePoll = $overallDeadline - microtime(true);
            usleep((int) (min(3, max(0, $remainingBeforePoll)) * 1_000_000));
            if (microtime(true) >= $overallDeadline) {
                break;
            }

            $requestTimeout = max(1, min(
                self::HTTP_REQUEST_TIMEOUT_SECONDS,
                (int) ceil($overallDeadline - microtime(true))
            ));
            try {
                $statusResponse = Http::withHeaders($headers)->timeout($requestTimeout)->get($statusUrl);
                $this->logFalResponse('status', 'GET', $statusUrl, $statusResponse, $prompt, $requestId);
            } catch (ConnectionException $exception) {
                Log::channel('fal_ajax')->notice('fal.ai status request did not receive a response.', [
                    'prompt_id' => $prompt->id,
                    'request_id' => $requestId,
                    'method' => 'GET',
                    'url' => $statusUrl,
                    'message' => $exception->getMessage(),
                ]);
                continue;
            }

            if ($this->jobWasCancelledOrFailed($prompt)) {
                return null;
            }

            if ($statusResponse->failed()) {
                if ($statusResponse->status() === 405
                    && ++$methodNotAllowedCount >= self::RESULT_POLL_ATTEMPTS) {
                    throw new \RuntimeException(
                        'fal.ai rejected status polling with HTTP 405 for 20 consecutive polls.'
                    );
                }
                continue;
            }

            if (! $this->touchProcessingHeartbeat($prompt)) {
                return null;
            }

            $jobStatus = $statusResponse->json('status', 'UNKNOWN');
            if ($jobStatus === 'COMPLETED') {
                // Try the original result URL first. fal-provided response URLs
                // and /response variants remain fallbacks for newer endpoints.
                $resultUrls = $this->falResultUrlCandidates([
                    ...$baseResultUrls,
                    $statusResponse->json('response_url'),
                ]);
                $resultPayload = $this->retrieveFalResult(
                    $resultUrls,
                    $headers,
                    $prompt,
                    $requestId
                );
                if ($prompt->generation_type === 'layerize') {
                    return $resultPayload;
                }

                $imageUrl = data_get($resultPayload, 'images.0.url')
                    ?? data_get($resultPayload, 'image.url');

                return $imageUrl;
            }

            if (in_array($jobStatus, ['FAILED', 'ERROR'], true)) {
                $remoteError = $statusResponse->json('error') ?? $statusResponse->json('message');
                Log::warning('fal.ai reported a failed render.', [
                    'prompt_id' => $prompt->id,
                    'request_id' => $requestId,
                    'remote_error' => $remoteError,
                    'body' => Str::limit($statusResponse->body(), 2000),
                ]);
                throw new \RuntimeException('fal.ai reported that the render failed.'.($remoteError ? ' '.$remoteError : ''));
            }
        }

        throw new \RuntimeException("fal.ai did not complete the render within {$overallTimeout} seconds.");
    }

    private function falSubmitUrl(string $modelName): string
    {
        $normalizedModelName = Str::startsWith($modelName, 'fal-ai/')
            ? Str::after($modelName, 'fal-ai/')
            : $modelName;

        if ($this->isSeedreamProModel($normalizedModelName)) {
            return "https://queue.fal.run/{$normalizedModelName}";
        }

        return "https://queue.fal.run/fal-ai/{$normalizedModelName}";
    }

    private function isSeedreamProModel(string $modelName): bool
    {
        $normalizedModelName = Str::startsWith($modelName, 'fal-ai/')
            ? Str::after($modelName, 'fal-ai/')
            : $modelName;

        return Str::startsWith($normalizedModelName, 'bytedance/seedream/v5/pro/');
    }

    private function falPollingBaseUrl(string $modelName, string $requestId): string
    {
        $normalizedModelName = Str::startsWith($modelName, 'fal-ai/')
            ? Str::after($modelName, 'fal-ai/')
            : $modelName;
        $pollingModelPath = Str::before($normalizedModelName, '/');

        return "https://queue.fal.run/fal-ai/{$pollingModelPath}/requests/{$requestId}";
    }

    /**
     * fal.ai can report COMPLETED briefly before its model output is readable.
     *
     * @param  array<int, string>  $resultUrls
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function retrieveFalResult(
        array $resultUrls,
        array $headers,
        Prompt $prompt,
        string $requestId
    ): array {
        $diagnostics = [];

        for ($attempt = 1; $attempt <= self::RESULT_POLL_ATTEMPTS; $attempt++) {
            if ($this->jobWasCancelledOrFailed($prompt)) {
                return [];
            }

            sleep(self::RESULT_POLL_INTERVAL_SECONDS);

            foreach ($resultUrls as $resultUrl) {
                try {
                    $resultResponse = Http::withHeaders($headers)
                        ->timeout(self::HTTP_REQUEST_TIMEOUT_SECONDS)
                        ->get($resultUrl);
                    $this->logFalResponse(
                        'result',
                        'GET',
                        $resultUrl,
                        $resultResponse,
                        $prompt,
                        $requestId,
                        $attempt
                    );
                    $rawPayload = $resultResponse->json();
                    $diagnostics[$resultUrl] = [
                        'attempt' => $attempt,
                        'status' => $resultResponse->status(),
                        'top_level_keys' => is_array($rawPayload) ? array_keys($rawPayload) : [],
                        'body' => Str::limit($resultResponse->body(), 4000),
                    ];

                    if ($resultResponse->successful()) {
                        $payload = $this->normalizeFalResultPayload($rawPayload);
                        $hasExpectedOutput = $prompt->generation_type === 'layerize'
                            ? ! empty($payload['layers'])
                            : is_string(data_get($payload, 'images.0.url'))
                                || is_string(data_get($payload, 'image.url'));

                        if ($hasExpectedOutput) {
                            return $payload;
                        }
                    }
                } catch (ConnectionException $exception) {
                    $diagnostics[$resultUrl] = [
                        'attempt' => $attempt,
                        'status' => null,
                        'top_level_keys' => [],
                        'body' => $exception->getMessage(),
                    ];
                    Log::channel('fal_ajax')->notice('fal.ai result request did not receive a response.', [
                        'prompt_id' => $prompt->id,
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'method' => 'GET',
                        'url' => $resultUrl,
                        'message' => $exception->getMessage(),
                    ]);
                }

                if (! $this->touchProcessingHeartbeat($prompt)) {
                    return [];
                }
            }

        }

        Log::error('fal.ai completed but its result payload never became available.', [
            'prompt_id' => $prompt->id,
            'request_id' => $requestId,
            'attempts' => $diagnostics,
        ]);

        throw new \RuntimeException(
            $prompt->generation_type === 'layerize'
                ? 'fal.ai completed without returning layer data.'
                : 'fal.ai completed without returning an image URL.'
        );
    }

    private function logFalResponse(
        string $phase,
        string $method,
        string $url,
        Response $response,
        Prompt $prompt,
        ?string $requestId = null,
        ?int $attempt = null
    ): void {
        Log::channel('fal_ajax')->debug('fal.ai HTTP response.', [
            'phase' => $phase,
            'prompt_id' => $prompt->id,
            'request_id' => $requestId,
            'attempt' => $attempt,
            'method' => $method,
            'url' => $url,
            'http_status' => $response->status(),
            'response_body' => $response->body(),
        ]);
    }

    /**
     * Keep fal-provided URLs first for backward compatibility, then try their
     * canonical /response variants for endpoints that return queue metadata.
     *
     * @param  array<int, mixed>  $urls
     * @return array<int, string>
     */
    private function falResultUrlCandidates(array $urls): array
    {
        $candidates = [];

        foreach ($urls as $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $url = trim($url);
            $candidates[] = $url;

            if (str_ends_with($url, '/status')) {
                $candidates[] = substr($url, 0, -strlen('/status')).'/response';
            } elseif (! str_ends_with($url, '/response')) {
                $candidates[] = rtrim($url, '/').'/response';
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * fal.ai REST responses contain model output directly, while some gateways
     * and clients wrap the same output in data/response/result/output.
     *
     * @return array<string, mixed>
     */
    private function normalizeFalResultPayload(mixed $payload, int $depth = 0): array
    {
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (! is_array($payload) || $depth > 5) {
            return [];
        }

        if (array_key_exists('images', $payload)
            || array_key_exists('layers', $payload)
            || is_string(data_get($payload, 'image.url'))) {
            return $payload;
        }

        $prioritizedValues = [];
        foreach (['data', 'response', 'result', 'output', 'payload'] as $wrapper) {
            if (array_key_exists($wrapper, $payload)) {
                $prioritizedValues[] = $payload[$wrapper];
            }
        }
        foreach ($payload as $key => $value) {
            if (! in_array($key, ['data', 'response', 'result', 'output', 'payload'], true)) {
                $prioritizedValues[] = $value;
            }
        }

        foreach ($prioritizedValues as $value) {
            $result = $this->normalizeFalResultPayload($value, $depth + 1);
            if ($result !== []) {
                return $result;
            }
        }

        return [];
    }

    private function renderLayerize(Prompt $prompt): void
    {
        $layer = Layer::query()->where('prompt_id', $prompt->id)->first();

        if (! $layer) {
            $prompt->forceFill(['render_status' => 4])->save();

            return;
        }

        try {
            $falKey = $this->falKeyFor($prompt);
            if (! $falKey) {
                throw new \RuntimeException('FAL_API_KEY is not configured.');
            }

            $result = $this->generateWithFal($falKey, $prompt->model, $prompt);
            if ($this->jobWasCancelledOrFailed($prompt)) {
                $layer->forceFill(['status' => 4, 'error' => 'Cancelled by user.'])->save();

                return;
            }

            if (! is_array($result) || empty($result['layers'])) {
                throw new \RuntimeException('fal.ai completed without returning layer data.');
            }

            $flattenedImages = $result['images'] ?? [];
            $structuredLayers = array_map(function (array $item, int $index) use ($flattenedImages) {
                if (empty($item['image']['url']) && ! empty($flattenedImages[$index]['url'])) {
                    $item['image'] = array_merge($flattenedImages[$index], $item['image'] ?? []);
                    $item['image']['url'] = $flattenedImages[$index]['url'];
                }

                return $item;
            }, $result['layers'], array_keys($result['layers']));

            $layer->forceFill([
                'status' => 2,
                'images' => $flattenedImages,
                'layers' => $structuredLayers,
                'error' => null,
            ])->save();
            $prompt->forceFill(['render_status' => 2])->save();
        } catch (Throwable $exception) {
            Log::error('AJAX layerize job failed.', [
                'layer_id' => $layer->id,
                'prompt_id' => $prompt->id,
                'message' => $exception->getMessage(),
            ]);
            report($exception);
            $layer->forceFill(['status' => 4, 'error' => $exception->getMessage()])->save();
            $prompt->forceFill(['render_status' => 4])->save();
        }
    }

    private function touchProcessingHeartbeat(Prompt $prompt): bool
    {
        Prompt::query()
            ->whereKey($prompt->id)
            ->where('render_status', 1)
            ->update(['updated_at' => now()]);
        $prompt->refresh();

        $isStillProcessing = (int) $prompt->render_status === 1;

        // MySQL reports zero affected rows when updated_at already has the same
        // second-level value. That is not a cancellation; the persisted status
        // is the source of truth.
        return $isStillProcessing;
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

    private function downloadImage(string $url, string $outputPath, Prompt $prompt): bool
    {
        $response = Http::timeout(60)->get($url);

        if ($response->failed()) {
            Log::warning('Generated fal.ai image download failed.', [
                'prompt_id' => $prompt->id,
                'host' => parse_url($url, PHP_URL_HOST),
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'body' => Str::limit($response->body(), 1000),
            ]);

            return false;
        }

        $body = $response->body();
        if ($body === '' || file_put_contents($outputPath, $body) === false) {
            Log::warning('Generated fal.ai image could not be written to temporary storage.', [
                'prompt_id' => $prompt->id,
                'temp_directory' => dirname($outputPath),
                'content_length' => strlen($body),
            ]);

            return false;
        }

        return true;
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
