<?php

namespace Tests\Unit;

use App\Services\RenderJobService;
use ReflectionMethod;
use Tests\TestCase;

class RenderJobServiceTest extends TestCase
{
    public function test_it_accepts_direct_and_wrapped_fal_result_payloads(): void
    {
        $service = app(RenderJobService::class);
        $normalize = new ReflectionMethod($service, 'normalizeFalResultPayload');
        $normalize->setAccessible(true);
        $output = [
            'images' => [['url' => 'https://fal.media/result.png']],
            'layers' => [['image' => ['url' => 'https://fal.media/layer.png']]],
        ];

        $this->assertSame($output, $normalize->invoke($service, $output));
        $this->assertSame($output, $normalize->invoke($service, ['data' => $output]));
        $this->assertSame($output, $normalize->invoke($service, ['response' => ['result' => $output]]));
        $this->assertSame($output, $normalize->invoke($service, json_encode(['output' => $output])));
        $this->assertSame($output, $normalize->invoke($service, [
            'request_id' => 'request-id',
            'unexpected_gateway_wrapper' => ['payload' => $output],
        ]));
    }

    public function test_it_rejects_queue_metadata_without_model_output(): void
    {
        $service = app(RenderJobService::class);
        $normalize = new ReflectionMethod($service, 'normalizeFalResultPayload');
        $normalize->setAccessible(true);

        $this->assertSame([], $normalize->invoke($service, [
            'status' => 'COMPLETED',
            'request_id' => 'request-id',
        ]));
    }

    public function test_it_preserves_provided_result_urls_and_adds_response_fallbacks(): void
    {
        $service = app(RenderJobService::class);
        $candidates = new ReflectionMethod($service, 'falResultUrlCandidates');
        $candidates->setAccessible(true);

        $this->assertSame([
            'https://queue.fal.run/model/requests/one',
            'https://queue.fal.run/model/requests/one/response',
            'https://queue.fal.run/model/requests/two/response',
        ], $candidates->invoke($service, [
            'https://queue.fal.run/model/requests/one',
            'https://queue.fal.run/model/requests/two/response',
            'https://queue.fal.run/model/requests/one',
        ]));
    }
}
