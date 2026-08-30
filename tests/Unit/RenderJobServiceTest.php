<?php

namespace Tests\Unit;

use App\Models\Prompt;
use App\Services\RenderJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RenderJobServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_a_processing_job_after_thirty_seconds_without_a_result(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $prompt = $this->processingPrompt();
        Prompt::query()->whereKey($prompt->id)->update([
            'updated_at' => now()->subSeconds(31),
        ]);

        app(RenderJobService::class)->status();

        $this->assertSame(4, (int) $prompt->fresh()->render_status);
    }

    public function test_the_current_processing_job_can_be_cancelled(): void
    {
        $prompt = $this->processingPrompt();

        $result = app(RenderJobService::class)->cancel($prompt);

        $this->assertSame('cancelled', $result['state']);
        $this->assertSame(4, (int) $prompt->fresh()->render_status);
    }

    private function processingPrompt(): Prompt
    {
        return Prompt::query()->create([
            'user_id' => 1,
            'render_status' => 1,
            'generation_type' => 'prompt',
            'generated_prompt' => 'Test prompt',
            'model' => 'fal-ai/test-model',
        ]);
    }
}
