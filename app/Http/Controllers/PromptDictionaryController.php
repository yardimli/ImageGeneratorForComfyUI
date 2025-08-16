<?php

	namespace App\Http\Controllers;

	use App\Http\Controllers\LlmController;
	use App\Models\Prompt;
	use App\Models\PromptDictionaryEntry;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;

	class PromptDictionaryController extends Controller
	{
		/**
		 * Display the prompt dictionary grid view. // MODIFIED
		 */
		public function grid(LlmController $llmController) // NEW METHOD
		{
			$entries = PromptDictionaryEntry::where('user_id', auth()->id())
				->orderBy('name', 'asc')
				->get();

			// Fetch models for the AI modal.
			try {
				$modelsResponse = $llmController->getModels();
				$models = collect($modelsResponse['data'] ?? [])->sortBy('name')->all();
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Prompt Dictionary Grid: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator.');
			}

			return view('prompt-dictionary.grid', compact('entries', 'models'));
		}

		/**
		 * Display the prompt dictionary management page.
		 */
		public function edit(Request $request, LlmController $llmController) // MODIFIED: Added Request
		{
			// MODIFICATION START: Add filtering logic
			$query = PromptDictionaryEntry::where('user_id', auth()->id());

			if ($request->has('entry_id')) {
				$query->where('id', $request->input('entry_id'));
			}

			$entries = $query->latest()->get();
			// MODIFICATION END

			// Attach prompt data (for upscaling status) to each entry that has an image.
			foreach ($entries as $entry) {
				$entry->prompt_data = null;
				if ($entry->id && !empty($entry->image_path)) {
					$entry->prompt_data = Prompt::where('filename', $entry->image_path)
						->select('id', 'upscale_status', 'upscale_url', 'filename')
						->first();
				}
			}

			// Fetch models for the AI modals.
			try {
				$modelsResponse = $llmController->getModels();
				$models = collect($modelsResponse['data'] ?? [])->sortBy('name')->all();
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Prompt Dictionary: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator.');
			}

			// Define available image generation models.
			$imageModels = [
				['id' => 'schnell', 'name' => 'Schnell'],
				['id' => 'dev', 'name' => 'Dev'],
				['id' => 'outpaint', 'name' => 'Outpaint'],
				['id' => 'minimax', 'name' => 'MiniMax'],
				['id' => 'minimax-expand', 'name' => 'MiniMax Expand'],
				['id' => 'imagen3', 'name' => 'Imagen 3'],
				['id' => 'aura-flow', 'name' => 'Aura Flow'],
				['id' => 'ideogram-v2a', 'name' => 'Ideogram v2a'],
				['id' => 'luma-photon', 'name' => 'Luma Photon'],
				['id' => 'recraft-20b', 'name' => 'Recraft 20b'],
				['id' => 'fal-ai/qwen-image', 'name' => 'Fal Qwen Image'],
			];

			return view('prompt-dictionary.edit', compact('entries', 'models', 'imageModels'));
		}

		/**
		 * Update the dictionary entries for the user.
		 */
		public function update(Request $request)
		{
			$validated = $request->validate([
				'entries' => 'nullable|array',
				'entries.*.id' => 'nullable|integer|exists:prompt_dictionary_entries,id',
				'entries.*.name' => 'required|string|max:255',
				'entries.*.description' => 'nullable|string',
				'entries.*.image_prompt' => 'nullable|string',
				'entries.*.image_path' => 'nullable|string|max:2048',
			]);

			DB::transaction(function () use ($validated) {
				$incomingIds = [];
				if (isset($validated['entries'])) {
					foreach ($validated['entries'] as $entryData) {
						$values = [
							'user_id' => auth()->id(),
							'name' => $entryData['name'],
							'description' => $entryData['description'] ?? null,
							'image_prompt' => $entryData['image_prompt'] ?? null,
							'image_path' => $entryData['image_path'] ?? null,
						];
						$entry = PromptDictionaryEntry::updateOrCreate(['id' => $entryData['id'] ?? null, 'user_id' => auth()->id()], $values);
						$incomingIds[] = $entry->id;
					}
				}
				// Delete any entries that were removed on the frontend.
				PromptDictionaryEntry::where('user_id', auth()->id())->whereNotIn('id', $incomingIds)->delete();
			});

			return redirect()->route('prompt-dictionary.grid')->with('success', 'Dictionary updated successfully!'); // MODIFIED: Redirect to grid
		}

		/**
		 * Rewrite an entry's description using AI.
		 */
		public function rewriteDescription(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$response = $llmController->callLlmSync(
					$validated['prompt'],
					$validated['model'],
					'AI Dictionary Description Rewrite',
					0.7,
					'json_object'
				);

				$rewrittenText = $response['rewritten_text'] ?? null;

				if (!$rewrittenText) {
					Log::error('AI Dictionary Description Rewrite failed to return valid text.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'rewritten_text' => trim($rewrittenText)
				]);
			} catch (\Exception $e) {
				Log::error('AI Dictionary Description Rewrite Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while rewriting the description. Please try again.'], 500);
			}
		}

		/**
		 * Generate an image prompt for a dictionary entry using AI.
		 */
		public function generateImagePrompt(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$response = $llmController->callLlmSync(
					$validated['prompt'],
					$validated['model'],
					'AI Dictionary Image Prompt Generation',
					0.7,
					'json_object'
				);

				$generatedPrompt = $response['prompt'] ?? null;

				if (!$generatedPrompt) {
					Log::error("AI Dictionary Image Prompt Generation failed to return a valid prompt.", ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'prompt' => trim($generatedPrompt)
				]);
			} catch (\Exception $e) {
				Log::error("AI Dictionary Image Prompt Generation Failed: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the image prompt. Please try again.'], 500);
			}
		}

		/**
		 * Generate and save dictionary entries using AI. // MODIFIED
		 */
		public function generateEntries(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$response = $llmController->callLlmSync(
					$validated['prompt'],
					$validated['model'],
					'AI Dictionary Entry Generation',
					0.7,
					'json_object'
				);

				$generatedEntries = $response['entries'] ?? null;

				if (!$generatedEntries || !is_array($generatedEntries)) {
					Log::error('AI Dictionary Entry Generation failed to return a valid array.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				// START MODIFICATION: Save the generated entries to the database
				$savedEntries = [];
				DB::transaction(function () use ($generatedEntries, &$savedEntries) {
					foreach ($generatedEntries as $entryData) {
						if (!empty($entryData['name']) && !empty($entryData['description'])) {
							$savedEntries[] = PromptDictionaryEntry::create([
								'user_id' => auth()->id(),
								'name' => $entryData['name'],
								'description' => $entryData['description'],
							]);
						}
					}
				});
				// END MODIFICATION

				return response()->json([
					'success' => true,
					'entries' => $savedEntries // Return the newly created entries
				]);
			} catch (\Exception $e) {
				Log::error('AI Dictionary Entry Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating entries. Please try again.'], 500);
			}
		}
	}
