<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Rolandstarke\Thumbnail\Facades\Thumbnail;

class GalleryController extends Controller
{
    public function index()
    {
        $images = Prompt::where('user_id', auth()->id())
            ->whereNotNull('filename')
            ->orderBy('created_at', 'desc')
            ->paginate(18);

        $images->getCollection()->transform(function ($prompt) {
	        if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
                $prompt->thumbnail = Thumbnail::src($prompt->filename)
                    ->preset('thumbnail_450_jpg')
                    ->url();
            }
            return $prompt;
        });

        return view('gallery.index', compact('images'));
    }

	public function filter(Request $request)
	{
		$sourceImage = $request->query('source_image');

		$query = Prompt::where('user_id', auth()->id())
			->whereNotNull('filename');

		if ($sourceImage) {
			$query->where(function($q) use ($sourceImage) {
				$q->where('input_image_1', $sourceImage)
					->orWhere('input_image_2', $sourceImage);
			});
		}

		$images = $query->orderBy('created_at', 'desc')
			->paginate(18);

		$images->getCollection()->transform(function ($prompt) {
			if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
				$prompt->thumbnail = Thumbnail::src($prompt->filename)
					->preset('thumbnail_450_jpg')
					->url();
			}
			return $prompt;
		});

		$filterActive = !empty($sourceImage);
		$filterDescription = "";

		if ($filterActive && $sourceImage) {
			$filterDescription = "Images generated using source: " . basename($sourceImage);
		}

		return view('gallery.index', compact('images', 'filterActive', 'filterDescription'));
	}
}
