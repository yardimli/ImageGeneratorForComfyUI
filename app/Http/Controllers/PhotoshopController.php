<?php

namespace App\Http\Controllers;

use App\Services\FalModelService;
use Illuminate\View\View;
use Throwable;

class PhotoshopController extends Controller
{
    public function index(FalModelService $falModels): View
    {
        try {
            $imageModels = $falModels->models('image-to-image');
        } catch (Throwable $exception) {
            report($exception);
            $imageModels = [];
        }

        return view('photoshop.index', compact('imageModels'));
    }
}