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
		// MODIFICATION START: Added a helper to load and format image models from JSON.
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

			// Mapping from DB short name to full model name in JSON
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
				$shortName = array_search($modelData['name'], $supportedModelsMap);
				if ($shortName !== false) {
					$displayName = ucfirst(str_replace(['-', '_', '/'], ' ', $shortName));
					if (isset($modelData['price'])) {
						$displayName .= " (\${$modelData['price']})";
					}
					$viewModels[] = [
						'id' => $shortName,
						'name' => $displayName,
					];
					$foundModels[$shortName] = true;
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

		/**
		 * Display a listing of the user's stories.
		 */
		public function index()
		{
			// Eager load prompt counts and calculate cost via subquery.
			$stories = Story::with('user')
				->withCount(['pagePrompts', 'characterPrompts', 'placePrompts'])
				->addSelect([
					'image_cost' => Prompt::selectRaw('COALESCE(SUM(
                        CASE
                            WHEN model = "dev" THEN 0.00
                            WHEN model LIKE "%imagen%" THEN 0.07
                            ELSE 0.04
                        END
                    ), 0)')
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
