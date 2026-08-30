<?php

namespace Tests\Unit;

use App\Support\ImageUrl;
use Tests\TestCase;

class ImageUrlTest extends TestCase
{
    public function test_it_builds_generated_preview_urls_from_image_url(): void
    {
        config(['app.image_url' => 'https://images.example.com']);

        $this->assertSame(
            'https://images.example.com/storage/images/render.png',
            ImageUrl::preview('render.png')
        );
        $this->assertSame(
            'https://images.example.com/storage/images/render.png',
            ImageUrl::preview('C:\\project\\storage\\app\\public\\images\\render.png')
        );
    }

    public function test_it_preserves_remote_urls(): void
    {
        config(['app.image_url' => 'https://images.example.com']);

        $this->assertSame(
            'https://cdn.example.com/render.png',
            ImageUrl::preview('https://cdn.example.com/render.png')
        );
    }

    public function test_it_falls_back_to_app_url(): void
    {
        config([
            'app.image_url' => null,
            'app.url' => 'https://app.example.com',
        ]);

        $this->assertSame(
            'https://app.example.com/storage/images/render.png',
            ImageUrl::preview('render.png')
        );
    }
}
