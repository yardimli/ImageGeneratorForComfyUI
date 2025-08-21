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

	class CreateStoryController extends Controller
	{
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
		 * This is the first step, creating the core story structure.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
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

				// Return data for the frontend to start generating descriptions.
				return response()->json([
					'story_id' => $story->id,
					'characters_to_process' => $story->characters->pluck('name'),
					'places_to_process' => $story->places->pluck('name'),
				]);
			} catch (ValidationException $e) {
				return response()->json(['message' => 'The AI returned data in an invalid format. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Story Generation Failed: ' . $e->getMessage());
				return response()->json(['message' => 'An error occurred while generating the story with AI. Error: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * Generates a description for a single character or place.
		 * Called via AJAX from the story creation page.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateDescription(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'story_id' => 'required|integer|exists:stories,id',
				'type' => 'required|string|in:character,place',
				'name' => 'required|string',
			]);

			try {
				$story = Story::with('pages', 'characters', 'places')->findOrFail($validated['story_id']);
				$fullStoryText = $story->pages->pluck('story_text')->implode("\n\n");

				if ($validated['type'] === 'character') {
					$prompt = $this->buildSingleCharacterDescriptionPrompt($story, $fullStoryText, $validated['name']);
					$callReason = 'AI Story Generation - Character Description';
				} else {
					$prompt = $this->buildSinglePlaceDescriptionPrompt($story, $fullStoryText, $validated['name']);
					$callReason = 'AI Story Generation - Place Description';
				}

				$descriptionData = $llmController->callLlmSync(
					$prompt,
					$story->model, // Use the same model as the core story
					$callReason,
					0.7,
					'json_object'
				);

				$this->validateSingleEntityData($descriptionData, $validated['name']);

				if ($validated['type'] === 'character') {
					$story->characters()->where('name', $validated['name'])->update(['description' => $descriptionData['description']]);
				} else {
					$story->places()->where('name', $validated['name'])->update(['description' => $descriptionData['description']]);
				}

				return response()->json(['success' => true, 'description' => $descriptionData['description']]);
			} catch (ValidationException $e) {
				return response()->json(['message' => 'The AI returned data in an invalid format for ' . $validated['name'] . '. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Description Generation Failed: ' . $e->getMessage());
				return response()->json(['message' => 'An error occurred while generating a description for ' . $validated['name'] . '. Error: ' . $e->getMessage()], 500);
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
			$llmPrompt = LlmPrompt::where('name', 'story.core.generate')->firstOrFail();
			return str_replace(
				['{numPages}', '{instructions}'],
				[$numPages, $instructions],
				$llmPrompt->system_prompt
			);
		}

		/**
		 * Builds the prompt for the LLM to generate a single character's description.
		 *
		 * @param Story $story
		 * @param string $fullStoryText
		 * @param string $characterName
		 * @return string
		 */
		private function buildSingleCharacterDescriptionPrompt(Story $story, string $fullStoryText, string $characterName): string
		{
			// Context of pages where the character appears
			$characterPageContext = $story->pages()
				->whereHas('characters', fn ($q) => $q->where('name', $characterName))
				->get()
				->map(fn ($page) => "Page " . $page->page_number . ": " . $page->story_text)
				->implode("\n");

			// Context of already described characters
			$existingCharacterContext = $story->characters
				->where('name', '!=', $characterName)
				->whereNotNull('description')
				->map(fn ($char) => "Character: {$char->name}\nDescription: {$char->description}")
				->implode("\n\n");

			// Context of already described places
			$existingPlaceContext = $story->places
				->whereNotNull('description')
				->map(fn ($place) => "Place: {$place->name}\nDescription: {$place->description}")
				->implode("\n\n");

			$llmPrompt = LlmPrompt::where('name', 'story.character.describe')->firstOrFail();
			return str_replace(
				['{fullStoryText}', '{characterName}', '{characterPageContext}', '{existingCharacterContext}', '{existingPlaceContext}'],
				[$fullStoryText, $characterName, $characterPageContext ?: 'N/A', $existingCharacterContext ?: 'N/A', $existingPlaceContext ?: 'N/A'],
				$llmPrompt->system_prompt
			);
		}

		/**
		 * Builds the prompt for the LLM to generate a single place's description.
		 *
		 * @param Story $story
		 * @param string $fullStoryText
		 * @param string $placeName
		 * @return string
		 */
		private function buildSinglePlaceDescriptionPrompt(Story $story, string $fullStoryText, string $placeName): string
		{
			// Context of pages where the place appears
			$placePageContext = $story->pages()
				->whereHas('places', fn ($q) => $q->where('name', $placeName))
				->get()
				->map(fn ($page) => "Page " . $page->page_number . ": " . $page->story_text)
				->implode("\n");

			// Context of all characters (assuming they are generated first)
			$allCharacterContext = $story->characters
				->whereNotNull('description')
				->map(fn ($char) => "Character: {$char->name}\nDescription: {$char->description}")
				->implode("\n\n");

			// Context of other already described places
			$existingPlaceContext = $story->places
				->where('name', '!=', $placeName)
				->whereNotNull('description')
				->map(fn ($place) => "Place: {$place->name}\nDescription: {$place->description}")
				->implode("\n\n");

			$llmPrompt = LlmPrompt::where('name', 'story.place.describe')->firstOrFail();
			return str_replace(
				['{fullStoryText}', '{placeName}', '{placePageContext}', '{allCharacterContext}', '{existingPlaceContext}'],
				[$fullStoryText, $placeName, $placePageContext ?: 'N/A', $allCharacterContext ?: 'N/A', $existingPlaceContext ?: 'N/A'],
				$llmPrompt->system_prompt
			);
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
		 * Validates the structure for a single entity description from the LLM.
		 *
		 * @param array|null $data
		 * @param string $expectedName
		 * @throws ValidationException
		 */
		private function validateSingleEntityData(?array $data, string $expectedName): void
		{
			$validator = Validator::make($data ?? [], [
				'name' => 'required|string|in:' . $expectedName,
				'description' => 'required|string|min:1',
			]);

			if ($validator->fails()) {
				Log::error('AI Single Entity Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Entity Data: ', $data ?? []);
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
					'description' => '', // Description will be generated later
				]);
				$characterMap[$character->name] = $character->id;
			}

			$placeMap = [];
			foreach ($data['places'] as $placeData) {
				$place = $story->places()->create([
					'name' => $placeData['name'],
					'description' => '', // Description will be generated later
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
	}
