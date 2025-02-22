<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use App\Models\Prompt;
	use Illuminate\Http\Request;

	class PromptApiController extends Controller
	{
		public function getPendingPrompts()
		{
			$prompts = Prompt::where('render_status', 0)
				->orderBy('id', 'desc')
				->limit(4)
				->get();

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
			$count = \App\Models\Prompt::where('render_status', 0)->count();
			return response()->json(['count' => $count]);
		}
	}
