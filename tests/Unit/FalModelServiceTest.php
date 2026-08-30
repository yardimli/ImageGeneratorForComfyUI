<?php

namespace Tests\Unit;

use App\Services\FalModelService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FalModelServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fal.api_key' => 'test-key',
            'services.fal.platform_url' => 'https://api.fal.test/v1',
        ]);
        Cache::flush();
    }

    public function test_it_fetches_all_pages_once_and_reuses_the_cached_catalogue(): void
    {
        Http::fake(function ($request) {
            if ($request->data()['cursor'] ?? null) {
                return Http::response([
                    'models' => [[
                        'endpoint_id' => 'fal-ai/a-model',
                        'metadata' => ['display_name' => 'A model'],
                    ]],
                    'next_cursor' => null,
                    'has_more' => false,
                ]);
            }

            return Http::response([
                'models' => [[
                    'endpoint_id' => 'fal-ai/z-model',
                    'metadata' => ['display_name' => 'Z model'],
                ]],
                'next_cursor' => 'page-2',
                'has_more' => true,
            ]);
        });

        $service = app(FalModelService::class);

        $first = $service->models();
        $second = $service->models();

        $this->assertSame(['fal-ai/a-model', 'fal-ai/z-model'], array_column($first, 'endpoint_id'));
        $this->assertSame($first, $second);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Key test-key')
            && $request->data()['category'] === 'text-to-image');
    }

    public function test_refresh_replaces_the_cache_and_price_returns_the_matching_entry(): void
    {
        Cache::forever('fal.models.text_to_image.catalogue.v3', [['endpoint_id' => 'fal-ai/old']]);

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/models/pricing?endpoint_id=fal-ai%2Fnew')) {
                return Http::response([
                    'prices' => [[
                        'endpoint_id' => 'fal-ai/new',
                        'unit_price' => 0.025,
                        'unit' => 'image',
                        'currency' => 'USD',
                    ]],
                    'next_cursor' => null,
                    'has_more' => false,
                ]);
            }

            return Http::response([
                'models' => [['endpoint_id' => 'fal-ai/new', 'metadata' => []]],
                'next_cursor' => null,
                'has_more' => false,
            ]);
        });

        $service = app(FalModelService::class);

        $this->assertSame('fal-ai/new', $service->refreshModels()[0]['endpoint_id']);
        $this->assertSame(0.025, $service->price('fal-ai/new')['unit_price']);
    }

    public function test_it_keeps_separate_catalogues_for_different_model_categories(): void
    {
        Http::fake(function ($request) {
            $category = $request->data()['category'];

            return Http::response([
                'models' => [[
                    'endpoint_id' => 'fal-ai/'.$category,
                    'metadata' => ['category' => $category],
                ]],
                'next_cursor' => null,
                'has_more' => false,
            ]);
        });

        $service = app(FalModelService::class);

        $this->assertSame('fal-ai/text-to-image', $service->models()[0]['endpoint_id']);
        $this->assertSame(
            'fal-ai/image-to-image',
            $service->models('image-to-image')[0]['endpoint_id']
        );
        Http::assertSentCount(2);
    }

    public function test_cached_models_never_fetches_the_catalogue(): void
    {
        Http::fake();
        Cache::forever('fal.models.text_to_image.catalogue.v3', [
            ['endpoint_id' => 'fal-ai/cached-text-model'],
        ]);
        Cache::forever('fal.models.image_to_image.catalogue.v3', [
            ['endpoint_id' => 'fal-ai/cached-image-model'],
        ]);

        $service = app(FalModelService::class);

        $this->assertSame('fal-ai/cached-text-model', $service->cachedModels()[0]['endpoint_id']);
        $this->assertSame(
            'fal-ai/cached-image-model',
            $service->cachedModels('image-to-image')[0]['endpoint_id']
        );
        $this->assertSame([], $service->cachedModels('unknown-category'));
        Http::assertNothingSent();
    }

    public function test_it_excludes_lora_models_from_image_catalogues(): void
    {
        Http::fake(Http::response([
            'models' => [
                ['endpoint_id' => 'fal-ai/regular', 'metadata' => ['display_name' => 'Regular model']],
                ['endpoint_id' => 'fal-ai/flux-lora', 'metadata' => ['display_name' => 'Flux model']],
                ['endpoint_id' => 'fal-ai/hidden', 'metadata' => ['display_name' => 'Portrait LoRA Studio']],
            ],
            'next_cursor' => null,
            'has_more' => false,
        ]));

        $models = app(FalModelService::class)->refreshModels();

        $this->assertSame(['fal-ai/regular'], array_column($models, 'endpoint_id'));
        $this->assertSame(['fal-ai/regular'], array_column(app(FalModelService::class)->cachedModels(), 'endpoint_id'));
    }
}
