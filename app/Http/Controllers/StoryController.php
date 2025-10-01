<?php

	namespace App\Http\Controllers;

	use App\Http\Controllers\LlmController;
	use App\Models\LlmPrompt;
	use App\Models\Prompt;
	use App\Models\Story;
	use App\Models\StoryCharacter;
	use App\Models\StoryPage;
	use App\Models\StoryPlace;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\ValidationException;
	use Exception;

	class StoryController extends Controller
	{
		// MODIFICATION START: Updated to load all models from JSON, using short names where available.
		/**
		 * Loads and formats the available image generation models for use in views.
		 *
		 * @return array
		 */
		protected function getAvailableImageModels(): array
		{
			try {
				$jsonString = file_get_contents(resource_path('text-to-image-models/models.json'));
				$allModels = json_decode($jsonString, true);
			} catch (Exception $e) {
				Log::error('Failed to load image models from JSON: ' . $e->getMessage());
				return [];
			}

			// This map defines which models get a "short name". Others will use their full name.
			$supportedModelsMap = [
				'schnell' => 'flux-1/schnell',
				'dev' => 'flux-1/dev',
				'minimax' => 'minimax/image-01',
				'imagen3' => 'imagen4/preview/ultra',
				'aura-flow' => 'aura-flow',
				'ideogram-v2a' => 'ideogram/v2a',
				'luma-photon' => 'luma-photon',
				'recraft-20b' => 'recraft-20b',
				'fal-ai/qwen-image' => 'qwen-image',
			];

			$viewModels = [];
			$foundModels = [];

			foreach ($allModels as $modelData) {
				$fullName = $modelData['name'];
				$shortName = array_search($fullName, $supportedModelsMap);

				// The ID for the form value. Use short name if it exists, otherwise full name.
				$id = ($shortName !== false) ? $shortName : $fullName;

				// The name for display in the dropdown.
				$displayNameBase = ($shortName !== false) ? $shortName : $fullName;
				$displayName = ucfirst(str_replace(['-', '_', '/'], ' ', $displayNameBase));
				if (isset($modelData['price'])) {
					$displayName .= " (\${$modelData['price']})";
				}

				$viewModels[] = [
					'id' => $id,
					'name' => $displayName,
				];

				if ($shortName === 'minimax') {
					$foundModels['minimax'] = true;
				}
			}

			// Manually add minimax-expand if minimax was found
			if (isset($foundModels['minimax'])) {
				$viewModels[] = [
					'id' => 'minimax-expand',
					'name' => 'Minimax Expand ($0.01)', // Price is hardcoded as it's a variant
				];
			}

			usort($viewModels, fn($a, $b) => strcmp($a['name'], $b['name']));
			return $viewModels;
		}
		// MODIFICATION END

		// MODIFICATION START: New method to generate SQL for cost calculation.
		/**
		 * Loads model prices from JSON and generates a SQL CASE statement for cost calculation.
		 *
		 * @return string
		 */
		private function getCostSqlCaseStatement(): string
		{
			try {
				$jsonString = file_get_contents(resource_path('text-to-image-models/models.json'));
				$allModels = json_decode($jsonString, true);

				if (json_last_error() !== JSON_ERROR_NONE) {
					Log::error('Failed to parse models.json for cost calculation: ' . json_last_error_msg());
					return '0.00'; // Fallback
				}

				// This map defines which models get a "short name".
				$supportedModelsMap = [
					'schnell' => 'flux-1/schnell',
					'dev' => 'flux-1/dev',
					'minimax' => 'minimax/image-01',
					'imagen3' => 'imagen4/preview/ultra',
					'aura-flow' => 'aura-flow',
					'ideogram-v2a' => 'ideogram/v2a',
					'luma-photon' => 'luma-photon',
					'recraft-20b' => 'recraft-20b',
					'fal-ai/qwen-image' => 'qwen-image',
				];

				$priceMap = [];
				// Map full names to prices
				foreach ($allModels as $modelData) {
					if (isset($modelData['name']) && isset($modelData['price'])) {
						$priceMap[$modelData['name']] = (float)$modelData['price'];
					}
				}

				// Map short names to prices
				foreach ($supportedModelsMap as $shortName => $fullName) {
					if (isset($priceMap[$fullName])) {
						$priceMap[$shortName] = $priceMap[$fullName];
					}
				}

				// Handle special cases like minimax-expand
				if (isset($priceMap['minimax/image-01'])) {
					$priceMap['minimax-expand'] = $priceMap['minimax/image-01'];
				}

				if (empty($priceMap)) {
					return '0.035';
				}

				$sql = 'CASE model ';
				foreach ($priceMap as $modelName => $price) {
					// Basic sanitization for model name
					$sanitizedModelName = str_replace("'", "''", $modelName);
					$sql .= "WHEN '{$sanitizedModelName}' THEN {$price} ";
				}
				$sql .= 'ELSE 0.035 END'; // Default cost for any other model not in the list

				return $sql;
			} catch (Exception $e) {
				Log::error('Failed to load image models from JSON for cost calculation: ' . $e->getMessage());
				return '0.00'; // Fallback
			}
		}
		// MODIFICATION END

		/**
		 * Display a listing of the user's stories.
		 */
		public function index()
		{
			// MODIFICATION START: Generate cost calculation dynamically from models.json
			$costSql = $this->getCostSqlCaseStatement();
			// MODIFICATION END

			// Eager load prompt counts and calculate cost via subquery.
			$stories = Story::with('user')
				->withCount(['pagePrompts', 'characterPrompts', 'placePrompts'])
				->addSelect([
					// MODIFICATION START: Use the dynamically generated SQL CASE statement.
					'image_cost' => Prompt::selectRaw("COALESCE(SUM({$costSql}), 0)")
						// MODIFICATION END
						->where(function ($query) {
							// Link prompts to the story through its pages
							$query->whereIn('story_page_id', function ($subQuery) {
								$subQuery->select('id')->from('story_pages')->whereColumn('story_pages.story_id', 'stories.id');
							})
								// Link prompts to the story through its characters
								->orWhereIn('story_character_id', function ($subQuery) {
									$subQuery->select('id')->from('story_characters')->whereColumn('story_characters.story_id', 'stories.id');
								})
								// Link prompts to the story through its places
								->orWhereIn('story_place_id', function ($subQuery) {
									$subQuery->select('id')->from('story_places')->whereColumn('story_places.story_id', 'stories.id');
								});
						})
				])
				->latest('updated_at')
				->paginate(15);
			return view('story.index', compact('stories'));
		}

		/**
		 * Display the specified story publicly.
		 *
		 * @param \App\Models\Story $story
		 * @return \Illuminate\View\View
		 */
		public function show(Story $story)
		{
			$story->load(['user', 'pages.characters', 'pages.places', 'pages.dictionary', 'characters', 'places']);
			return view('story.show', compact('story'));
		}

		/**
		 * START MODIFICATION: Add a new method to display the story as simple text.
		 *
		 * @param Story $story
		 * @return \Illuminate\View\View
		 */
		public function textView(Story $story)
		{
			// Eager load all necessary relationships to avoid N+1 query issues.
			$story->load(['pages', 'characters', 'places']);

			// Start building the text string.
			$textOutput = "Title: " . $story->title . "\n";
			$textOutput .= "========================================\n\n";

			$textOutput .= "Description:\n" . $story->short_description . "\n\n";

			// Add AI generation details if they exist.
			if ($story->initial_prompt) {
				$textOutput .= "AI Generation Details:\n";
				$textOutput .= "----------------------\n";
				$textOutput .= "Model Used: " . $story->model . "\n";
				$textOutput .= "Initial Prompt:\n" . $story->initial_prompt . "\n\n";
			}

			$textOutput .= "Story Pages:\n";
			$textOutput .= "========================================\n\n";
			foreach ($story->pages as $page) {
				$textOutput .= "--- Page " . $page->page_number . " ---\n";
				$textOutput .= $page->story_text . "\n\n";
			}

			$textOutput .= "Characters:\n";
			$textOutput .= "========================================\n\n";
			if ($story->characters->isEmpty()) {
				$textOutput .= "No characters defined.\n\n";
			} else {
				foreach ($story->characters as $character) {
					$textOutput .= "Name: " . $character->name . "\n";
					$textOutput .= "Description: " . $character->description . "\n\n";
				}
			}

			$textOutput .= "Places:\n";
			$textOutput .= "========================================\n\n";
			if ($story->places->isEmpty()) {
				$textOutput .= "No places defined.\n\n";
			} else {
				foreach ($story->places as $place) {
					$textOutput .= "Name: " . $place->name . "\n";
					$textOutput .= "Description: " . $place->description . "\n\n";
				}
			}

			// Pass the compiled text to a new view.
			return view('story.text-view', compact('story', 'textOutput'));
		}
		// END MODIFICATION

		/**
		 * Show the form for editing the specified story.
		 */
		public function edit(Story $story, LlmController $llmController)
		{
			$story->load(['pages.characters', 'pages.places', 'pages.dictionary', 'characters', 'places']);

			foreach ($story->pages as $page) {
				$page->prompt_data = null; // Initialize
				if ($page->id) {
					// Find the prompt data for the current image path.
					$page->prompt_data = Prompt::where('filename', $page->image_path)
						->select('id', 'upscale_status', 'upscale_url', 'filename')
						->first();
				}
			}

			// Fetch models for the AI prompt generator modal
			try {
				$modelsResponse = $llmController->getModels();
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (Exception $e) {
				Log::error('Failed to fetch LLM models for Story Editor: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator. The feature will be unavailable.');
			}

			// MODIFICATION START: Replaced hardcoded model list with a dynamic one from the helper method.
			$imageModels = $this->getAvailableImageModels();
			// MODIFICATION END

			//  Fetch full prompt templates for JS, including the new dictionary prompt.
			$promptTemplates = LlmPrompt::whereIn('name', [
				'story.page.rewrite',
				'story.page.image_prompt',
				'story.page.dictionary.generate' // Add new dictionary prompt
			])->get(['name', 'system_prompt', 'options'])->keyBy('name');


			return view('story.edit', compact('story', 'models', 'imageModels', 'promptTemplates'));
		}

		/**
		 * Update the specified story in storage.
		 */
		public function update(Request $request, Story $story)
		{
			//  Add validation rules for dictionary entries.
			$validated = $request->validate([
				'title' => 'required|string|max:255',
				'short_description' => 'nullable|string',
				'level' => 'required|string|max:50',
				'pages' => 'nullable|array',
				'pages.*.id' => 'nullable|integer|exists:story_pages,id',
				'pages.*.story_text' => 'nullable|string',
				'pages.*.image_prompt' => 'nullable|string',
				'pages.*.image_path' => 'nullable|string|max:2048',
				'pages.*.characters' => 'nullable|array',
				'pages.*.characters.*' => 'integer|exists:story_characters,id',
				'pages.*.places' => 'nullable|array',
				'pages.*.places.*' => 'integer|exists:story_places,id',
				'pages.*.dictionary' => 'nullable|array',
				'pages.*.dictionary.*.word' => 'required_with:pages.*.dictionary.*.explanation|string|max:255',
				'pages.*.dictionary.*.explanation' => 'required_with:pages.*.dictionary.*.word|string',
			]);


			DB::transaction(function () use ($story, $validated, $request) {
				$story->update([
					'title' => $validated['title'],
					'short_description' => $validated['short_description'],
					'level' => $validated['level'],
				]);

				$pageNumber = 1;
				$incomingPageIds = [];

				if (isset($validated['pages'])) {
					foreach ($validated['pages'] as $pageData) {
						$pageValues = [
							'story_id' => $story->id,
							'page_number' => $pageNumber++,
							'story_text' => $pageData['story_text'] ?? null,
							'image_prompt' => $pageData['image_prompt'] ?? null,
							'image_path' => $pageData['image_path'] ?? null,
						];

						if (isset($pageData['id'])) {
							$page = StoryPage::find($pageData['id']);
							if ($page && $page->story_id === $story->id) {
								$page->update($pageValues);
								$incomingPageIds[] = $page->id;
							}
						} else {
							$page = StoryPage::create($pageValues);
							$incomingPageIds[] = $page->id;
						}

						$page->characters()->sync($pageData['characters'] ?? []);
						$page->places()->sync($pageData['places'] ?? []);

						//  Save dictionary entries for the page.
						// A "delete and recreate" strategy is simple and effective here.
						$page->dictionary()->delete();
						if (isset($pageData['dictionary'])) {
							foreach ($pageData['dictionary'] as $dictData) {
								if (!empty($dictData['word']) && !empty($dictData['explanation'])) {
									$page->dictionary()->create([
										'word' => $dictData['word'],
										'explanation' => $dictData['explanation'],
									]);
								}
							}
						}
					}
				}
				$story->pages()->whereNotIn('id', $incomingPageIds)->delete();
			});


			return redirect()->route('stories.edit', $story)->with('success', 'Story updated successfully!');
		}

		/**
		 * Remove the specified story from storage.
		 */
		public function destroy(Story $story)
		{
			$story->delete();
			return redirect()->route('stories.index')->with('success', 'Story deleted successfully.');
		}

		/**
		 * START MODIFICATION: Add a new method to clone a story.
		 * Clones a story and all of its related content.
		 *
		 * @param Story $story
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function clone(Story $story)
		{
			try {
				DB::transaction(function () use ($story) {
					// 1. Clone the Story model itself.
					$newStory = $story->replicate();
					$newStory->title = $story->title . ' (Copy)';
					$newStory->created_at = now();
					$newStory->updated_at = now();
					$newStory->save();

					// 2. Create maps to track old IDs to new IDs for relational integrity.
					$oldToNewCharacterIdMap = [];
					$oldToNewPlaceIdMap = [];

					// 3. Clone characters and populate the character ID map.
					foreach ($story->characters as $character) {
						$newCharacter = $character->replicate();
						$newCharacter->story_id = $newStory->id;
						$newCharacter->save();
						$oldToNewCharacterIdMap[$character->id] = $newCharacter->id;
					}

					// 4. Clone places and populate the place ID map.
					foreach ($story->places as $place) {
						$newPlace = $place->replicate();
						$newPlace->story_id = $newStory->id;
						$newPlace->save();
						$oldToNewPlaceIdMap[$place->id] = $newPlace->id;
					}

					// 5. Clone pages and their direct and pivot relationships.
					foreach ($story->pages as $page) {
						$newPage = $page->replicate();
						$newPage->story_id = $newStory->id;
						$newPage->save();

						// 5a. Clone dictionary entries belonging to the page.
						foreach ($page->dictionary as $entry) {
							$newEntry = $entry->replicate();
							$newEntry->story_page_id = $newPage->id;
							$newEntry->save();
						}

						// 5b. Sync character relationships for the new page using the ID map.
						$characterIdsToSync = $page->characters->pluck('id')->map(function ($oldId) use ($oldToNewCharacterIdMap) {
							return $oldToNewCharacterIdMap[$oldId] ?? null;
						})->filter();

						if ($characterIdsToSync->isNotEmpty()) {
							$newPage->characters()->sync($characterIdsToSync);
						}

						// 5c. Sync place relationships for the new page using the ID map.
						$placeIdsToSync = $page->places->pluck('id')->map(function ($oldId) use ($oldToNewPlaceIdMap) {
							return $oldToNewPlaceIdMap[$oldId] ?? null;
						})->filter();

						if ($placeIdsToSync->isNotEmpty()) {
							$newPage->places()->sync($placeIdsToSync);
						}
					}

					// 6. Clone quiz entries.
					foreach ($story->quiz as $quizItem) {
						$newQuizItem = $quizItem->replicate();
						$newQuizItem->story_id = $newStory->id;
						$newQuizItem->save();
					}
				});
			} catch (Exception $e) {
				Log::error('Failed to clone story: ' . $e->getMessage());
				return redirect()->route('stories.index')->with('error', 'An error occurred while cloning the story.');
			}

			return redirect()->route('stories.index')->with('success', 'Story cloned successfully.');
		}
		// END MODIFICATION

		/**
		 * Inserts an empty page above the specified page.
		 *
		 * @param Story $story
		 * @param StoryPage $storyPage
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function insertPageAbove(Story $story, StoryPage $storyPage)
		{
			if ($storyPage->story_id !== $story->id) {
				abort(404);
			}
			$this->insertEmptyPage($story, $storyPage->page_number);
			return redirect()->route('stories.edit', $story)->with('success', 'New page inserted successfully.');
		}

		/**
		 * Inserts an empty page below the specified page.
		 *
		 * @param Story $story
		 * @param StoryPage $storyPage
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function insertPageBelow(Story $story, StoryPage $storyPage)
		{
			if ($storyPage->story_id !== $story->id) {
				abort(404);
			}
			$this->insertEmptyPage($story, $storyPage->page_number + 1);
			return redirect()->route('stories.edit', $story)->with('success', 'New page inserted successfully.');
		}

		/**
		 * Helper function to insert a page and re-order subsequent pages.
		 *
		 * @param Story $story
		 * @param int $pageNumber
		 */
		private function insertEmptyPage(Story $story, int $pageNumber): void
		{
			DB::transaction(function () use ($story, $pageNumber) {
				// Increment page numbers of subsequent pages.
				$story->pages()->where('page_number', '>=', $pageNumber)->increment('page_number');

				// Create the new empty page.
				StoryPage::create([
					'story_id' => $story->id,
					'page_number' => $pageNumber,
					'story_text' => null,
				]);
			});
		}

		/**
		 * Rewrite story text using AI.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function rewriteText(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$response = $llmController->callLlmSync(
					$validated['prompt'],
					$validated['model'],
					'AI Story Text Rewrite',
					0.7,
					'json_object'
				);

				$rewrittenText = $response['rewritten_text'] ?? null;

				if (!$rewrittenText) {
					Log::error('AI Text Rewrite failed to return a valid text.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'rewritten_text' => trim($rewrittenText)
				]);
			} catch (Exception $e) {
				Log::error('AI Text Rewrite Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while rewriting the text. Please try again.'], 500);
			}
		}

		/**
		 * Rewrite an asset's (character/place) description using AI.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function rewriteAssetDescription(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$response = $llmController->callLlmSync(
					$validated['prompt'],
					$validated['model'],
					'AI Asset Description Rewrite',
					0.7,
					'json_object'
				);

				$rewrittenText = $response['rewritten_text'] ?? null;

				if (!$rewrittenText) {
					Log::error('AI Asset Description Rewrite failed to return valid text.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'rewritten_text' => trim($rewrittenText)
				]);
			} catch (Exception $e) {
				Log::error('AI Asset Description Rewrite Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while rewriting the description. Please try again.'], 500);
			}
		}

		/**
		 * Generate an image prompt for a story page using AI.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
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
					'AI Image Prompt Generation',
					0.7,
					'json_object'
				);

				$generatedPrompt = $response['prompt'] ?? null;

				if (!$generatedPrompt) {
					Log::error('AI Image Prompt Generation failed to return a valid prompt.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'prompt' => trim($generatedPrompt)
				]);
			} catch (Exception $e) {
				Log::error('AI Image Prompt Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the image prompt. Please try again.'], 500);
			}
		}

		//  Add method to generate dictionary entries for a single page.
		/**
		 * Generate dictionary entries for a story page using AI.
		 *
		 * @param Request $request
		 * @param StoryPage $storyPage
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateDictionaryForPage(Request $request, StoryPage $storyPage, LlmController $llmController)
		{
			//  Add 'nullable' to the 'existingWords' validation rule.
			$validated = $request->validate([
				'userRequest' => 'required|string',
				'existingWords' => 'present|nullable|string',
				'model' => 'required|string',
			]);


			try {
				$llmPrompt = LlmPrompt::where('name', 'story.page.dictionary.generate')->firstOrFail();

				// The user prompt template contains placeholders for the user's request, existing words, and the page text.
				$systemPromptContent = str_replace(
					['{userRequest}', '{existingWords}', '{pageText}'],
					[$validated['userRequest'], $validated['existingWords'] ?? '', $storyPage->story_text],
					$llmPrompt->system_prompt
				);

				$response = $llmController->callLlmSync(
					$systemPromptContent,
					$validated['model'],
					'AI Page Dictionary Generation',
					0.7,
					'json_object'
				);

				$dictionaryEntries = $response['dictionary'] ?? null;

				if (!is_array($dictionaryEntries)) {
					Log::error('AI Page Dictionary Generation failed to return a valid array.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'dictionary' => $dictionaryEntries
				]);
			} catch (Exception $e) {
				Log::error('AI Page Dictionary Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the dictionary. Please try again.'], 500);
			}
		}


		/**
		 * Generate an image prompt for a story character using AI.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateCharacterImagePrompt(Request $request, LlmController $llmController)
		{
			return $this->generateAssetImagePrompt($request, $llmController, 'character');
		}

		/**
		 * Generate an image prompt for a story place using AI.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generatePlaceImagePrompt(Request $request, LlmController $llmController)
		{
			return $this->generateAssetImagePrompt($request, $llmController, 'place');
		}

		/**
		 * Generic handler for generating asset (character/place) image prompts.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @param string $assetType
		 * @return \Illuminate\Http\JsonResponse
		 */
		private function generateAssetImagePrompt(Request $request, LlmController $llmController, string $assetType)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$response = $llmController->callLlmSync(
					$validated['prompt'],
					$validated['model'],
					'AI Asset Image Prompt Generation',
					0.7,
					'json_object'
				);

				$generatedPrompt = $response['prompt'] ?? null;

				if (!$generatedPrompt) {
					Log::error("AI {$assetType} Image Prompt Generation failed to return a valid prompt.", ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'prompt' => trim($generatedPrompt)
				]);
			} catch (Exception $e) {
				Log::error("AI {$assetType} Image Prompt Generation Failed: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the image prompt. Please try again.'], 500);
			}
		}

		/**
		 * Show the character management page for a story.
		 */
		public function characters(Story $story, LlmController $llmController)
		{
			$story->load('characters');

			foreach ($story->characters as $character) {
				$character->prompt_data = null;
				if ($character->id && !empty($character->image_path)) {
					$character->prompt_data = Prompt::where('filename', $character->image_path)
						->select('id', 'upscale_status', 'upscale_url', 'filename')
						->first();
				}
			}

			try {
				$modelsResponse = $llmController->getModels();
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (Exception $e) {
				Log::error('Failed to fetch LLM models for Story Characters: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator.');
			}

			// MODIFICATION START: Replaced hardcoded model list with a dynamic one from the helper method.
			$imageModels = $this->getAvailableImageModels();
			// MODIFICATION END

			//  Fetch full prompt templates for JS.
			$promptTemplates = LlmPrompt::where('name', 'like', 'story.asset.%')
				->get(['name', 'system_prompt', 'options'])->keyBy('name');


			return view('story.characters', compact('story', 'models', 'imageModels', 'promptTemplates'));
		}

		/**
		 * Update the characters for a story.
		 */
		public function updateCharacters(Request $request, Story $story)
		{
			$validated = $request->validate([
				'characters' => 'nullable|array',
				'characters.*.id' => 'nullable|integer|exists:story_characters,id',
				'characters.*.name' => 'required|string|max:255',
				'characters.*.description' => 'nullable|string',
				'characters.*.image_prompt' => 'nullable|string',
				'characters.*.image_path' => 'nullable|string|max:2048',
			]);

			DB::transaction(function () use ($story, $validated) {
				$incomingIds = [];
				if (isset($validated['characters'])) {
					foreach ($validated['characters'] as $charData) {
						$values = [
							'story_id' => $story->id,
							'name' => $charData['name'],
							'description' => $charData['description'] ?? null,
							'image_prompt' => $charData['image_prompt'] ?? null,
							'image_path' => $charData['image_path'] ?? null,
						];
						$character = StoryCharacter::updateOrCreate(['id' => $charData['id'] ?? null, 'story_id' => $story->id], $values);
						$incomingIds[] = $character->id;
					}
				}
				$story->characters()->whereNotIn('id', $incomingIds)->delete();
			});

			return redirect()->route('stories.characters', $story)->with('success', 'Characters updated successfully!');
		}

		/**
		 * Show the place management page for a story.
		 */
		public function places(Story $story, LlmController $llmController)
		{
			$story->load('places');

			foreach ($story->places as $place) {
				$place->prompt_data = null;
				if ($place->id && !empty($place->image_path)) {
					$place->prompt_data = Prompt::where('filename', $place->image_path)
						->select('id', 'upscale_status', 'upscale_url', 'filename')
						->first();
				}
			}

			try {
				$modelsResponse = $llmController->getModels();
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (Exception $e) {
				Log::error('Failed to fetch LLM models for Story Places: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator.');
			}

			// MODIFICATION START: Replaced hardcoded model list with a dynamic one from the helper method.
			$imageModels = $this->getAvailableImageModels();
			// MODIFICATION END

			//  Fetch full prompt templates for JS.
			$promptTemplates = LlmPrompt::where('name', 'like', 'story.asset.%')
				->get(['name', 'system_prompt', 'options'])->keyBy('name');


			return view('story.places', compact('story', 'models', 'imageModels', 'promptTemplates'));
		}

		/**
		 * Update the places for a story.
		 */
		public function updatePlaces(Request $request, Story $story)
		{
			$validated = $request->validate([
				'places' => 'nullable|array',
				'places.*.id' => 'nullable|integer|exists:story_places,id',
				'places.*.name' => 'required|string|max:255',
				'places.*.description' => 'nullable|string',
				'places.*.image_prompt' => 'nullable|string',
				'places.*.image_path' => 'nullable|string|max:2048',
			]);

			DB::transaction(function () use ($story, $validated) {
				$incomingIds = [];
				if (isset($validated['places'])) {
					foreach ($validated['places'] as $placeData) {
						$values = [
							'story_id' => $story->id,
							'name' => $placeData['name'],
							'description' => $placeData['description'] ?? null,
							'image_prompt' => $placeData['image_prompt'] ?? null,
							'image_path' => $placeData['image_path'] ?? null,
						];
						$place = StoryPlace::updateOrCreate(['id' => $placeData['id'] ?? null, 'story_id' => $story->id], $values);
						$incomingIds[] = $place->id;
					}
				}
				$story->places()->whereNotIn('id', $incomingIds)->delete();
			});

			return redirect()->route('stories.places', $story)->with('success', 'Places updated successfully!');
		}
	}
