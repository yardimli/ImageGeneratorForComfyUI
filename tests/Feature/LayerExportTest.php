<?php

namespace Tests\Feature;

use App\Http\Controllers\LayerController;
use App\Models\Layer;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class LayerExportTest extends TestCase
{
    public function test_download_all_contains_every_layer_and_metadata_json(): void
    {
        $user = new User();
        $user->id = 7;
        $this->actingAs($user);

        $layer = new Layer([
            'user_id' => 7,
            'model' => 'bytedance/seedream/v5/pro/layerize',
            'input_image' => 'https://example.test/source.png',
            'status' => 2,
            'layers' => [
                [
                    'name' => 'Base image',
                    'description' => 'Inpainted background',
                    'z_index' => 0,
                    'image' => ['url' => 'https://fal.media/base.png'],
                ],
                [
                    'name' => 'Foreground cat',
                    'description' => 'Blue cat',
                    'z_index' => 1,
                    'bounding_box' => ['absolute' => [10, 20, 110, 220]],
                    'image' => ['url' => 'https://fal.media/cat.png'],
                ],
            ],
        ]);
        $layer->id = 2;
        $layer->created_at = now();

        Http::fake([
            'https://fal.media/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $response = app(LayerController::class)->downloadAll($layer);
        $archivePath = $response->getFile()->getPathname();
        $zip = new ZipArchive();

        try {
            $this->assertTrue($zip->open($archivePath) === true);
            $this->assertSame(3, $zip->numFiles);
            $this->assertNotFalse($zip->locateName('01-z00-base-image.png'));
            $this->assertNotFalse($zip->locateName('02-z01-foreground-cat.png'));

            $metadata = json_decode($zip->getFromName('metadata.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('Inpainted background', $metadata['layers'][0]['description']);
            $this->assertSame('Blue cat', $metadata['layers'][1]['description']);
            $this->assertSame('02-z01-foreground-cat.png', $metadata['layers'][1]['image']['file']);
        } finally {
            $zip->close();
            @unlink($archivePath);
        }
    }

    public function test_editor_payload_contains_history_and_import_metadata(): void
    {
        $layer = new Layer([
            'user_id' => 7,
            'status' => 2,
            'input_image' => '/storage/layerize-inputs/7/source.png',
            'layers' => [[
                'name' => 'Foreground',
                'z_index' => 3,
                'bounding_box' => ['absolute' => [10, 20, 110, 220]],
                'image' => ['url' => 'https://fal.media/foreground.png', 'width' => 100, 'height' => 200],
            ]],
        ]);
        $layer->id = 42;
        $layer->created_at = now();

        $method = new \ReflectionMethod(LayerController::class, 'layerPayload');
        $method->setAccessible(true);
        $payload = $method->invoke(app(LayerController::class), $layer);

        $this->assertSame(42, $payload['id']);
        $this->assertSame('ready', $payload['status']);
        $this->assertSame([10, 20, 110, 220], $payload['layers'][0]['bounds']);
        $this->assertSame(3, $payload['layers'][0]['zIndex']);
        $this->assertStringContainsString('/layers/42/download/0', $payload['layers'][0]['url']);
    }
}
