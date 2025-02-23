<?php

	use App\Http\Controllers\GalleryController;
	use App\Http\Controllers\HomeController;
	use App\Http\Controllers\PromptController;
	use App\Http\Controllers\UpscaleAndNotesController;
	use Illuminate\Support\Facades\Route;

	/*
	|--------------------------------------------------------------------------
	| Web Routes
	|--------------------------------------------------------------------------
	|
	| Here is where you can register web routes for your application. These
	| routes are loaded by the RouteServiceProvider and all of them will
	| be assigned to the "web" middleware group. Make something great!
	|
	*/

	Route::get('/', function () {
		return view('welcome');
	});

	Auth::routes(['register' => false]);

	Route::middleware('auth')->group(function () {
		Route::get('/home', [HomeController::class, 'index'])->name('home');

		Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');

		Route::get('/generate', [PromptController::class, 'index'])->name('prompts.index');
		Route::post('/generate', [PromptController::class, 'generate'])->name('prompts.generate');
		Route::get('/prompts/settings/{id}', [PromptController::class, 'loadSettings'])->name('prompts.settings.load');

		Route::post('/templates/save', [PromptController::class, 'saveTemplate'])->name('templates.save');

		Route::post('/images/{prompt}/update-notes', [UpscaleAndNotesController::class, 'updateNotes']);
		Route::post('/images/{prompt}/upscale', [UpscaleAndNotesController::class, 'upscaleImage'])->name('image.upscale');
		Route::get('/images/{prompt}/upscale-status/{prediction_id}', [UpscaleAndNotesController::class, 'checkUpscaleStatus'])->name('image.upscale.status');

	});

