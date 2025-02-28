<?php

	use App\Http\Controllers\GalleryController;
	use App\Http\Controllers\HomeController;
	use App\Http\Controllers\ImageMixController;
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
		Route::post('/store-generated-prompts', [PromptController::class, 'storeGeneratedPrompts'])->name('prompts.store-generated');

		Route::get('/prompts/settings/latest', [PromptController::class, 'getLatestSetting'])->name('prompts.settings.latest');
		Route::get('/prompts/settings/{id}', [PromptController::class, 'loadSettings'])->name('prompts.settings.load');

		Route::delete('/prompts/{prompt}', [PromptController::class, 'deletePrompt'])->name('prompts.delete');
		Route::delete('/prompt-settings/{id}', [PromptController::class, 'deleteSettingWithImages'])->name('prompt-settings.delete');


		Route::post('/templates/save', [PromptController::class, 'saveTemplate'])->name('templates.save');

		Route::post('/images/{prompt}/update-notes', [UpscaleAndNotesController::class, 'updateNotes']);
		Route::post('/images/{prompt}/upscale', [UpscaleAndNotesController::class, 'upscaleImage'])->name('image.upscale');
		Route::get('/images/{prompt}/upscale-status/{prediction_id}', [UpscaleAndNotesController::class, 'checkUpscaleStatus'])->name('image.upscale.status');


		Route::get('/image-mix', [ImageMixController::class, 'index'])->name('image-mix.index');
		Route::post('/image-mix/store', [ImageMixController::class, 'store'])->name('image-mix.store');
		Route::get('/image-mix/settings/latest', [ImageMixController::class, 'getLatestSetting'])->name('image-mix.settings.latest');
		Route::get('/image-mix/settings/{id}', [ImageMixController::class, 'loadSettings'])->name('image-mix.settings.load');
		Route::post('/image-mix/upload', [ImageMixController::class, 'uploadImage'])->name('image-mix.upload');

	});

