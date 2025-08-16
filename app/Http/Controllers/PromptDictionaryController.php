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
		// START MODIFICATION: Added search method for dictionary popup
		/**
		 * Search for dictionary entries for the autocomplete feature.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function search(Request $request)
		{
			$term = $request->input('term', '');

			$entries = PromptDictionaryEntry::where('user_id', auth()->id())
				->where('name', 'LIKE', '%' . $term . '%')
				->orderBy('name', 'asc')
				->limit(10)
				->get(['id', 'name', 'description', 'image_prompt', 'image_path']);

			// Add a placeholder image if image_path is empty
			$entries->transform(function ($entry) {
				$entry->image_path = $entry->image_path ?: 'https://via.placeholder.com/50?text=N/A';
				return $entry;
			});

			return response()->json($entries);
		}
		// END MODIFICATION

		/**
		 * Display the prompt dictionary grid view.
		 */
		public function grid(LlmController $llmController)
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
		 * Display the page to edit or create a single prompt dictionary entry.
		 */
		public function edit(Request $request, LlmController $llmController)
		{
			if ($request->has('entry_id')) {
				$entry = PromptDictionaryEntry::where('user_id', auth()->id())
					->findOrFail($request->input('entry_id'));
			} else {
				$entry = new PromptDictionaryEntry();
			}

			// Attach prompt data (for upscaling status) if it's an existing entry with an image.
			$entry->prompt_data = null;
			if ($entry->exists && !empty($entry->image_path)) {
				$entry->prompt_data = Prompt::where('filename', $entry->image_path)
					->select('id', 'upscale_status', 'upscale_url', 'filename')
					->first();
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

			return view('prompt-dictionary.edit', compact('entry', 'models', 'imageModels'));
		}

		/**
		 * Create or update a single dictionary entry for the user.
		 */
		public function update(Request $request)
		{
			$validated = $request->validate([
				'id' => 'nullable|integer|exists:prompt_dictionary_entries,id,user_id,' . auth()->id(),
				'name' => 'required|string|max:255',
				'description' => 'nullable|string',
				'image_prompt' => 'nullable|string',
				'image_path' => 'nullable|string|max:2048',
			]);

			$id = $request->input('id');
			$message = $id ? 'Entry updated successfully!' : 'Entry created successfully!';

			PromptDictionaryEntry::updateOrCreate(
				['id' => $id, 'user_id' => auth()->id()],
				$validated
			);

			return redirect()->route('prompt-dictionary.edit', ['entry_id' => $id])
				->with('success', $message);
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

		// START MODIFICATION: Replaced generateEntries with two separate methods for preview and storing.
		/**
		 * Generate a preview of dictionary entries using AI without saving them.
		 */
		public function previewGeneratedEntries(Request $request, LlmController $llmController)
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

				// Filter out any potentially empty entries from the AI response
				$filteredEntries = array_filter($generatedEntries, function ($entry) {
					return !empty($entry['name']) && !empty($entry['description']);
				});

				return response()->json([
					'success' => true,
					'entries' => array_values($filteredEntries) // Re-index array
				]);
			} catch (\Exception $e) {
				Log::error('AI Dictionary Entry Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating entries. Please try again.'], 500);
			}
		}

		/**
		 * Store a batch of generated dictionary entries.
		 */
		public function storeGeneratedEntries(Request $request)
		{
			$validated = $request->validate([
				'entries' => 'required|array',
				'entries.*.name' => 'required|string|max:255',
				'entries.*.description' => 'required|string',
			]);

			try {
				DB::transaction(function () use ($validated) {
					foreach ($validated['entries'] as $entryData) {
						PromptDictionaryEntry::create([
							'user_id' => auth()->id(),
							'name' => $entryData['name'],
							'description' => $entryData['description'],
						]);
					}
				});

				return response()->json([
					'success' => true,
					'message' => 'Entries saved successfully!'
				]);
			} catch (\Exception $e) {
				Log::error('AI Dictionary Entry Storing Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while saving the entries. Please try again.'], 500);
			}
		}
		// END MODIFICATION
	}
