<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    public function test_user_can_delete_their_queue_record_by_id(): void
    {
        $this->createQueueTestTables();
        $user = User::factory()->create();
        $prompt = Prompt::query()->create([
            'user_id' => $user->id,
            'render_status' => 4,
            'generation_type' => 'prompt',
            'model' => 'flux-1/dev',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('prompts.queue.delete', ['promptId' => $prompt->id]))
            ->assertOk()
            ->assertJson(['success' => true, 'prompt_id' => $prompt->id]);

        $this->assertDatabaseMissing('prompts', ['id' => $prompt->id]);
    }

    public function test_deleting_an_already_removed_queue_record_is_successful(): void
    {
        $this->createQueueTestTables();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('prompts.queue.delete', ['promptId' => 40493]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Prompt was already removed',
                'prompt_id' => 40493,
            ]);
    }

    private function createQueueTestTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prompts')) {
            Schema::create('prompts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->integer('render_status')->default(0);
                $table->string('generation_type')->default('prompt');
                $table->string('model')->default('flux-1/dev');
                $table->timestamps();
            });
        }
    }
}
