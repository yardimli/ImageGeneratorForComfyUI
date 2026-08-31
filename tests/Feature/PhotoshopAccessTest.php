<?php

namespace Tests\Feature;

use App\Models\PhotoshopLayer;
use App\Models\PhotoshopProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoshopAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_photoshop(): void
    {
        $this->get('/photoshop')->assertRedirect('/login');
    }

    public function test_user_can_create_project_with_png_layer(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/photoshop/projects', [
            'name' => 'Cover sketch', 'width' => 1024, 'height' => 1024, 'blank_layer' => true,
        ])->assertCreated()->assertJsonPath('project.name', 'Cover sketch');

        $project = PhotoshopProject::findOrFail($response->json('project.id'));
        $this->assertSame($user->id, $project->user_id);
        $this->assertCount(1, $project->layers);
        Storage::disk('public')->assertExists($project->layers->first()->file_path);
    }

    public function test_project_and_layer_endpoints_are_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = PhotoshopProject::create(['user_id' => $owner->id, 'name' => 'Private', 'width' => 100, 'height' => 100]);
        $layer = PhotoshopLayer::create(['photoshop_project_id' => $project->id, 'name' => 'Layer 1', 'file_path' => 'photoshop/test.png', 'width' => 100, 'height' => 100]);

        $this->actingAs($other)->getJson("/photoshop/projects/{$project->id}")->assertNotFound();
        $this->actingAs($other)->patchJson("/photoshop/layers/{$layer->id}", ['x' => 20])->assertNotFound();
    }

    public function test_user_can_upload_and_transform_png_layer(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = PhotoshopProject::create(['user_id' => $user->id, 'name' => 'Local import', 'width' => 50, 'height' => 50]);
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6WQAAAABJRU5ErkJggg==';

        $layerId = $this->actingAs($user)->postJson("/photoshop/projects/{$project->id}/layers", [
            'name' => 'Imported', 'image' => $png, 'width' => 50, 'height' => 50, 'x' => 0, 'y' => 0,
        ])->assertCreated()->json('layer.id');

        $this->patchJson("/photoshop/layers/{$layerId}", ['x' => 12.5, 'rotation' => 30, 'opacity' => 75])
            ->assertOk()->assertJsonPath('layer.opacity', 75);
        $this->assertDatabaseHas('photoshop_layers', ['id' => $layerId, 'opacity' => 75]);
    }
}
