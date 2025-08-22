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

				// MODIFIED: Fetch all necessary prompts for the view
				$prompts = [
					'content' => LlmPrompt::where('name', 'story.generate.content')->firstOrFail(),
					'entities' => LlmPrompt::where('name', 'story.generate.entities')->firstOrFail(),
					'character' => LlmPrompt::where('name', 'story.character.describe')->firstOrFail(),
					'place' => LlmPrompt::where('name', 'story.place.describe')->firstOrFail(),
				];

				return view('story.create-ai', compact('models', 'summaries', 'prompts'));
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models/prompts for AI Story Creator: ' . $e->getMessage());
				return redirect()->route('stories.index')->with('error', 'Could not fetch AI models or prompts at this time. Please try again later.');
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
		 * NEW: Step 1 of AI generation. Generates story content (title, desc, pages).
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateContent(Request $request, LlmController $llmController)
		{
			// MODIFIED: 'instructions' is removed as the main prompt textarea is now directly editable.
			$validated = $request->validate([
				'model' => 'required|string',
				'level' => 'required|string|max:50',
				'prompt_content_generation' => 'required|string',
				'prompt_entity_generation' => 'required|string',
				'prompt_character_description' => 'required|string',
				'prompt_place_description' => 'required|string',
			]);

			try {
				// MODIFIED: The full prompt is submitted directly from the form, no placeholder replacement is needed here.
				$contentPrompt = $validated['prompt_content_generation'];

				$storyContentData = $llmController->callLlmSync(
					$contentPrompt,
					$validated['model'],
					'AI Story Generation - Content',
					0.7,
					'json_object'
				);

				$this->validateContentData($storyContentData);

				$story = $this->saveStoryFromAiContent($storyContentData, $validated);

				return response()->json(['story_id' => $story->id]);
			} catch (ValidationException $e) {
				return response()->json(['message' => 'The AI returned story content in an invalid format. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Story Content Generation Failed: ' . $e->getMessage());
				return response()->json(['message' => 'An error occurred while generating the story content. Error: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * NEW: Step 2 of AI generation. Generates characters and places from story text.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateEntities(Request $request, LlmController $llmController)
		{
			$validated = $request->validate(['story_id' => 'required|integer|exists:stories,id']);

			try {
				$story = Story::with('pages')->findOrFail($validated['story_id']);
				$fullStoryText = $story->pages->pluck('story_text')->implode("\n\n");

				$entityPrompt = str_replace('{fullStoryText}', $fullStoryText, $story->prompt_entity_generation);

				$entityData = $llmController->callLlmSync(
					$entityPrompt,
					$story->model,
					'AI Story Generation - Entities',
					0.7,
					'json_object'
				);

				$this->validateEntityData($entityData);

				$this->saveEntitiesAndLinks($story, $entityData);

				// Refresh the relationships
				$story->load('characters', 'places');

				return response()->json([
					'story_id' => $story->id,
					'characters_to_process' => $story->characters->pluck('name'),
					'places_to_process' => $story->places->pluck('name'),
				]);
			} catch (ValidationException $e) {
				return response()->json(['message' => 'The AI returned characters/places in an invalid format. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Story Entity Generation Failed: ' . $e->getMessage());
				return response()->json(['message' => 'An error occurred while generating characters and places. Error: ' . $e->getMessage()], 500);
			}
		}


		/**
		 * MODIFIED: Step 3 of AI generation. Generates a description for a single character or place.
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
					// MODIFIED: Get prompt from the story record
					$promptTemplate = $story->prompt_character_description;
					if (!$promptTemplate) {
						throw new \Exception('Character description prompt template is missing for this story.');
					}
					$prompt = $this->buildSingleCharacterDescriptionPrompt($story, $fullStoryText, $validated['name'], $promptTemplate);
					$callReason = 'AI Story Generation - Character Description';
				} else {
					// MODIFIED: Get prompt from the story record
					$promptTemplate = $story->prompt_place_description;
					if (!$promptTemplate) {
						throw new \Exception('Place description prompt template is missing for this story.');
					}
					$prompt = $this->buildSinglePlaceDescriptionPrompt($story, $fullStoryText, $validated['name'], $promptTemplate);
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
		 * MODIFIED: Builds the prompt for a single character's description using a provided template.
		 *
		 * @param Story $story
		 * @param string $fullStoryText
		 * @param string $characterName
		 * @param string $promptTemplate
		 * @return string
		 */
		private function buildSingleCharacterDescriptionPrompt(Story $story, string $fullStoryText, string $characterName, string $promptTemplate): string
		{
			$characterPageContext = $story->pages()
				->whereHas('characters', fn ($q) => $q->where('name', $characterName))
				->get()
				->map(fn ($page) => "Page " . $page->page_number . ": " . $page->story_text)
				->implode("\n");

			$existingCharacterContext = $story->characters
				->where('name', '!=', $characterName)
				->whereNotNull('description')
				->map(fn ($char) => "Character: {$char->name}\nDescription: {$char->description}")
				->implode("\n\n");

			$existingPlaceContext = $story->places
				->whereNotNull('description')
				->map(fn ($place) => "Place: {$place->name}\nDescription: {$place->description}")
				->implode("\n\n");

			return str_replace(
				['{fullStoryText}', '{characterName}', '{characterPageContext}', '{existingCharacterContext}', '{existingPlaceContext}'],
				[$fullStoryText, $characterName, $characterPageContext ?: 'N/A', $existingCharacterContext ?: 'N/A', $existingPlaceContext ?: 'N/A'],
				$promptTemplate
			);
		}

		/**
		 * MODIFIED: Builds the prompt for a single place's description using a provided template.
		 *
		 * @param Story $story
		 * @param string $fullStoryText
		 * @param string $placeName
		 * @param string $promptTemplate
		 * @return string
		 */
		private function buildSinglePlaceDescriptionPrompt(Story $story, string $fullStoryText, string $placeName, string $promptTemplate): string
		{
			$placePageContext = $story->pages()
				->whereHas('places', fn ($q) => $q->where('name', $placeName))
				->get()
				->map(fn ($page) => "Page " . $page->page_number . ": " . $page->story_text)
				->implode("\n");

			$allCharacterContext = $story->characters
				->whereNotNull('description')
				->map(fn ($char) => "Character: {$char->name}\nDescription: {$char->description}")
				->implode("\n\n");

			$existingPlaceContext = $story->places
				->where('name', '!=', $placeName)
				->whereNotNull('description')
				->map(fn ($place) => "Place: {$place->name}\nDescription: {$place->description}")
				->implode("\n\n");

			return str_replace(
				['{fullStoryText}', '{placeName}', '{placePageContext}', '{allCharacterContext}', '{existingPlaceContext}'],
				[$fullStoryText, $placeName, $placePageContext ?: 'N/A', $allCharacterContext ?: 'N/A', $existingPlaceContext ?: 'N/A'],
				$promptTemplate
			);
		}

		/**
		 * NEW: Validates the story content data from the LLM.
		 *
		 * @param array|null $data
		 * @throws ValidationException
		 */
		private function validateContentData(?array $data): void
		{
			$validator = Validator::make($data ?? [], [
				'title' => 'required|string',
				'description' => 'required|string',
				'pages' => 'required|array|min:1',
				'pages.*.content' => 'required|string',
			]);

			if ($validator->fails()) {
				Log::error('AI Story Content Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Content Data: ', $data ?? []);
				throw new ValidationException($validator);
			}
		}

		/**
		 * NEW: Validates the story entity data from the LLM.
		 *
		 * @param array|null $data
		 * @throws ValidationException
		 */
		private function validateEntityData(?array $data): void
		{
			$validator = Validator::make($data ?? [], [
				'characters' => 'present|array',
				'characters.*.name' => 'required|string',
				'characters.*.pages' => 'required|array',
				'characters.*.pages.*' => 'integer',
				'places' => 'present|array',
				'places.*.name' => 'required|string',
				'places.*.pages' => 'required|array',
				'places.*.pages.*' => 'integer',
			]);

			if ($validator->fails()) {
				Log::error('AI Story Entity Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Entity Data: ', $data ?? []);
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
		 * NEW: Saves the initial story and pages from validated AI content data.
		 *
		 * @param array $data
		 * @param array $validatedRequestData
		 * @return Story
		 */
		private function saveStoryFromAiContent(array $data, array $validatedRequestData): Story
		{
			$story = Story::create([
				'user_id' => auth()->id(),
				'title' => $data['title'],
				'short_description' => $data['description'],
				'level' => $validatedRequestData['level'],
				// MODIFIED: 'initial_prompt' now stores the full content generation prompt submitted by the user.
				'initial_prompt' => $validatedRequestData['prompt_content_generation'],
				'model' => $validatedRequestData['model'],
				'prompt_content_generation' => $validatedRequestData['prompt_content_generation'],
				'prompt_entity_generation' => $validatedRequestData['prompt_entity_generation'],
				'prompt_character_description' => $validatedRequestData['prompt_character_description'],
				'prompt_place_description' => $validatedRequestData['prompt_place_description'],
			]);

			foreach ($data['pages'] as $index => $pageData) {
				$story->pages()->create([
					'page_number' => $index + 1,
					'story_text' => $pageData['content'],
				]);
			}

			return $story;
		}

		/**
		 * NEW: Saves characters and places and links them to pages.
		 *
		 * @param Story $story
		 * @param array $data
		 * @return void
		 */
		private function saveEntitiesAndLinks(Story $story, array $data): void
		{
			$pagesByNumber = $story->pages->keyBy('page_number');

			foreach ($data['characters'] as $charData) {
				$character = $story->characters()->create([
					'name' => $charData['name'],
					'description' => '', // Description will be generated later
				]);

				$pageIdsToSync = collect($charData['pages'])
					->map(fn ($pageNumber) => $pagesByNumber[$pageNumber]->id ?? null)
					->filter()
					->all();

				if (!empty($pageIdsToSync)) {
					$character->pages()->sync($pageIdsToSync);
				}
			}

			foreach ($data['places'] as $placeData) {
				$place = $story->places()->create([
					'name' => $placeData['name'],
					'description' => '', // Description will be generated later
				]);

				$pageIdsToSync = collect($placeData['pages'])
					->map(fn ($pageNumber) => $pagesByNumber[$pageNumber]->id ?? null)
					->filter()
					->all();

				if (!empty($pageIdsToSync)) {
					$place->pages()->sync($pageIdsToSync);
				}
			}
		}
	}
