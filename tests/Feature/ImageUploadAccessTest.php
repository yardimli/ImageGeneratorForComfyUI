<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImageUploadAccessTest extends TestCase
{
    public function test_shared_upload_history_requires_authentication(): void
    {
        $this->get('/image-uploads')->assertRedirect('/login');
    }

    public function test_shared_image_upload_requires_authentication(): void
    {
        $this->post('/image-uploads')->assertRedirect('/login');
    }
}
