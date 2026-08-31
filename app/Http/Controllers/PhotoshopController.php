<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PhotoshopController extends Controller
{
    public function index(): View
    {
        return view('photoshop.index');
    }
}