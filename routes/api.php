<?php

	use App\Http\Controllers\Api\PromptApiController;
	use App\Http\Controllers\UpscaleAndNotesController;
	use Illuminate\Http\Request;
	use Illuminate\Routing\Middleware\ThrottleRequests;
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

	Route::get('/prompts/pending', [PromptApiController::class, 'getPendingPrompts'])->withoutMiddleware([ThrottleRequests::class]);
	Route::post('/prompts/update-filename', [PromptApiController::class, 'updateFilename'])->withoutMiddleware([ThrottleRequests::class]);
	Route::post('/prompts/update-status', [PromptApiController::class, 'updateRenderStatus'])->withoutMiddleware([ThrottleRequests::class]);
	Route::get('/prompts/queue-count', [PromptApiController::class, 'getQueueCount'])->withoutMiddleware([ThrottleRequests::class]);


