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

		Route::get('/gallery/filter', [GalleryController::class, 'filter'])->name('gallery.filter');
		Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');

		Route::get('/generate', [PromptController::class, 'index'])->name('prompts.index');
		Route::post('/generate', [PromptController::class, 'generate'])->name('prompts.generate');
		Route::post('/store-generated-prompts', [PromptController::class, 'storeGeneratedPrompts'])->name('prompts.store-generated');

		Route::post('/prompts/bulk-delete', [PromptController::class, 'bulkDelete'])->name('prompts.bulk-delete');
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
		Route::post('/image-mix/upload', [ImageMixController::class, 'uploadImage'])->name('image-mix.upload');
		Route::get('/image-mix/uploads', [ImageMixController::class, 'getUploadedImages'])->name('image-mix.uploads');

		Route::get('/kontext-basic', [KontextBasicController::class, 'index'])->name('kontext-basic.index');
		Route::post('/kontext-basic/store', [KontextBasicController::class, 'store'])->name('kontext-basic.store');
		Route::get('/kontext-basic/render-history', [KontextBasicController::class, 'getRenderHistory'])->name('kontext-basic.render-history');

		Route::get('/kontext-lora', [KontextLoraController::class, 'index'])->name('kontext-lora.index');
		Route::post('/kontext-lora/store', [KontextLoraController::class, 'store'])->name('kontext-lora.store');
		Route::get('/kontext-lora/render-history', [KontextLoraController::class, 'getRenderHistory'])->name('kontext-lora.render-history');

		Route::get('/image-editor', [ImageEditorController::class, 'index'])->name('image-editor.index');
		Route::post('/image-editor/save', [ImageEditorController::class, 'save'])->name('image-editor.save');

		Route::get('/pexels/search', [PexelsController::class, 'search'])->name('pexels.search');
		Route::post('/pexels/download', [PexelsController::class, 'download'])->name('pexels.download');

		Route::get('/queue', [PromptController::class, 'queue'])->name('prompts.queue');
		Route::delete('/queue/delete-all', [PromptController::class, 'deleteAllQueuedPrompts'])->name('prompts.queue.delete-all');
		Route::delete('/queue/{prompt}', [PromptController::class, 'deleteQueuedPrompt'])->name('prompts.queue.delete');
		Route::post('/queue/requeue/{prompt}', [PromptController::class, 'requeuePrompt'])->name('prompts.queue.requeue');

		Route::get('/album-covers', [AlbumCoverController::class, 'index'])->name('album-covers.index');
		Route::get('/album-covers/liked', [AlbumCoverController::class, 'showLiked'])->name('album-covers.liked');
		Route::post('/album-covers/update-liked', [AlbumCoverController::class, 'updateLiked'])->name('album-covers.update-liked');
		Route::post('/album-covers/upload', [AlbumCoverController::class, 'upload'])->name('album-covers.upload'); // New Route
		Route::post('/album-covers/generate-prompts', [AlbumCoverController::class, 'generatePrompts'])->name('album-covers.generate-prompts');
		Route::post('/album-covers/kontext/generate', [AlbumCoverController::class, 'generateKontext'])->name('album-covers.kontext.generate');
		Route::post('/album-covers/kontext/status', [AlbumCoverController::class, 'checkKontextStatus'])->name('album-covers.kontext.status');
		Route::post('/album-covers/{cover}/update-prompt', [AlbumCoverController::class, 'updateMixPrompt'])->name('album-covers.update-prompt');
		Route::post('/album-covers/{cover}/update-notes', [AlbumCoverController::class, 'updateNotes'])->name('album-covers.update-notes');
		// START MODIFICATION: Add route for unliking a cover
		Route::post('/album-covers/{cover}/unlike', [AlbumCoverController::class, 'unlikeCover'])->name('album-covers.unlike');
		// END MODIFICATION
		Route::post('/album-covers/{cover}/upscale', [AlbumCoverController::class, 'upscaleCover'])->name('album-covers.upscale');
		Route::get('/album-covers/{cover}/upscale-status/{prediction_id}', [AlbumCoverController::class, 'checkUpscaleStatus'])->name('album-covers.upscale.status');

		Route::get('/stories/create/ai', [StoryController::class, 'createWithAi'])->name('stories.create-ai');
		Route::post('/stories/create/ai', [StoryController::class, 'storeWithAi'])->name('stories.store-ai');

		Route::resource('stories', StoryController::class);
		Route::get('/stories/{story}/characters', [StoryController::class, 'characters'])->name('stories.characters');
		Route::post('/stories/{story}/characters', [StoryController::class, 'updateCharacters'])->name('stories.characters.update');
		Route::get('/stories/{story}/places', [StoryController::class, 'places'])->name('stories.places');
		Route::post('/stories/{story}/places', [StoryController::class, 'updatePlaces'])->name('stories.places.update');

	});
