<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptSetting;
use App\Support\ImageUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ImageUploadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $image = $validated['image'];
        $filename = time().'_'.$image->getClientOriginalName();
        $image->storeAs('public/uploads', $filename);

        return response()->json([
            'success' => true,
            'path' => ImageUrl::preview('storage/uploads/'.$filename),
            'source_path' => '/storage/uploads/'.$filename,
            'filename' => $filename,
        ]);
    }

    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $sort = $request->query('sort', 'newest');
        $perPage = (int) $request->query('perPage', 12);
        $perPage = in_array($perPage, [12, 24, 48, 96], true) ? $perPage : 12;

        $prompts = Prompt::where('user_id', auth()->id())
            ->whereIn('generation_type', ['mix', 'mix-one', 'kontext-basic', 'kontext-lora'])
            ->where(function ($query) {
                $query->where(function ($nested) {
                    $nested->whereNotNull('input_image_1')->where('input_image_1', '!=', '');
                })->orWhere(function ($nested) {
                    $nested->whereNotNull('input_image_2')->where('input_image_2', '!=', '');
                });
            })
            ->get(['input_image_1', 'input_image_2']);

        $usageCounts = [];
        foreach ($prompts as $prompt) {
            foreach ([$prompt->input_image_1, $prompt->input_image_2] as $path) {
                if ($path) {
                    $usageCounts[$path] = ($usageCounts[$path] ?? 0) + 1;
                }
            }
        }

        $settings = PromptSetting::where('user_id', auth()->id())
            ->whereIn('generation_type', ['mix', 'mix-one', 'kontext-basic', 'kontext-lora'])
            ->where(function ($query) {
                $query->whereNotNull('input_images_1')->orWhereNotNull('input_images_2');
            })
            ->orderBy('created_at')
            ->get(['input_images_1', 'input_images_2', 'generation_type', 'created_at']);

        $uniqueImages = [];
        foreach ($settings as $setting) {
            $this->addImages($uniqueImages, $setting->input_images_1, $setting->created_at);

            // Historical mixer settings stored image objects in the second input column.
            if ($setting->generation_type === 'mix') {
                $this->addImages($uniqueImages, $setting->input_images_2, $setting->created_at);
            }
        }

        $images = collect($uniqueImages)
            ->map(function (array $image) use ($usageCounts) {
                $image['usage_count'] = $usageCounts[$image['path']] ?? 0;
                $image['uploaded_at_formatted'] = $image['created_at'] instanceof Carbon
                    ? $image['created_at']->format('Y-m-d H:i')
                    : 'N/A';
                unset($image['created_at']);

                return $image;
            })
            ->values();

        $images = match ($sort) {
            'oldest' => $images->sortBy('uploaded_at_formatted')->values(),
            'count_desc' => $images->sort(function (array $left, array $right) {
                return ($right['usage_count'] <=> $left['usage_count'])
                    ?: strcmp($right['name'], $left['name']);
            })->values(),
            default => $images->sortByDesc('uploaded_at_formatted')->values(),
        };

        $totalImages = $images->count();
        $totalPages = (int) ceil($totalImages / $perPage);

        return response()->json([
            'images' => $images->slice(($page - 1) * $perPage, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_images' => $totalImages,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function addImages(array &$uniqueImages, ?string $json, $createdAt): void
    {
        $images = json_decode($json ?? '', true);
        if (! is_array($images)) {
            return;
        }

        foreach ($images as $image) {
            $path = $image['path'] ?? null;
            if (! $path || isset($uniqueImages[$path])) {
                continue;
            }

            $uniqueImages[$path] = [
                'path' => $path,
                'name' => basename($path),
                'prompt' => $image['prompt'] ?? '',
                'created_at' => $createdAt,
            ];
        }
    }
}
