<?php

	namespace App\Http\Controllers;

	// START MODIFICATION: Import new classes needed for AI story generation.
	use App\Http\Controllers\LlmController;
	use App\Models\Prompt;
	use App\Models\Story;
	use App\Models\StoryCharacter;
	use App\Models\StoryPage;
	use App\Models\StoryPlace;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\ValidationException;
// END MODIFICATION

// use Illuminate\Support\Facades\Gate;

	class StoryController extends Controller
	{
		/**
		 * Display a listing of the user's stories.
		 */
		public function index()
		{
			// START MODIFICATION: Fetch all stories for public view, with author info and pagination.
			$stories = Story::with('user')->latest('updated_at')->paginate(15);
			return view('story.index', compact('stories'));
			// END MODIFICATION
		}

		// START MODIFICATION: Add a new method to publicly display a single story.
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
		// END MODIFICATION

		/**
		 * Show the form for creating a new story.
		 */
		public function create()
		{
			return view('story.create');
		}

// START MODIFICATION: Add method to show the AI story creation form.
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

				return view('story.create-ai', compact('models'));
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for AI Story Creator: ' . $e->getMessage());
				return redirect()->route('stories.index')->with('error', 'Could not fetch AI models at this time. Please try again later.');
			}
		}
// END MODIFICATION

		/**
		 * Store a newly created story in storage.
		 */
		public function store(Request $request)
		{
			$validated = $request->validate([
				'title' => 'required|string|max:255',
				'short_description' => 'nullable|string',
			]);

			$story = Story::create([
				'user_id' => auth()->id(),
				'title' => $validated['title'],
				'short_description' => $validated['short_description'],
			]);

			return redirect()->route('stories.edit', $story)->with('success', 'Story created successfully. Now add some pages!');
		}

// START MODIFICATION: Add method to generate and store a story using AI.
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
				'instructions' => 'required|string|max:4000',
				'num_pages' => 'required|integer|min:1|max:99',
				'model' => 'required|string',
			]);

			$prompt = $this->buildStoryPrompt($validated['instructions'], $validated['num_pages']);

			try {
				$storyData = $llmController->callLlmSync(
					$prompt,
					$validated['model'],
					'AI Story Generation',
					0.7, // A reasonable temperature for creative tasks
					'json_object'
				);

				// Validate the structure of the JSON returned by the LLM
				$this->validateStoryData($storyData);

				$story = $this->saveStoryFromAiData($storyData);

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
		 * @return Story
		 */
		private function saveStoryFromAiData(array $data): Story
		{
			return DB::transaction(function () use ($data) {
				$story = Story::create([
					'user_id' => auth()->id(),
					'title' => $data['title'],
					'short_description' => $data['description'],
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

					$charIds = collect($pageData['characters'])->map(fn($name) => $characterMap[$name] ?? null)->filter()->all();
					$placeIds = collect($pageData['places'])->map(fn($name) => $placeMap[$name] ?? null)->filter()->all();

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
// END MODIFICATION

		/**
		 * Show the form for editing the specified story.
		 */
		// START MODIFICATION: Inject LlmController and fetch models for the view.
		public function edit(Story $story, LlmController $llmController)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$story->load(['pages.characters', 'pages.places', 'characters', 'places']);

			// START MODIFICATION: Check for generated images and load prompt data for upscaling.
			foreach ($story->pages as $page) {
				$page->prompt_data = null; // Initialize
				if (empty($page->image_path) && $page->id) {
					// Find the latest successfully generated image for this page from the prompts table.
					$generatedImage = Prompt::where('story_page_id', $page->id)
						->whereNotNull('filename')
						->latest('updated_at')
						->first();

					if ($generatedImage) {
						// This will pre-fill the image path in the view.
						$page->image_path = $generatedImage->filename;
						// Also attach the prompt data so the new image can be upscaled immediately.
						$page->prompt_data = $generatedImage;
					}
				} elseif (!empty($page->image_path)) {
					// If an image path already exists, find its prompt data for the modal.
					$page->prompt_data = Prompt::where('filename', $page->image_path)
						->select('id', 'upscale_status', 'upscale_url', 'filename')
						->first();
				}
			}
			// END MODIFICATION

			// Fetch models for the AI prompt generator modal
			try {
				$modelsResponse = $llmController->getModels();
				$models = collect($modelsResponse['data'] ?? [])
					->sortBy('name')
					->all();
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Story Editor: ' . $e->getMessage());
				$models = []; // Pass an empty array on failure
				session()->flash('error', 'Could not fetch AI models for the prompt generator. The feature will be unavailable.');
			}

			// START NEW MODIFICATION: Define models for the "Draw with AI" feature.
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
			];
			// END NEW MODIFICATION

			return view('story.edit', compact('story', 'models', 'imageModels'));
		}
		// END MODIFICATION

		/**
		 * Update the specified story in storage.
		 */
		public function update(Request $request, Story $story)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$validated = $request->validate([
				'title' => 'required|string|max:255',
				'short_description' => 'nullable|string',
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
				// Delete pages that were not in the submission
				$story->pages()->whereNotIn('id', $incomingPageIds)->delete();
			});


			return redirect()->route('stories.edit', $story)->with('success', 'Story updated successfully!');
		}

		/**
		 * Remove the specified story from storage.
		 */
		public function destroy(Story $story)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$story->delete();
			return redirect()->route('stories.index')->with('success', 'Story deleted successfully.');
		}

		// START MODIFICATION: Add methods for AI image prompt generation.
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
				'page_text' => 'required|string',
				'character_descriptions' => 'present|array',
				'character_descriptions.*' => 'string',
				'place_descriptions' => 'present|array',
				'place_descriptions.*' => 'string',
				'instructions' => 'nullable|string|max:1000',
				'model' => 'required|string',
			]);

			$prompt = $this->buildImageGenerationPrompt(
				$validated['page_text'],
				$validated['character_descriptions'],
				$validated['place_descriptions'],
				$validated['instructions'] ?? ''
			);

			try {
				$response = $llmController->callLlmSync(
					$prompt,
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
		 * Builds the prompt for the LLM to generate an image prompt.
		 *
		 * @param string $pageText
		 * @param array $characterDescriptions
		 * @param array $placeDescriptions
		 * @param string $userInstructions
		 * @return string
		 */
		private function buildImageGenerationPrompt(string $pageText, array $characterDescriptions, array $placeDescriptions, string $userInstructions): string
		{
			$characterText = !empty($characterDescriptions) ? "Characters in this scene:\n- " . implode("\n- ", $characterDescriptions) : "No specific characters are described for this scene.";
			$placeText = !empty($placeDescriptions) ? "Places in this scene:\n- " . implode("\n- ", $placeDescriptions) : "No specific places are described for this scene.";
			$instructionsText = !empty($userInstructions) ? "User's specific instructions: \"{$userInstructions}\"" : "No specific instructions from the user.";

			$jsonStructure = <<<'JSON'
{
  "prompt": "A detailed, comma-separated list of visual descriptors for the image."
}
JSON;

			return <<<PROMPT
You are an expert at writing image generation prompts for AI art models like DALL-E 3 or Midjourney.
Your task is to create a single, concise, and descriptive image prompt based on the provided context of a story page.

**Context:**
1.  **Page Content:**
    "{$pageText}"

2.  **Scene Details:**
    {$characterText}
    {$placeText}

3.  **User Guidance:**
    {$instructionsText}

**Instructions:**
- Synthesize all the information to create a vivid image prompt.
- The prompt should be a single paragraph of comma-separated descriptive phrases.
- Focus on visual details: the setting, character appearance, actions, mood, and lighting.
- Provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
- The JSON object must follow this exact structure:
{$jsonStructure}

Now, generate the image prompt for the provided context in the specified JSON format.
PROMPT;
		}
		// END MODIFICATION

		/**
		 * Show the character management page for a story.
		 */
		public function characters(Story $story)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$story->load('characters');
			return view('story.characters', compact('story'));
		}

		/**
		 * Update the characters for a story.
		 */
		public function updateCharacters(Request $request, Story $story)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$validated = $request->validate([
				'characters' => 'nullable|array',
				'characters.*.id' => 'nullable|integer|exists:story_characters,id',
				'characters.*.name' => 'required|string|max:255',
				'characters.*.description' => 'nullable|string',
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
							'image_path' => $charData['image_path'] ?? null,
						];
						$character = StoryCharacter::updateOrCreate(['id' => $charData['id'] ?? null], $values);
						$incomingIds[] = $character->id;
					}
				}
				// Delete any characters that were not submitted
				$story->characters()->whereNotIn('id', $incomingIds)->delete();
			});

			return redirect()->route('stories.characters', $story)->with('success', 'Characters updated successfully!');
		}

		/**
		 * Show the place management page for a story.
		 */
		public function places(Story $story)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$story->load('places');
			return view('story.places', compact('story'));
		}

		/**
		 * Update the places for a story.
		 */
		public function updatePlaces(Request $request, Story $story)
		{
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}

			$validated = $request->validate([
				'places' => 'nullable|array',
				'places.*.id' => 'nullable|integer|exists:story_places,id',
				'places.*.name' => 'required|string|max:255',
				'places.*.description' => 'nullable|string',
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
							'image_path' => $placeData['image_path'] ?? null,
						];
						$place = StoryPlace::updateOrCreate(['id' => $placeData['id'] ?? null], $values);
						$incomingIds[] = $place->id;
					}
				}
				// Delete any places that were not submitted
				$story->places()->whereNotIn('id', $incomingIds)->delete();
			});

			return redirect()->route('stories.places', $story)->with('success', 'Places updated successfully!');
		}
	}
