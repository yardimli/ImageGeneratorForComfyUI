<?php

	namespace App\Http\Controllers;

	use App\Http\Controllers\LlmController;
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

	class StoryController extends Controller
	{
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
			$story->load(['user', 'pages.characters', 'pages.places', 'characters', 'places']);
			return view('story.show', compact('story'));
		}

		/**
		 * Show the form for creating a new story.
		 */
		public function create()
		{
			return view('story.create');
		}

		/**
		 * Show the form for creating a new story with AI.
		 *
		 * @param LlmController $llmController
		 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
		 */
		public function createWithAi(LlmController $llmController)
		{
			try {
				$modelsResponse = $llmController->getModels();
				// MODIFICATION: Process models to add variants for image/reasoning support.
				$models = $llmController->processModelsForView($modelsResponse);

				$summaries = [];
				$summaryPath = resource_path('summaries');
				if (File::isDirectory($summaryPath)) {
					$files = File::files($summaryPath);
					foreach ($files as $file) {
						if ($file->getExtension() === 'txt') {
							$summaries[] = [
								'filename' => $file->getFilename(),
								'name' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
								'content' => File::get($file->getRealPath()),
							];
						}
					}
					// Sort by name for consistent ordering in the dropdown.
					usort($summaries, fn ($a, $b) => strcmp($a['name'], $b['name']));
				}

				return view('story.create-ai', compact('models', 'summaries'));
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for AI Story Creator: ' . $e->getMessage());
				return redirect()->route('stories.index')->with('error', 'Could not fetch AI models at this time. Please try again later.');
			}
		}

		/**
		 * Store a newly created story in storage.
		 */
		public function store(Request $request)
		{
			$validated = $request->validate([
				'title' => 'required|string|max:255',
				'short_description' => 'nullable|string',
				'level' => 'required|string|max:50',
			]);

			$story = Story::create([
				'user_id' => auth()->id(),
				'title' => $validated['title'],
				'short_description' => $validated['short_description'],
				'level' => $validated['level'],
			]);

			return redirect()->route('stories.edit', $story)->with('success', 'Story created successfully. Now add some pages!');
		}

		/**
		 * Generate and store a new story using AI.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function storeWithAi(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'instructions' => 'required|string|max:14000',
				'num_pages' => 'required|integer|min:1|max:99',
				'model' => 'required|string',
				'level' => 'required|string|max:50',
			]);

			try {
				$story = DB::transaction(function () use ($validated, $llmController) {
					// --- STEP 1: Generate Story Core (title, pages, character/place names) ---
					$corePrompt = $this->buildStoryPrompt($validated['instructions'], $validated['num_pages']);
					$storyData = $llmController->callLlmSync(
						$corePrompt,
						$validated['model'],
						'AI Story Generation - Core',
						0.7,
						'json_object'
					);

					$this->validateStoryData($storyData);
					// Save the initial story structure with empty character/place descriptions.
					$story = $this->saveStoryFromAiData($storyData, $validated);

					// START MODIFICATION: Create a map of characters/places to the text of the pages they appear on.
					$characterPageTextMap = [];
					$placePageTextMap = [];
					foreach ($storyData['pages'] as $index => $page) {
						// We include the page number for better context for the LLM.
						$pageContentWithNumber = "Page " . ($index + 1) . ": " . $page['content'];
						if (!empty($page['characters'])) {
							foreach ($page['characters'] as $charName) {
								if (!isset($characterPageTextMap[$charName])) {
									$characterPageTextMap[$charName] = [];
								}
								$characterPageTextMap[$charName][] = $pageContentWithNumber;
							}
						}
						if (!empty($page['places'])) {
							foreach ($page['places'] as $placeName) {
								if (!isset($placePageTextMap[$placeName])) {
									$placePageTextMap[$placeName] = [];
								}
								$placePageTextMap[$placeName][] = $pageContentWithNumber;
							}
						}
					}
					// END MODIFICATION

					$fullStoryText = implode("\n\n", array_column($storyData['pages'], 'content'));
					$characterNames = array_column($storyData['characters'] ?? [], 'name');
					$placeNames = array_column($storyData['places'] ?? [], 'name');

					// --- STEP 2: Generate Character Descriptions ---
					if (!empty($characterNames)) {
						// MODIFICATION: Pass the map to the prompt builder.
						$charPrompt = $this->buildCharacterDescriptionPrompt($fullStoryText, $characterNames, $characterPageTextMap);
						$characterDescriptionData = $llmController->callLlmSync(
							$charPrompt,
							$validated['model'],
							'AI Story Generation - Characters',
							0.7,
							'json_object'
						);
						$this->updateCharactersFromAiData($story, $characterDescriptionData);
					}

					// --- STEP 3: Generate Place Descriptions ---
					if (!empty($placeNames)) {
						// MODIFICATION: Pass the map to the prompt builder.
						$placePrompt = $this->buildPlaceDescriptionPrompt($fullStoryText, $placeNames, $placePageTextMap);
						$placeDescriptionData = $llmController->callLlmSync(
							$placePrompt,
							$validated['model'],
							'AI Story Generation - Places',
							0.7,
							'json_object'
						);
						$this->updatePlacesFromAiData($story, $placeDescriptionData);
					}

					return $story;
				});

				return redirect()->route('stories.edit', $story)->with('success', 'Your AI-generated story has been created successfully!');
			} catch (ValidationException $e) {
				return back()->withInput()->withErrors($e->errors())->with('error', 'The AI returned data in an invalid format. Please try again.');
			} catch (\Exception $e) {
				Log::error('AI Story Generation Failed: ' . $e->getMessage());
				return back()->withInput()->with('error', 'An error occurred while generating the story with AI. Please try again. Error: ' . $e->getMessage());
			}
		}

		/**
		 * Builds the prompt for the LLM to generate a story.
		 *
		 * @param string $instructions
		 * @param int $numPages
		 * @return string
		 */
		private function buildStoryPrompt(string $instructions, int $numPages): string
		{
			// START MODIFICATION: Changed JSON structure to show variants and updated prompt instructions.
			$jsonStructure = <<<'JSON'
{
  "title": "A string for the story title.",
  "description": "A short description of the story.",
  "characters": [
    {
      "name": "Character Name (Appearance 1)",
      "description": ""
    },
    {
      "name": "Character Name (Appearance 2)",
      "description": ""
    }
  ],
  "places": [
    {
      "name": "Place Name (State 1)",
      "description": ""
    }
  ],
  "pages": [
    {
      "content": "The text for this page of the story.",
      "characters": ["Character Name (Appearance 1)"],
      "places": ["Place Name (State 1)"]
    }
  ]
}
JSON;

			return <<<PROMPT
You are a creative storyteller. Based on the following instructions, create a complete story.
The story must have a title, a short description, a list of characters, a list of places, and a series of pages.
The number of pages must be exactly {$numPages}.

VERY IMPORTANT INSTRUCTIONS:
1.  Instructions from the user: "{$instructions}"
2.  For the 'characters' and 'places' arrays, provide only the 'name'. Leave the 'description' for each character and place as an empty string (""). You will be asked to describe them in a later step.
3.  If a character's or place's appearance, clothing, age or state changes during the story, you MUST create a separate entry for each version with a descriptive name (e.g., "Cinderella (in rags)", "Cinderella (in a ballgown)", "Arthur (Young)", "Arthur (Old)", "The Castle (daytime)", "The Castle (under siege)").
4.  In the 'pages' array, you MUST reference the specific version of the character or place that appears on that page.


Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure (the example shows how to handle character variations):
{$jsonStructure}

Now, generate the story based on the user's instructions.
PROMPT;
			// END MODIFICATION
		}

		/**
		 * Builds the prompt for the LLM to generate character descriptions.
		 *
		 * @param string $fullStoryText
		 * @param array $characterNames
		 * @param array $characterPageTextMap // MODIFICATION: Add parameter for page context.
		 * @return string
		 */
		// START MODIFICATION: Add $characterPageTextMap parameter and use it to build context.
		private function buildCharacterDescriptionPrompt(string $fullStoryText, array $characterNames, array $characterPageTextMap): string
		{
			$jsonStructure = <<<'JSON'
{
  "characters": [
    {
      "name": "Character Name",
      "description": "A detailed description of the character's appearance, including their clothes and physique."
    }
  ]
}
JSON;

			// Build a string with context for each character.
			$characterContext = '';
			foreach ($characterNames as $name) {
				$characterContext .= "Character: \"{$name}\"\n";
				if (isset($characterPageTextMap[$name]) && !empty($characterPageTextMap[$name])) {
					$characterContext .= "Appears in:\n";
					foreach ($characterPageTextMap[$name] as $pageText) {
						$characterContext .= "- " . trim($pageText) . "\n";
					}
				}
				$characterContext .= "\n";
			}

			return <<<PROMPT
You are a character designer. Based on the full story text and the specific page contexts provided below, create a detailed visual description for each of the listed characters.
Focus on their physical appearance, clothing, and physique with attention to detail, as they appear in the pages they are mentioned in.

Full Story Text (for overall context):
---
{$fullStoryText}
---

Character Appearances by Page:
---
{$characterContext}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must contain a 'characters' array, and each object in the array must have a 'name' and 'description' key.
The 'name' must exactly match one of the names from the provided list.
The JSON object must follow this exact structure:
{$jsonStructure}

Now, generate the character descriptions based on their specific appearances in the story.
PROMPT;
		}
		// END MODIFICATION

		/**
		 * Builds the prompt for the LLM to generate place descriptions.
		 *
		 * @param string $fullStoryText
		 * @param array $placeNames
		 * @param array $placePageTextMap // MODIFICATION: Add parameter for page context.
		 * @return string
		 */
		// START MODIFICATION: Add $placePageTextMap parameter and use it to build context.
		private function buildPlaceDescriptionPrompt(string $fullStoryText, array $placeNames, array $placePageTextMap): string
		{
			$jsonStructure = <<<'JSON'
{
  "places": [
    {
      "name": "Place Name",
      "description": "A detailed description of the place's appearance and atmosphere."
    }
  ]
}
JSON;

			// Build a string with context for each place.
			$placeContext = '';
			foreach ($placeNames as $name) {
				$placeContext .= "Place: \"{$name}\"\n";
				if (isset($placePageTextMap[$name]) && !empty($placePageTextMap[$name])) {
					$placeContext .= "Appears in:\n";
					foreach ($placePageTextMap[$name] as $pageText) {
						$placeContext .= "- " . trim($pageText) . "\n";
					}
				}
				$placeContext .= "\n";
			}

			return <<<PROMPT
You are a world builder. Based on the full story text and the specific page contexts provided below, create a detailed visual description for each of the listed places.
Focus on the appearance, atmosphere, and key features of each location as it appears in the pages it is mentioned in.

Full Story Text (for overall context):
---
{$fullStoryText}
---

Place Appearances by Page:
---
{$placeContext}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must contain a 'places' array, and each object in the array must have a 'name' and 'description' key.
The 'name' must exactly match one of the names from the provided list.
The JSON object must follow this exact structure:
{$jsonStructure}

Now, generate the place descriptions based on their specific appearances in the story.
PROMPT;
		}
		// END MODIFICATION

		/**
		 * Validates the structure of the data returned from the LLM.
		 *
		 * @param array|null $storyData
		 * @throws ValidationException
		 */
		private function validateStoryData(?array $storyData): void
		{
			$validator = Validator::make($storyData ?? [], [
				'title' => 'required|string',
				'description' => 'required|string',
				'characters' => 'present|array',
				'characters.*.name' => 'required|string',
				'characters.*.description' => 'present|string',
				'places' => 'present|array',
				'places.*.name' => 'required|string',
				'places.*.description' => 'present|string',
				'pages' => 'required|array|min:1',
				'pages.*.content' => 'required|string',
				'pages.*.characters' => 'present|array',
				'pages.*.places' => 'present|array',
			]);

			if ($validator->fails()) {
				Log::error('AI Story Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Data: ', $storyData ?? []);
				throw new ValidationException($validator);
			}
		}

		/**
		 * Saves the story and its components from the validated AI data.
		 *
		 * @param array $data
		 * @param array $validatedRequestData
		 * @return Story
		 */
		private function saveStoryFromAiData(array $data, array $validatedRequestData): Story
		{
			$story = Story::create([
				'user_id' => auth()->id(),
				'title' => $data['title'],
				'short_description' => $data['description'],
				'level' => $validatedRequestData['level'],
				'initial_prompt' => $validatedRequestData['instructions'],
				'model' => $validatedRequestData['model'],
			]);

			$characterMap = [];
			foreach ($data['characters'] as $charData) {
				$character = $story->characters()->create([
					'name' => $charData['name'],
					'description' => $charData['description'], // This will be an empty string initially
				]);
				$characterMap[$character->name] = $character->id;
			}

			$placeMap = [];
			foreach ($data['places'] as $placeData) {
				$place = $story->places()->create([
					'name' => $placeData['name'],
					'description' => $placeData['description'], // This will be an empty string initially
				]);
				$placeMap[$place->name] = $place->id;
			}

			foreach ($data['pages'] as $index => $pageData) {
				$page = $story->pages()->create([
					'page_number' => $index + 1,
					'story_text' => $pageData['content'],
				]);

				$charIds = collect($pageData['characters'])->map(fn ($name) => $characterMap[$name] ?? null)->filter()->all();
				$placeIds = collect($pageData['places'])->map(fn ($name) => $placeMap[$name] ?? null)->filter()->all();

				if (!empty($charIds)) {
					$page->characters()->sync($charIds);
				}
				if (!empty($placeIds)) {
					$page->places()->sync($placeIds);
				}
			}

			return $story;
		}

		/**
		 * Updates character descriptions from the validated AI data.
		 *
		 * @param Story $story
		 * @param array|null $characterData
		 * @throws ValidationException
		 */
		private function updateCharactersFromAiData(Story $story, ?array $characterData): void
		{
			$validator = Validator::make($characterData ?? [], [
				'characters' => 'present|array',
				'characters.*.name' => 'required|string',
				'characters.*.description' => 'required|string',
			]);

			if ($validator->fails()) {
				Log::error('AI Character Description Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Character Data: ', $characterData ?? []);
				throw new ValidationException($validator);
			}

			$validated = $validator->validated();

			foreach ($validated['characters'] as $charUpdate) {
				$story->characters()
					->where('name', $charUpdate['name'])
					->update(['description' => $charUpdate['description']]);
			}
		}

		/**
		 * Updates place descriptions from the validated AI data.
		 *
		 * @param Story $story
		 * @param array|null $placeData
		 * @throws ValidationException
		 */
		private function updatePlacesFromAiData(Story $story, ?array $placeData): void
		{
			$validator = Validator::make($placeData ?? [], [
				'places' => 'present|array',
				'places.*.name' => 'required|string',
				'places.*.description' => 'required|string',
			]);

			if ($validator->fails()) {
				Log::error('AI Place Description Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Place Data: ', $placeData ?? []);
				throw new ValidationException($validator);
			}

			$validated = $validator->validated();

			foreach ($validated['places'] as $placeUpdate) {
				$story->places()
					->where('name', $placeUpdate['name'])
					->update(['description' => $placeUpdate['description']]);
			}
		}

		/**
		 * Show the form for editing the specified story.
		 */
		public function edit(Story $story, LlmController $llmController)
		{
			$story->load(['pages.characters', 'pages.places', 'characters', 'places']);

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
				// MODIFICATION: Process models to add variants for image/reasoning support.
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Story Editor: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator. The feature will be unavailable.');
			}

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

			return view('story.edit', compact('story', 'models', 'imageModels'));
		}

		/**
		 * Update the specified story in storage.
		 */
		public function update(Request $request, Story $story)
		{
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
			} catch (\Exception $e) {
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
			} catch (\Exception $e) {
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
			} catch (\Exception $e) {
				Log::error('AI Image Prompt Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the image prompt. Please try again.'], 500);
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
			} catch (\Exception $e) {
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
				// MODIFICATION: Process models to add variants for image/reasoning support.
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Story Characters: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator.');
			}

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

			return view('story.characters', compact('story', 'models', 'imageModels'));
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
				// MODIFICATION: Process models to add variants for image/reasoning support.
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Story Places: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the prompt generator.');
			}

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

			return view('story.places', compact('story', 'models', 'imageModels'));
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
