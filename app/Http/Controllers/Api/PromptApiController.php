<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use App\Http\Controllers\UpscaleAndNotesController;
	use App\Models\Prompt;
	use Illuminate\Http\Request;

	class PromptApiController extends Controller
	{
		public function getPendingPrompts()
		{
			// Retrieve 4 prompts for each render_status

			$userIds0 = Prompt::where('render_status', 0)->distinct('user_id')->pluck('user_id');
			$userIds1 = Prompt::where('render_status', 1)->distinct('user_id')->pluck('user_id');
			$userIds3 = Prompt::where('render_status', 3)->distinct('user_id')->pluck('user_id');

			$prompts = collect();

			// For render_status 0
			foreach ($userIds0 as $userId) {
				$userPrompts = Prompt::where('render_status', 0)
					->where('user_id', $userId)
					->orderBy('id', 'desc')
					->limit(3)
					->get();
				$prompts = $prompts->merge($userPrompts);
			}

			// For render_status 1
			foreach ($userIds1 as $userId) {
				$userPrompts = Prompt::where('render_status', 1)
					->where('user_id', $userId)
					->orderBy('id', 'desc')
					->limit(3)
					->get();
				$prompts = $prompts->merge($userPrompts);
			}

			// For render_status 3
			foreach ($userIds3 as $userId) {
				$userPrompts = Prompt::where('render_status', 3)
					->where('user_id', $userId)
					->orderBy('id', 'desc')
					->limit(3)
					->get();
				$prompts = $prompts->merge($userPrompts);
			}

			return response()->json([
				'success' => true,
				'prompts' => $prompts
			]);
		}

		public function updateFilename(Request $request)
		{
			$validated = $request->validate([
				'id' => 'required|integer',
				'filename' => 'required|string'
			]);

			$prompt = Prompt::find($validated['id']);

			if (!$prompt) {
				return response()->json([
					'success' => false,
					'message' => 'Prompt not found'
				], 404);
			}

			$prompt->filename = $validated['filename'];
			$prompt->render_status = 2;
			$prompt->save();

			return response()->json([
				'success' => true,
				'message' => 'Filename updated successfully'
			]);
		}

		public function updateRenderStatus(Request $request)
		{
			$prompt = Prompt::findOrFail($request->id);
			$prompt->render_status = $request->status;
			$prompt->save();

			return response()->json(['success' => true]);
		}

		public function getQueueCount()
		{
			// Get pending renders count
			$pendingCount = Prompt::where('render_status', 0)->count();

			// Check for pending upscales
			$pendingUpscales = Prompt::where('upscale_status', 1)
				->whereNotNull('upscale_prediction_id')
				->get();

			// Process pending upscales
			foreach ($pendingUpscales as $prompt) {
				$upscaleController = new UpscaleAndNotesController();
				$result = $upscaleController->checkUpscaleStatusOperation($prompt, $prompt->upscale_prediction_id);

				// If upscale completed or failed, don't count it in pending queue
				if (isset($result['upscale_result']) || isset($result['error'])) {
					continue;
				}

				// Add to pending count if still processing
				$pendingCount++;
			}

			return response()->json([
				'count' => $pendingCount
			]);
		}
	}
