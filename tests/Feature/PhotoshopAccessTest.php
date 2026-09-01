<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PhotoshopAccessTest extends TestCase
{
    public function test_guest_cannot_open_photoshop(): void
    {
        $this->get('/photoshop')->assertRedirect('/login');
    }

    public function test_user_can_open_local_photoshop_workspace(): void
    {
        $this->actingAs(User::factory()->make())
            ->get('/photoshop')
            ->assertOk()
            ->assertSee('localFileInput')
            ->assertSee('newLayerFolder')
            ->assertSee('lrs-folder.png')
            ->assertSee('deleteLayersDialog')
            ->assertSee('lrs-bin.png');
    }

    public function test_cloud_project_api_is_not_available(): void
    {
        $this->actingAs(User::factory()->make())
            ->getJson('/photoshop/projects')
            ->assertNotFound();
    }
}