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
	use Illuminate\Support\Facades\File; // MODIFICATION: Add File facade.
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
				$models = collect($modelsResponse['data'] ?? [])
					->sortBy('name')
					->all();

				// START MODIFICATION: Get summary files and their content.
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
				// END MODIFICATION

				// MODIFICATION: Pass summaries to the view.
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
			// MODIFICATION: Reverted to original validation, as summary is handled by frontend.
			$validated = $request->validate([
				'instructions' => 'required|string|max:4000',
				'num_pages' => 'required|integer|min:1|max:99',
				'model' => 'required|string',
				'level' => 'required|string|max:50',
			]);

			$prompt = $this->buildStoryPrompt($validated['instructions'], $validated['num_pages']);

			try {
				$storyData = $llmController->callLlmSync(
					$prompt,
					$validated['model'],
					'AI Story Generation',
					0.7,
					'json_object'
				);

				$this->validateStoryData($storyData);
				$story = $this->saveStoryFromAiData($storyData, $validated);

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
			$jsonStructure = <<<'JSON'
{
  "title": "A string for the story title.",
  "description": "A short description of the story.",
  "characters": [
    {
      "name": "Character Name",
      "description": "A description of the character."
    }
  ],
  "places": [
    {
      "name": "Place Name",
      "description": "A description of the place."
    }
  ],
  "pages": [
    {
      "content": "The text for this page of the story.",
      "characters": ["Character Name 1", "Character Name 2"],
      "places": ["Place Name 1"]
    }
  ]
}
JSON;

			return <<<PROMPT
You are a creative storyteller. Based on the following instructions, create a complete story.
The story must have a title, a short description, a list of characters, a list of places, and a series of pages.
The number of pages must be exactly {$numPages}.

Instructions from the user: "{$instructions}"

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{$jsonStructure}

Now, generate the story based on the user's instructions.
PROMPT;
		}

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
				'characters.*.description' => 'required|string',
				'places' => 'present|array',
				'places.*.name' => 'required|string',
				'places.*.description' => 'required|string',
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
			return DB::transaction(function () use ($data, $validatedRequestData) {
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
						'description' => $charData['description'],
					]);
					$characterMap[$character->name] = $character->id;
				}

				$placeMap = [];
				foreach ($data['places'] as $placeData) {
					$place = $story->places()->create([
						'name' => $placeData['name'],
						'description' => $placeData['description'],
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
			});
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
				$models = collect($modelsResponse['data'] ?? [])
					->sortBy('name')
					->all();
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
				$models = collect($modelsResponse['data'] ?? [])->sortBy('name')->all();
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
				$models = collect($modelsResponse['data'] ?? [])->sortBy('name')->all();
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
