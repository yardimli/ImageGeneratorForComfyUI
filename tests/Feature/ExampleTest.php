<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_public_index_ignores_stale_impersonation_session_without_a_user(): void
    {
        $this->withSession(['impersonator_id' => 1])
            ->get('/')
            ->assertOk()
            ->assertDontSee('You are viewing DreamCover as');
    }
}
