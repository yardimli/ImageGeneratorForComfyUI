<?php

	use App\Http\Controllers\Api\PromptApiController;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Route;

	/*
	|--------------------------------------------------------------------------
	| API Routes
	|--------------------------------------------------------------------------
	|
	| Here is where you can register API routes for your application. These
	| routes are loaded by the RouteServiceProvider and all of them will
	| be assigned to the "api" middleware group. Make something great!
	|
	*/

	Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
		return $request->user();
	});

	Route::get('/prompts/pending', [PromptApiController::class, 'getPendingPrompts']);
	Route::post('/prompts/update-filename', [PromptApiController::class, 'updateFilename']);
	Route::post('/prompts/update-status', [PromptApiController::class, 'updateRenderStatus']);
	Route::get('/prompts/queue-count', [PromptApiController::class, 'getQueueCount']);
	Route::get('/prompts/upscale-queue-count', [UpscaleAndNotesController::class, 'getUpscaleQueueCount']);


