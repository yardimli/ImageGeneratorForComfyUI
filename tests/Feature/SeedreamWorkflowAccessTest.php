<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeedreamWorkflowAccessTest extends TestCase
{
    public function test_image_editor_pro_requires_authentication(): void
    {
        $this->get('/image-editor-pro')->assertRedirect('/login');
        $this->post('/image-editor-pro/generate')->assertRedirect('/login');
    }

    public function test_layerize_requires_authentication(): void
    {
        $this->post('/layers')->assertRedirect('/login');
        $this->get('/layers/history')->assertRedirect('/login');
        $this->get('/layers/1/status')->assertRedirect('/login');
        $this->get('/layers/1/download/0')->assertRedirect('/login');
    }
}
