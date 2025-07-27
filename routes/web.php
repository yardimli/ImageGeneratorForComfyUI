<?php

	use App\Http\Controllers\AlbumCoverController;
	use App\Http\Controllers\GalleryController;
	use App\Http\Controllers\HomeController;
	use App\Http\Controllers\ImageEditorController;
	use App\Http\Controllers\ImageMixController;
	use App\Http\Controllers\KontextBasicController;
	use App\Http\Controllers\KontextLoraController;
	use App\Http\Controllers\PexelsController;
	use App\Http\Controllers\PromptController;
	use App\Http\Controllers\StoryController;
	use App\Http\Controllers\StoryImageController;
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

		// Gallery Routes
		Route::prefix('gallery')->name('gallery.')->group(function () {
			Route::get('/', [GalleryController::class, 'index'])->name('index');
			Route::get('/filter', [GalleryController::class, 'filter'])->name('filter');
		});

		// Prompt/Generate Routes
		Route::prefix('prompts')->name('prompts.')->group(function () {
			Route::get('/settings/latest', [PromptController::class, 'getLatestSetting'])->name('settings.latest');
			Route::get('/settings/{id}', [PromptController::class, 'loadSettings'])->name('settings.load');
			Route::post('/bulk-delete', [PromptController::class, 'bulkDelete'])->name('bulk-delete');
			Route::delete('/{prompt}', [PromptController::class, 'deletePrompt'])->name('delete');
		});

		// Generate routes (keeping original paths for backward compatibility)
		Route::get('/generate', [PromptController::class, 'index'])->name('prompts.index');
		Route::post('/generate', [PromptController::class, 'generate'])->name('prompts.generate');
		Route::post('/store-generated-prompts', [PromptController::class, 'storeGeneratedPrompts'])->name('prompts.store-generated');
		Route::delete('/prompt-settings/{id}', [PromptController::class, 'deleteSettingWithImages'])->name('prompt-settings.delete');

		// Queue Routes
		Route::prefix('queue')->name('prompts.queue.')->group(function () {
			Route::get('/', [PromptController::class, 'queue'])->name('index');
			Route::delete('/delete-all', [PromptController::class, 'deleteAllQueuedPrompts'])->name('delete-all');
			Route::delete('/{prompt}', [PromptController::class, 'deleteQueuedPrompt'])->name('delete');
			Route::post('/requeue/{prompt}', [PromptController::class, 'requeuePrompt'])->name('requeue');
		});

		// Template Routes
		Route::prefix('templates')->name('templates.')->group(function () {
			Route::post('/save', [PromptController::class, 'saveTemplate'])->name('save');
		});

		// Image Routes
		Route::prefix('images')->name('image.')->group(function () {
			Route::post('/{prompt}/update-notes', [UpscaleAndNotesController::class, 'updateNotes']);
			Route::post('/{prompt}/upscale', [UpscaleAndNotesController::class, 'upscaleImage'])->name('upscale');
			Route::get('/{prompt}/upscale-status/{prediction_id}', [UpscaleAndNotesController::class, 'checkUpscaleStatus'])->name('upscale.status');
		});

		// Image Mix Routes
		Route::prefix('image-mix')->name('image-mix.')->group(function () {
			Route::get('/', [ImageMixController::class, 'index'])->name('index');
			Route::post('/store', [ImageMixController::class, 'store'])->name('store');
			Route::post('/upload', [ImageMixController::class, 'uploadImage'])->name('upload');
			Route::get('/uploads', [ImageMixController::class, 'getUploadedImages'])->name('uploads');
		});

		// Kontext Basic Routes
		Route::prefix('kontext-basic')->name('kontext-basic.')->group(function () {
			Route::get('/', [KontextBasicController::class, 'index'])->name('index');
			Route::post('/store', [KontextBasicController::class, 'store'])->name('store');
			Route::get('/render-history', [KontextBasicController::class, 'getRenderHistory'])->name('render-history');
		});

		// Kontext Lora Routes
		Route::prefix('kontext-lora')->name('kontext-lora.')->group(function () {
			Route::get('/', [KontextLoraController::class, 'index'])->name('index');
			Route::post('/store', [KontextLoraController::class, 'store'])->name('store');
			Route::get('/render-history', [KontextLoraController::class, 'getRenderHistory'])->name('render-history');
		});

		// Image Editor Routes
		Route::prefix('image-editor')->name('image-editor.')->group(function () {
			Route::get('/', [ImageEditorController::class, 'index'])->name('index');
			Route::post('/save', [ImageEditorController::class, 'save'])->name('save');
		});

		// Pexels Routes
		Route::prefix('pexels')->name('pexels.')->group(function () {
			Route::get('/search', [PexelsController::class, 'search'])->name('search');
			Route::post('/download', [PexelsController::class, 'download'])->name('download');
		});

		// Album Covers Routes
		Route::prefix('album-covers')->name('album-covers.')->group(function () {
			Route::get('/', [AlbumCoverController::class, 'index'])->name('index');
			Route::get('/liked', [AlbumCoverController::class, 'showLiked'])->name('liked');
			Route::post('/update-liked', [AlbumCoverController::class, 'updateLiked'])->name('update-liked');
			Route::post('/upload', [AlbumCoverController::class, 'upload'])->name('upload');
			Route::post('/generate-prompts', [AlbumCoverController::class, 'generatePrompts'])->name('generate-prompts');

			// Kontext sub-routes
			Route::prefix('kontext')->name('kontext.')->group(function () {
				Route::post('/generate', [AlbumCoverController::class, 'generateKontext'])->name('generate');
				Route::post('/status', [AlbumCoverController::class, 'checkKontextStatus'])->name('status');
			});

			// Cover-specific routes
			Route::prefix('{cover}')->group(function () {
				Route::post('/update-prompt', [AlbumCoverController::class, 'updateMixPrompt'])->name('update-prompt');
				Route::post('/update-notes', [AlbumCoverController::class, 'updateNotes'])->name('update-notes');
				Route::post('/unlike', [AlbumCoverController::class, 'unlikeCover'])->name('unlike');
				Route::post('/upscale', [AlbumCoverController::class, 'upscaleCover'])->name('upscale');
				Route::get('/upscale-status/{prediction_id}', [AlbumCoverController::class, 'checkUpscaleStatus'])->name('upscale.status');
			});
		});

		// Stories Routes
		Route::prefix('stories')->name('stories.')->group(function () {
			// AI creation routes
			Route::get('/create/ai', [StoryController::class, 'createWithAi'])->name('create-ai');
			Route::post('/create/ai', [StoryController::class, 'storeWithAi'])->name('store-ai');
			Route::post('/generate-image-prompt', [StoryController::class, 'generateImagePrompt'])->name('generate-image-prompt');

			// Page image generation routes
			Route::prefix('pages/{storyPage}')->name('pages.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generate'])->name('generate-image');
				Route::get('/image-status', [StoryImageController::class, 'checkStatus'])->name('image-status');
			});

			// Story-specific routes
			Route::prefix('{story}')->group(function () {
				Route::get('/characters', [StoryController::class, 'characters'])->name('characters');
				Route::post('/characters', [StoryController::class, 'updateCharacters'])->name('characters.update');
				Route::get('/places', [StoryController::class, 'places'])->name('places');
				Route::post('/places', [StoryController::class, 'updatePlaces'])->name('places.update');
			});
		});

		// Stories resource routes (must be after prefix routes to avoid conflicts)
		Route::resource('stories', StoryController::class);
	});
