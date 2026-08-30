<?php

namespace Tests\Feature;

use Tests\TestCase;

class RenderQueueAccessTest extends TestCase
{
    public function test_render_queue_status_requires_authentication(): void
    {
        $this->get('/render-queue/status')->assertRedirect('/login');
    }

    public function test_render_queue_processing_requires_authentication(): void
    {
        $this->post('/render-queue/process')->assertRedirect('/login');
    }

    public function test_render_queue_cancellation_requires_authentication(): void
    {
        $this->post('/render-queue/1/cancel')->assertRedirect('/login');
    }

    public function test_failed_generation_bulk_delete_requires_authentication(): void
    {
        $this->delete('/queue/failed/delete-all')->assertRedirect('/login');
    }
}
