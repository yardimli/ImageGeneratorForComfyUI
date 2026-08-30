<?php

namespace App\Support;

class ImageUrl
{
    public static function preview(?string $path, string $defaultDirectory = 'storage/images'): ?string
    {
        if (!$path) {
            return null;
        }

        $path = trim($path);

        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
            return $path;
        }

        $normalized = str_replace('\\', '/', $path);

        if (preg_match('#(?:^|/)storage/app/public/(.+)$#i', $normalized, $matches)) {
            $relativePath = 'storage/' . $matches[1];
        } elseif (preg_match('#(?:^|/)public/storage/(.+)$#i', $normalized, $matches)) {
            $relativePath = 'storage/' . $matches[1];
        } elseif (str_starts_with(ltrim($normalized, '/'), 'storage/')) {
            $relativePath = ltrim($normalized, '/');
        } else {
            $relativePath = trim($defaultDirectory, '/') . '/' . basename($normalized);
        }

        $baseUrl = rtrim(config('app.image_url') ?: config('app.url'), '/');

        return $baseUrl . '/' . ltrim($relativePath, '/');
    }
}
