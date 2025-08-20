<?php

	namespace App\Http\Controllers;

	use App\Http\Controllers\LlmController;
	use App\Models\Prompt;
	use App\Models\Story;
	use App\Models\StoryDictionary;
	use App\Models\StoryPage;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\ValidationException;

	class DictionaryController extends Controller
	{
		// START MODIFICATION: Add methods for dictionary management.
		/**
		 * Show the dictionary management page for a story.
		 *
		 * @param Story $story
		 * @param LlmController $llmController
		 * @return \Illuminate\View\View
		 */
		public function dictionary(Story $story, LlmController $llmController)
		{
			// MODIFICATION: Load pages and dictionary entries, sorting dictionary by word.
			$story->load(['pages', 'dictionary' => function ($query) {
				$query->orderBy('word', 'asc');
			}]);

			// Prepare the prompt text
			$storyText = $story->pages->pluck('story_text')->implode("\n\n");

			// MODIFICATION: Get existing words to avoid duplicates in AI generation.
			$existingWords = $story->dictionary->pluck('word')->implode(', ');
			$existingWordsPrompt = !empty($existingWords) ? "The following words already have entries, so do not add them again: {$existingWords}." : '';

			$initialUserRequest = "Create 10 dictionary entries for the story above. Explain the words in a manner that is understandable for the story's level. {$existingWordsPrompt}";
			$promptText = "Story Title: {$story->title}\nLevel: {$story->level}\n\n---\n\n{$storyText}\n\n---\n\n{$initialUserRequest}";

			// Fetch models for the AI generator
			try {
				$modelsResponse = $llmController->getModels();
				// MODIFICATION: Process models to add variants for image/reasoning support.
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Story Dictionary: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the dictionary generator.');
			}

			return view('story.dictionary', compact('story', 'promptText', 'models'));
		}

		/**
		 * Update the dictionary entries for a story.
		 *
		 * @param Request $request
		 * @param Story $story
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function updateDictionary(Request $request, Story $story)
		{
			$validated = $request->validate([
				'dictionary' => 'nullable|array',
				'dictionary.*.word' => 'required_with:dictionary.*.explanation|string|max:255',
				// MODIFICATION: Corrected validation rule to be co-dependent with the 'word' field.
				'dictionary.*.explanation' => 'required_with:dictionary.*.word|string',
			]);

			DB::transaction(function () use ($story, $validated) {
				$story->dictionary()->delete();

				if (isset($validated['dictionary'])) {
					foreach ($validated['dictionary'] as $entry) {
						// Ensure we don't save empty rows that might pass validation
						if (!empty($entry['word']) && !empty($entry['explanation'])) {
							$story->dictionary()->create([
								'word' => $entry['word'],
								'explanation' => $entry['explanation'],
							]);
						}
					}
				}
			});

			return redirect()->route('stories.dictionary', $story)->with('success', 'Dictionary updated successfully!');
		}

		/**
		 * Generate dictionary entries for a story using AI.
		 *
		 * @param Request $request
		 * @param Story $story
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateDictionary(Request $request, Story $story, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$fullPrompt = $this->buildDictionaryPrompt($validated['prompt']);
				$response = $llmController->callLlmSync(
					$fullPrompt,
					$validated['model'],
					'AI Story Dictionary Generation',
					0.7,
					'json_object'
				);

				$dictionaryEntries = $response['dictionary'] ?? null;

				if (!is_array($dictionaryEntries)) {
					Log::error('AI Dictionary Generation failed to return a valid array.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'dictionary' => $dictionaryEntries
				]);
			} catch (\Exception $e) {
				Log::error('AI Dictionary Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the dictionary. Please try again.'], 500);
			}
		}

		/**
		 * Builds the prompt for the LLM to generate a story dictionary.
		 *
		 * @param string $userPrompt
		 * @return string
		 */
		private function buildDictionaryPrompt(string $userPrompt): string
		{
			$jsonStructure = <<<'JSON'
{
  "dictionary": [
    {
      "word": "The word from the text",
      "explanation": "A simple explanation of the word, tailored to the CEFR level."
    }
  ]
}
JSON;

			return <<<PROMPT
You are an expert linguist and teacher. Based on the following story text and user request, create a list of dictionary entries.
For each entry, provide the word and a simple explanation suitable for the specified language level mentioned in the text.

---
{$userPrompt}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{$jsonStructure}

Now, generate the dictionary based on the provided text and user request.
PROMPT;
		}
	}
