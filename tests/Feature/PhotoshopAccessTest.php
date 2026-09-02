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
            ->assertSee('placeImageInput')
            ->assertSee('layerAssetStore')
            ->assertSee('clipboardDocumentCard')
            ->assertSee('imageSizeDialog')
            ->assertSee('canvasSizeDialog')
            ->assertSee('canvasAnchorGrid')
            ->assertSee('zoomStatusInput')
            ->assertSee('documentStatusSize')
            ->assertSee('lassoSelection')
            ->assertSee('newLayerFolder')
            ->assertSee('lrs-folder.png')
            ->assertSee('deleteLayersDialog')
            ->assertSee('imageToImageDialog')
            ->assertSee('imageToImageModel')
            ->assertSee('imageToImageParameters')
            ->assertSee('imageToImageAutoCrop')
            ->assertSee('imageToImageTransparentColor')
            ->assertSee('genAiAutoCrop')
            ->assertSee('genAiTransparentColor')
            ->assertSee('ByteDance Seedream V4.5 Edit')
            ->assertSee('Z-Image Turbo Image To Image LoRA')
            ->assertSee('Gemini 3 Pro Image Preview Edit (Nano Banana Pro)')
            ->assertSee('Nano Banana 2 Edit')
            ->assertSee('imageToImageHistoryDialog')
            ->assertSee('lrs-bin.png');
    }

    public function test_cloud_project_api_is_not_available(): void
    {
        $this->actingAs(User::factory()->make())
            ->getJson('/photoshop/projects')
            ->assertNotFound();
    }
}
