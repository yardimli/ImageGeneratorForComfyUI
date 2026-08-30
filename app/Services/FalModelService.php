<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FalModelService
{
    /** @return array<int, array<string, mixed>> */
    public function cachedModels(string $category = 'text-to-image'): array
    {
        $models = Cache::get($this->modelsCacheKey($category));

        if (is_array($models)) {
            return $this->withoutLoraModels($models);
        }

        // Preserve the text-to-image catalogue cached before categories were split.
        if ($category === 'text-to-image') {
            $legacyModels = Cache::get('fal.models.text_to_image_catalogue.v2');
            if (is_array($legacyModels)) {
                return $this->withoutLoraModels($legacyModels);
            }
        }

        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function models(string $category = 'text-to-image'): array
    {
        $cacheKey = $this->modelsCacheKey($category);
        if (Cache::has($cacheKey)) {
            return $this->withoutLoraModels(Cache::get($cacheKey, []));
        }

        return $this->refreshModels($category);
    }

    /** @return array<int, array<string, mixed>> */
    public function refreshModels(string $category = 'text-to-image'): array
    {
        $models = [];
        $cursor = null;
        $seenCursors = [];

        do {
            $query = [
                'limit' => 100,
                'category' => $category,
            ];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $payload = $this->request('models', $query);
            $pageModels = $payload['models'] ?? null;

            if (! is_array($pageModels)) {
                throw new RuntimeException('fal.ai returned an invalid model catalogue response.');
            }

            foreach ($pageModels as $model) {
                if (is_array($model) && ! empty($model['endpoint_id'])) {
                    $models[$model['endpoint_id']] = $model;
                }
            }

            $nextCursor = $payload['next_cursor'] ?? null;
            if ($nextCursor !== null && isset($seenCursors[$nextCursor])) {
                throw new RuntimeException('fal.ai returned a repeated pagination cursor.');
            }

            if ($nextCursor !== null) {
                $seenCursors[$nextCursor] = true;
            }

            $cursor = ($payload['has_more'] ?? false) ? $nextCursor : null;
        } while ($cursor !== null);

        $models = $this->withoutLoraModels(array_values($models));
        usort($models, static function (array $left, array $right): int {
            $leftName = $left['metadata']['display_name'] ?? $left['endpoint_id'];
            $rightName = $right['metadata']['display_name'] ?? $right['endpoint_id'];

            return strcasecmp($leftName, $rightName);
        });

        Cache::forever($this->modelsCacheKey($category), $models);
        Cache::forever($this->updatedAtCacheKey($category), now()->toIso8601String());

        return $models;
    }

    /** @return array<string, mixed>|null */
    public function price(string $endpointId): ?array
    {
        $payload = $this->request('models/pricing', ['endpoint_id' => $endpointId]);
        $prices = $payload['prices'] ?? [];

        if (! is_array($prices)) {
            throw new RuntimeException('fal.ai returned an invalid pricing response.');
        }

        foreach ($prices as $price) {
            if (is_array($price) && ($price['endpoint_id'] ?? null) === $endpointId) {
                return $price;
            }
        }

        return null;
    }

    public function lastUpdatedAt(string $category = 'text-to-image'): ?string
    {
        return Cache::get($this->updatedAtCacheKey($category));
    }

    private function modelsCacheKey(string $category): string
    {
        return 'fal.models.'.str_replace('-', '_', $category).'.catalogue.v3';
    }

    private function updatedAtCacheKey(string $category): string
    {
        return 'fal.models.'.str_replace('-', '_', $category).'.updated_at.v3';
    }

    /** @param array<int, mixed> $models
     *  @return array<int, array<string, mixed>>
     */
    private function withoutLoraModels(array $models): array
    {
        return array_values(array_filter($models, static function ($model): bool {
            if (! is_array($model)) {
                return false;
            }

            $endpointId = (string) ($model['endpoint_id'] ?? '');
            $displayName = (string) ($model['metadata']['display_name'] ?? '');

            return stripos($endpointId, 'lora') === false
                && stripos($displayName, 'lora') === false;
        }));
    }

    /** @return array<string, mixed> */
    private function request(string $path, array $query): array
    {
        $apiKey = (string) config('services.fal.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('FAL_API_KEY is not configured.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.fal.platform_url'), '/'))
            ->withHeaders(['Authorization' => 'Key '.$apiKey])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 250)
            ->get($path, $query);

        if ($response->failed()) {
            throw new RuntimeException('fal.ai request failed with status '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('fal.ai returned an invalid JSON response.');
        }

        return $payload;
    }
}
