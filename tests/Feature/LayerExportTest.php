<?php

namespace Tests\Feature;

use App\Http\Controllers\LayerController;
use App\Models\Layer;
use Tests\TestCase;

class LayerExportTest extends TestCase
{
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
