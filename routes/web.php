<?php

	use App\Http\Controllers\AlbumCoverController;
	use App\Http\Controllers\CreateStoryController;
	use App\Http\Controllers\GalleryController;
	use App\Http\Controllers\HomeController;
	use App\Http\Controllers\ImageEditorController;
	use App\Http\Controllers\ImageEditController; // MODIFICATION: Import new controller
	use App\Http\Controllers\ImageMixController;
	use App\Http\Controllers\KontextBasicController;
	use App\Http\Controllers\KontextLoraController;
	use App\Http\Controllers\LlmPromptController;
	use App\Http\Controllers\PexelsController;
	use App\Http\Controllers\PromptController;
	use App\Http\Controllers\PromptDictionaryController;
	use App\Http\Controllers\PromptDictionaryImageController;
	use App\Http\Controllers\QuizController;
	use App\Http\Controllers\StoryController;
	use App\Http\Controllers\StoryImageController;
	use App\Http\Controllers\StoryPdfController;
	use App\Http\Controllers\UpscaleAndNotesController;
	use App\Http\Controllers\UpscaleSettingController;
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

		// LLM Prompt Management
		Route::prefix('llm-prompts')->name('llm-prompts.')->group(function () {
			Route::get('/', [LlmPromptController::class, 'index'])->name('index');
			Route::get('/{prompt}/edit', [LlmPromptController::class, 'edit'])->name('edit');
			Route::put('/{prompt}', [LlmPromptController::class, 'update'])->name('update');
		});

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
			Route::post('/generate', [PromptController::class, 'generate'])->name('generate');
			Route::post('/store-generated', [PromptController::class, 'storeGeneratedPrompts'])->name('store-generated');
			Route::post('/bulk-delete', [PromptController::class, 'bulkDelete'])->name('bulk-delete');
			Route::delete('/{prompt}', [PromptController::class, 'deletePrompt'])->name('delete');

			Route::prefix('settings')->name('settings.')->group(function () {
				Route::get('/latest', [PromptController::class, 'getLatestSetting'])->name('settings.latest');
				Route::get('/{id}', [PromptController::class, 'loadSettings'])->name('settings.load');
				// Note: The delete route for settings is now outside this group.
			});
		});

		// Prompt Dictionary routes for new generate/save flow and delete.
		Route::prefix('prompt-dictionary')->name('prompt-dictionary.')->group(function () {
			Route::get('/', [PromptDictionaryController::class, 'grid'])->name('index');
			Route::get('/edit', [PromptDictionaryController::class, 'edit'])->name('edit');
			Route::post('/edit', [PromptDictionaryController::class, 'update'])->name('update');
			Route::delete('/{entry}', [PromptDictionaryController::class, 'destroy'])->name('destroy'); // Added delete route
			Route::post('/rewrite-description', [PromptDictionaryController::class, 'rewriteDescription'])->name('rewrite-description');
			Route::post('/generate-image-prompt', [PromptDictionaryController::class, 'generateImagePrompt'])->name('generate-image-prompt');
			Route::get('/search', [PromptDictionaryController::class, 'search'])->name('search'); // Add search route
			Route::post('/preview-generated-entries', [PromptDictionaryController::class, 'previewGeneratedEntries'])->name('preview-generated-entries');
			Route::post('/store-generated-entries', [PromptDictionaryController::class, 'storeGeneratedEntries'])->name('store-generated-entries');

			Route::prefix('{entry}')->group(function () {
				Route::post('/generate-image', [PromptDictionaryImageController::class, 'generate'])->name('generate-image');
				Route::get('/image-status', [PromptDictionaryImageController::class, 'checkStatus'])->name('image-status');
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

		// START MODIFICATION: Add routes for the new Image Edit feature
		Route::prefix('image-edit')->name('image-edit.')->group(function () {
			Route::get('/', [ImageEditController::class, 'index'])->name('index');
			Route::post('/generate', [ImageEditController::class, 'generate'])->name('generate');
			Route::get('/status/{prompt}', [ImageEditController::class, 'checkStatus'])->name('status');
		});
		// END MODIFICATION

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
			Route::post('/proxy-image', [ImageEditorController::class, 'proxyImage'])->name('proxy');
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
			// START MODIFICATION: Add delete route for generated covers
			Route::delete('/generated/{cover}', [AlbumCoverController::class, 'destroyGeneratedCover'])->name('generated.destroy');
			// END MODIFICATION

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
			Route::get('/', [StoryController::class, 'index'])->name('index');
			Route::get('/create', [CreateStoryController::class, 'create'])->name('create');
			Route::post('/', [CreateStoryController::class, 'store'])->name('store');
			Route::get('/{story}/edit', [StoryController::class, 'edit'])->name('edit');
			Route::put('/{story}', [StoryController::class, 'update'])->name('update');
			Route::delete('/{story}', [StoryController::class, 'destroy'])->name('destroy');

			// START MODIFICATION: Add route for cloning a story.
			Route::post('/{story}/clone', [StoryController::class, 'clone'])->name('clone');
			// END MODIFICATION

			Route::get('read/{story}', [StoryController::class, 'show'])->name('show');
			// START MODIFICATION: Add route for the text view.
			Route::get('/{story}/text-view', [StoryController::class, 'textView'])->name('text-view');
			// END MODIFICATION

			// MODIFIED: AI Story Creation Wizard Routes
			Route::get('/create/ai', [CreateStoryController::class, 'createWithAiStep1'])->name('create-ai.step1');
			Route::get('/create/ai/step2/{story}', [CreateStoryController::class, 'createWithAiStep2'])->name('create-ai.step2');
			Route::get('/create/ai/step3/{story}', [CreateStoryController::class, 'createWithAiStep3'])->name('create-ai.step3');

			// MODIFIED: AI Generation POST routes are now split into generate (AJAX) and store (Form Submit)
			// Step 1
			Route::post('/ai-generate/content', [CreateStoryController::class, 'generateContent'])->name('ai-generate.content'); // AJAX
			Route::post('/ai-store/content', [CreateStoryController::class, 'storeContent'])->name('ai-store.content'); // Form submit to next step

			// Step 2
			Route::post('/{story}/ai-generate/entities', [CreateStoryController::class, 'generateEntities'])->name('ai-generate.entities'); // AJAX
			Route::post('/{story}/ai-store/entities', [CreateStoryController::class, 'storeEntities'])->name('ai-store.entities'); // Form submit to next step

			// Step 3
			Route::post('/ai-generate/description', [CreateStoryController::class, 'generateDescription'])->name('ai-generate.description'); // AJAX
			Route::post('/ai-store/description', [CreateStoryController::class, 'storeDescription'])->name('ai-store.description'); // NEW: AJAX to save a single description

			Route::post('/rewrite-text', [StoryController::class, 'rewriteText'])->name('rewrite-text');
			Route::post('/rewrite-asset-description', [StoryController::class, 'rewriteAssetDescription'])->name('rewrite-asset-description');
			Route::post('/generate-image-prompt', [StoryController::class, 'generateImagePrompt'])->name('generate-image-prompt');
			Route::post('/generate-character-image-prompt', [StoryController::class, 'generateCharacterImagePrompt'])->name('generate-character-image-prompt');
			Route::post('/generate-place-image-prompt', [StoryController::class, 'generatePlaceImagePrompt'])->name('generate-place-image-prompt');

			Route::put('/{story}/pages/{storyPage}/insert-above', [StoryController::class, 'insertPageAbove'])->name('pages.insert-above');
			Route::put('/{story}/pages/{storyPage}/insert-below', [StoryController::class, 'insertPageBelow'])->name('pages.insert-below');

			Route::prefix('pages/{storyPage}')->name('pages.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generate'])->name('generate-image');
				Route::post('/generate-image-v2', [StoryImageController::class, 'generateV2'])->name('generate-image-v2'); // START MODIFICATION
				Route::get('/image-status', [StoryImageController::class, 'checkStatus'])->name('image-status');
				Route::post('/generate-dictionary', [StoryController::class, 'generateDictionaryForPage'])->name('generate-dictionary');
			});

			Route::prefix('characters/{character}')->name('characters.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generateForCharacter'])->name('generate-image');
				Route::get('/image-status', [StoryImageController::class, 'checkCharacterStatus'])->name('image-status');
			});

			Route::prefix('places/{place}')->name('places.')->group(function () {
				Route::post('/generate-image', [StoryImageController::class, 'generateForPlace'])->name('generate-image');
				Route::get('/image-status', [StoryImageController::class, 'checkPlaceStatus'])->name('image-status');
			});

			Route::get('/{story}/characters', [StoryController::class, 'characters'])->name('characters');
			Route::post('/{story}/characters', [StoryController::class, 'updateCharacters'])->name('characters.update');
			Route::get('/{story}/places', [StoryController::class, 'places'])->name('places');
			Route::post('/{story}/places', [StoryController::class, 'updatePlaces'])->name('places.update');

			Route::get('/{story}/quiz', [QuizController::class, 'quiz'])->name('quiz');
			Route::post('/{story}/quiz/generate', [QuizController::class, 'generateQuiz'])->name('quiz.generate');
			Route::post('/{story}/quiz', [QuizController::class, 'updateQuiz'])->name('quiz.update');

			Route::get('/{story}/pdf/setup', [StoryPdfController::class, 'setup'])->name('pdf.setup');
			Route::post('/{story}/pdf/generate', [StoryPdfController::class, 'generate'])->name('pdf.generate');
		});

		Route::get('/assets/fonts/{filename}', [StoryPdfController::class, 'serveFont'])->name('assets.font');
		Route::get('/assets/wallpapers/{filename}', [StoryPdfController::class, 'serveWallpaper'])->name('assets.wallpaper');
		Route::get('/assets/logos/{filename}', [StoryPdfController::class, 'serveLogo'])->name('assets.logo');
		Route::get('/assets/stickers/{filename}', [StoryPdfController::class, 'serveSticker'])->name('assets.sticker');

		Route::prefix('admin/upscale-settings')->name('upscale-settings.')->group(function () {
			Route::get('/', [UpscaleSettingController::class, 'index'])->name('index');
			Route::post('/update', [UpscaleSettingController::class, 'update'])->name('update');
		});
	});
