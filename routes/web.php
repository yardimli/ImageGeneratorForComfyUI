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
	use App\Http\Controllers\StoryPdfController;
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

	// Publicly viewable story routes
	Route::get('stories', [StoryController::class, 'index'])->name('stories.index');
	Route::get('stories/{story}', [StoryController::class, 'show'])->name('stories.show');

	Route::middleware('auth')->group(function () {
		Route::get('/home', [HomeController::class, 'index'])->name('home');

		// --- Gallery ---
		Route::prefix('gallery')->name('gallery.')->group(function () {
			Route::get('/', [GalleryController::class, 'index'])->name('index');
			Route::get('/filter', [GalleryController::class, 'filter'])->name('filter');
		});

		// --- Routes with unique naming that must be outside the 'prompts' group ---
		Route::post('/templates/save', [PromptController::class, 'saveTemplate'])->name('templates.save');
		Route::delete('/prompt-settings/{id}', [PromptController::class, 'deleteSettingWithImages'])->name('prompt-settings.delete');


		// --- Prompt Generation & Management ---
		Route::prefix('prompts')->name('prompts.')->group(function () {
			Route::get('/', [PromptController::class, 'index'])->name('index');
			Route::post('/', [PromptController::class, 'generate'])->name('generate');
			Route::post('/store-generated', [PromptController::class, 'storeGeneratedPrompts'])->name('store-generated');
			Route::post('/bulk-delete', [PromptController::class, 'bulkDelete'])->name('bulk-delete');
			Route::delete('/{prompt}', [PromptController::class, 'deletePrompt'])->name('delete');

			Route::prefix('settings')->name('settings.')->group(function () {
				Route::get('/latest', [PromptController::class, 'getLatestSetting'])->name('latest');
				Route::get('/{id}', [PromptController::class, 'loadSettings'])->name('load');
				// Note: The delete route for settings is now outside this group.
			});
		});

		// --- Image Actions (Upscale, Notes) ---
		Route::prefix('images/{prompt}')->name('image.')->group(function () {
			Route::post('/update-notes', [UpscaleAndNotesController::class, 'updateNotes'])->name('notes.update');
			Route::post('/upscale', [UpscaleAndNotesController::class, 'upscaleImage'])->name('upscale');
			Route::get('/upscale-status/{prediction_id}', [UpscaleAndNotesController::class, 'checkUpscaleStatus'])->name('upscale.status');
		});

		// --- Image Mix ---
		Route::prefix('image-mix')->name('image-mix.')->group(function () {
			Route::get('/', [ImageMixController::class, 'index'])->name('index');
			Route::post('/store', [ImageMixController::class, 'store'])->name('store');
			Route::post('/upload', [ImageMixController::class, 'uploadImage'])->name('upload');
			Route::get('/uploads', [ImageMixController::class, 'getUploadedImages'])->name('uploads');
		});

		// --- Kontext ---
		Route::prefix('kontext-basic')->name('kontext-basic.')->group(function () {
			Route::get('/', [KontextBasicController::class, 'index'])->name('index');
			Route::post('/store', [KontextBasicController::class, 'store'])->name('store');
			Route::get('/render-history', [KontextBasicController::class, 'getRenderHistory'])->name('render-history');
		});

		Route::prefix('kontext-lora')->name('kontext-lora.')->group(function () {
			Route::get('/', [KontextLoraController::class, 'index'])->name('index');
			Route::post('/store', [KontextLoraController::class, 'store'])->name('store');
			Route::get('/render-history', [KontextLoraController::class, 'getRenderHistory'])->name('render-history');
		});

		// --- Image Editor ---
		Route::prefix('image-editor')->name('image-editor.')->group(function () {
			Route::get('/', [ImageEditorController::class, 'index'])->name('index');
			Route::post('/save', [ImageEditorController::class, 'save'])->name('save');
			// START MODIFICATION: Add the new proxy route.
			Route::post('/proxy-image', [ImageEditorController::class, 'proxyImage'])->name('proxy');
			// END MODIFICATION
		});

		// --- Pexels Integration ---
		Route::prefix('pexels')->name('pexels.')->group(function () {
			Route::get('/search', [PexelsController::class, 'search'])->name('search');
			Route::post('/download', [PexelsController::class, 'download'])->name('download');
		});

		// --- Queue Management ---
		Route::get('/queue', [PromptController::class, 'queue'])->name('prompts.queue');

		Route::prefix('queue')->name('prompts.queue.')->group(function () {
			Route::delete('/delete-all', [PromptController::class, 'deleteAllQueuedPrompts'])->name('delete-all');
			Route::delete('/{prompt}', [PromptController::class, 'deleteQueuedPrompt'])->name('delete');
			Route::post('/requeue/{prompt}', [PromptController::class, 'requeuePrompt'])->name('requeue');
		});

		// --- Album Covers ---
		Route::prefix('album-covers')->name('album-covers.')->group(function () {
			Route::get('/', [AlbumCoverController::class, 'index'])->name('index');
			Route::get('/liked', [AlbumCoverController::class, 'showLiked'])->name('liked');
			Route::post('/update-liked', [AlbumCoverController::class, 'updateLiked'])->name('update-liked');
			Route::post('/upload', [AlbumCoverController::class, 'upload'])->name('upload');
			Route::post('/generate-prompts', [AlbumCoverController::class, 'generatePrompts'])->name('generate-prompts');

			Route::prefix('kontext')->name('kontext.')->group(function () {
				Route::post('/generate', [AlbumCoverController::class, 'generateKontext'])->name('generate');
				Route::post('/status', [AlbumCoverController::class, 'checkKontextStatus'])->name('status');
			});

			Route::prefix('{cover}')->group(function () {
				Route::post('/update-prompt', [AlbumCoverController::class, 'updateMixPrompt'])->name('update-prompt');
				Route::post('/update-notes', [AlbumCoverController::class, 'updateNotes'])->name('update-notes');
				Route::post('/unlike', [AlbumCoverController::class, 'unlikeCover'])->name('unlike');
				Route::post('/upscale', [AlbumCoverController::class, 'upscaleCover'])->name('upscale');
				Route::get('/upscale-status/{prediction_id}', [AlbumCoverController::class, 'checkUpscaleStatus'])->name('upscale.status');
			});
		});

		// --- Stories ---
		Route::prefix('stories')->name('stories.')->group(function () {
			// Resource routes (except index and show)
			Route::get('/create', [StoryController::class, 'create'])->name('create');
			Route::post('/', [StoryController::class, 'store'])->name('store');
			Route::get('/{story}/edit', [StoryController::class, 'edit'])->name('edit');
			Route::put('/{story}', [StoryController::class, 'update'])->name('update');
			Route::delete('/{story}', [StoryController::class, 'destroy'])->name('destroy');

			// Custom routes
			Route::get('/create/ai', [StoryController::class, 'createWithAi'])->name('create-ai');
			Route::post('/create/ai', [StoryController::class, 'storeWithAi'])->name('store-ai');
			Route::post('/generate-image-prompt', [StoryController::class, 'generateImagePrompt'])->name('generate-image-prompt');
			// START MODIFICATION: Add routes for character and place prompt generation.
			Route::post('/generate-character-image-prompt', [StoryController::class, 'generateCharacterImagePrompt'])->name('generate-character-image-prompt');
			Route::post('/generate-place-image-prompt', [StoryController::class, 'generatePlaceImagePrompt'])->name('generate-place-image-prompt');
			// END MODIFICATION

			Route::prefix('pages/{storyPage}')->name('pages.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generate'])->name('generate-image');
				Route::get('/image-status', [StoryImageController::class, 'checkStatus'])->name('image-status');
			});

			// START MODIFICATION: Add routes for character and place image generation.
			Route::prefix('characters/{character}')->name('characters.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generateForCharacter'])->name('generate-image');
				Route::get('/image-status', [StoryImageController::class, 'checkCharacterStatus'])->name('image-status');
			});

			Route::prefix('places/{place}')->name('places.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generateForPlace'])->name('generate-image');
				Route::get('/image-status', [StoryImageController::class, 'checkPlaceStatus'])->name('image-status');
			});
			// END MODIFICATION

			Route::get('/{story}/characters', [StoryController::class, 'characters'])->name('characters');
			Route::post('/{story}/characters', [StoryController::class, 'updateCharacters'])->name('characters.update');
			Route::get('/{story}/places', [StoryController::class, 'places'])->name('places');
			Route::post('/{story}/places', [StoryController::class, 'updatePlaces'])->name('places.update');

			Route::get('/{story}/pdf/setup', [StoryPdfController::class, 'setup'])->name('pdf.setup');
			Route::post('/{story}/pdf/generate', [StoryPdfController::class, 'generate'])->name('pdf.generate');
		});
	});
