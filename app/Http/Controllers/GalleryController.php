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
                    ->preset('thumbnail_350_jpg')
                    ->url();
            }
            return $prompt;
        });

        return view('gallery.index', compact('images'));
    }
}
