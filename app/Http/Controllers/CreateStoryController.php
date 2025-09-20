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
	use Illuminate\Validation\Rule;
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
		 * MODIFIED: Show Step 1 of the form for creating a new story with AI.
		 *
		 * @param LlmController $llmController
		 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
		 */
		public function createWithAiStep1(LlmController $llmController) // MODIFIED: Renamed for wizard step 1
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

				$prompts = [
					'content' => LlmPrompt::where('name', 'story.generate.content')->firstOrFail(),
					// MODIFIED: Removed other prompts as they are not needed in the view anymore
				];

				return view('story.create-ai-step1', compact('models', 'summaries', 'prompts')); // MODIFIED: Point to new step 1 view
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models/prompts for AI Story Creator: ' . $e->getMessage());
				return redirect()->route('stories.index')->with('error', 'Could not fetch AI models or prompts at this time. Please try again later.');
			}
		}

		/**
		 * NEW: Show Step 2 of the AI story creation wizard (Review Content).
		 *
		 * @param Story $story
		 * @return \Illuminate\View\View
		 */
		public function createWithAiStep2(Story $story)
		{
			$story->load('pages');
			return view('story.create-ai-step2', compact('story'));
		}

		/**
		 * NEW: Show Step 3 of the AI story creation wizard (Describe Entities).
		 *
		 * @param Story $story
		 * @param LlmController $llmController
		 * @return \Illuminate\View\View
		 */
		public function createWithAiStep3(Story $story, LlmController $llmController) // MODIFICATION: Inject LlmController
		{
			$story->load('characters', 'places');

			// START MODIFICATION: Fetch LLM models for the view.
			try {
				$modelsResponse = $llmController->getModels();
				$models = $llmController->processModelsForView($modelsResponse);
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for AI Story Creator Step 3: ' . $e->getMessage());
				// Don't fail the page load, just show an error and an empty list.
				$models = [];
				session()->flash('error', 'Could not fetch AI models at this time. Please try again later.');
			}
			// END MODIFICATION

			return view('story.create-ai-step3', compact('story', 'models')); // MODIFICATION: Pass models to the view
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
		 * MODIFIED: Step 1 of AI generation. Generates story content (title, desc, pages) via AJAX.
		 *
		 * @param Request $request
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateContent(Request $request, LlmController $llmController)
		{
			$validated = $request->validate([
				'model' => 'required|string',
				'level' => 'required|string|max:50',
				'prompt_content_generation' => 'required|string',
			]);

			try {
				$contentPrompt = $validated['prompt_content_generation'];

				$storyContentData = $llmController->callLlmSync(
					$contentPrompt,
					$validated['model'],
					'AI Story Generation - Content',
					0.7,
					'json_object'
				);

				$this->validateContentData($storyContentData);

				// MODIFIED: Return the generated data as JSON instead of saving and redirecting.
				return response()->json($storyContentData);
			} catch (ValidationException $e) {
				// MODIFIED: Return JSON error
				return response()->json(['message' => 'The AI returned story content in an invalid format. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Story Content Generation Failed: ' . $e->getMessage());
				// MODIFIED: Return JSON error
				return response()->json(['message' => 'An error occurred while generating the story content. Error: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * NEW: Step 1 continuation. Stores the generated/edited content and proceeds to step 2.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function storeContent(Request $request)
		{
			$validated = $request->validate([
				'title' => 'required|string|max:255',
				'short_description' => 'required|string',
				'pages' => 'required|array|min:1',
				'pages.*' => 'required|string', // Pages are submitted as simple strings
				'level' => 'required|string|max:50',
				'model' => 'required|string',
				'prompt_content_generation' => 'required|string',
			]);

			try {
				// Re-structure page data to match the format expected by saveStoryFromAiContent
				$contentData = [
					'title' => $validated['title'],
					'description' => $validated['short_description'],
					'pages' => array_map(fn ($content) => ['content' => $content], $validated['pages']),
				];

				// Fetch the default prompts for the other steps directly from the database.
				$defaultPrompts = [
					'entities' => LlmPrompt::where('name', 'story.generate.entities')->firstOrFail()->system_prompt,
					'character' => LlmPrompt::where('name', 'story.character.describe')->firstOrFail()->system_prompt,
					'place' => LlmPrompt::where('name', 'story.place.describe')->firstOrFail()->system_prompt,
				];

				$story = $this->saveStoryFromAiContent($contentData, $validated, $defaultPrompts);

				return redirect()->route('stories.create-ai.step2', $story)->with('success', 'Story content saved! Now, let\'s identify characters and places.');
			} catch (\Exception $e) {
				Log::error('AI Story Content Saving Failed: ' . $e->getMessage());
				return back()->withInput()->with('error', 'An error occurred while saving the story content. Please try again.');
			}
		}

		/**
		 * MODIFIED: Step 2 of AI generation. Generates characters and places from story text via AJAX.
		 *
		 * @param Request $request
		 * @param Story $story
		 * @param LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateEntities(Request $request, Story $story, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt_entity_generation' => 'required|string',
			]);

			try {
				$story->load('pages');
				// MODIFIED: Prepend page numbers to the story text for better context for the LLM.
				$fullStoryText = $story->pages
					->map(fn ($page) => "Page {$page->page_number}: {$page->story_text}")
					->implode("\n\n");

				$entityPrompt = str_replace('{fullStoryText}', $fullStoryText, $validated['prompt_entity_generation']);

				$entityData = $llmController->callLlmSync(
					$entityPrompt,
					$story->model,
					'AI Story Generation - Entities',
					0.7,
					'json_object'
				);

				$this->validateEntityData($entityData);

				// MODIFIED: Return JSON instead of saving.
				return response()->json($entityData);
			} catch (ValidationException $e) {
				// MODIFIED: Return JSON error
				return response()->json(['message' => 'The AI returned characters/places in an invalid format. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Story Entity Generation Failed: ' . $e->getMessage());
				// MODIFIED: Return JSON error
				return response()->json(['message' => 'An error occurred while generating characters and places. Error: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * NEW: Step 2 continuation. Stores the generated/edited entities and proceeds to step 3.
		 *
		 * @param Request $request
		 * @param Story $story
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function storeEntities(Request $request, Story $story)
		{
			$validated = $request->validate([
				'prompt_entity_generation' => 'required|string',
				'characters' => 'present|array',
				'characters.*.name' => 'required|string',
				'characters.*.pages' => 'present|array',
				'characters.*.pages.*' => 'integer',
				'places' => 'present|array',
				'places.*.name' => 'required|string',
				'places.*.pages' => 'present|array',
				'places.*.pages.*' => 'integer',
			]);

			try {
				DB::transaction(function () use ($story, $validated) {
					// Update the story's prompt if it was edited
					if ($story->prompt_entity_generation !== $validated['prompt_entity_generation']) {
						$story->prompt_entity_generation = $validated['prompt_entity_generation'];
						$story->save();
					}

					// Clear existing entities to prevent duplicates if user goes back and forth
					$story->characters()->delete();
					$story->places()->delete();

					$entityData = [
						'characters' => $validated['characters'],
						'places' => $validated['places'],
					];

					$this->saveEntitiesAndLinks($story, $entityData);
				});

				return redirect()->route('stories.create-ai.step3', $story)->with('success', 'Characters and places saved! Now let\'s describe them.');
			} catch (\Exception $e) {
				Log::error('AI Story Entity Saving Failed: ' . $e->getMessage());
				return back()->withInput()->with('error', 'An error occurred while saving the entities. Please try again.');
			}
		}


		/**
		 * MODIFIED: Step 3 of AI generation. Generates a description for a single entity. Does not save.
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
				'prompt' => 'required|string',
				'model' => 'required|string', // START MODIFICATION: Add model validation
			]);

			try {
				$story = Story::with('pages', 'characters', 'places')->findOrFail($validated['story_id']);
				$fullStoryText = $story->pages->pluck('story_text')->implode("\n\n");

				$promptTemplate = $validated['prompt'];

				if ($validated['type'] === 'character') {
					$prompt = $this->buildSingleCharacterDescriptionPrompt($story, $fullStoryText, $validated['name'], $promptTemplate);
					$callReason = 'AI Story Generation - Character Description';
				} else {
					$prompt = $this->buildSinglePlaceDescriptionPrompt($story, $fullStoryText, $validated['name'], $promptTemplate);
					$callReason = 'AI Story Generation - Place Description';
				}

				$descriptionData = $llmController->callLlmSync(
					$prompt,
					$validated['model'], // MODIFICATION: Use model from request instead of $story->model
					$callReason,
					0.7,
					'json_object'
				);

				$this->validateSingleEntityData($descriptionData, $validated['name']);

				// MODIFIED: Do not save here. Just return the generated description.
				// The frontend will send a separate request to save it after user review/edit.
				return response()->json(['success' => true, 'description' => $descriptionData['description']]);
			} catch (ValidationException $e) {
				return response()->json(['message' => 'The AI returned data in an invalid format for ' . $validated['name'] . '. Please try again.', 'errors' => $e->errors()], 422);
			} catch (\Exception $e) {
				Log::error('AI Description Generation Failed: ' . $e->getMessage());
				return response()->json(['message' => 'An error occurred while generating a description for ' . $validated['name'] . '. Error: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * NEW: Step 3 action. Stores the final description for a single entity.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function storeDescription(Request $request)
		{
			$validated = $request->validate([
				'story_id' => 'required|integer|exists:stories,id',
				'type' => 'required|string|in:character,place',
				'name' => 'required|string',
				'description' => 'required|string',
			]);

			try {
				$story = Story::findOrFail($validated['story_id']);

				if ($validated['type'] === 'character') {
					$entity = $story->characters()->where('name', $validated['name'])->firstOrFail();
				} else { // place
					$entity = $story->places()->where('name', $validated['name'])->firstOrFail();
				}

				$entity->description = $validated['description'];
				$entity->save();

				// Also update the prompts on the story model if they were changed on the frontend
				if ($request->has('prompt_character_description')) {
					$story->prompt_character_description = $request->input('prompt_character_description');
				}
				if ($request->has('prompt_place_description')) {
					$story->prompt_place_description = $request->input('prompt_place_description');
				}
				if ($story->isDirty()) {
					$story->save();
				}

				return response()->json(['success' => true, 'message' => 'Description for ' . $validated['name'] . ' saved.']);
			} catch (\Exception $e) {
				Log::error('AI Description Saving Failed: ' . $e->getMessage());
				return response()->json(['message' => 'An error occurred while saving the description for ' . $validated['name'] . '. Error: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * Builds the prompt for a single character's description using a provided template.
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
				->where('description', '!=', '') // MODIFIED: Also check for empty strings
				->map(fn ($char) => "Character: {$char->name}\nDescription: {$char->description}")
				->implode("\n\n");

			$existingPlaceContext = $story->places
				->whereNotNull('description')
				->where('description', '!=', '') // MODIFIED: Also check for empty strings
				->map(fn ($place) => "Place: {$place->name}\nDescription: {$place->description}")
				->implode("\n\n");

			return str_replace(
				['{fullStoryText}', '{characterName}', '{characterPageContext}', '{existingCharacterContext}', '{existingPlaceContext}'],
				[$fullStoryText, $characterName, $characterPageContext ?: 'N/A', $existingCharacterContext ?: 'N/A', $existingPlaceContext ?: 'N/A'],
				$promptTemplate
			);
		}

		/**
		 * Builds the prompt for a single place's description using a provided template.
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
		 * Validates the story content data from the LLM.
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
		 * Validates the story entity data from the LLM.
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
				// MODIFIED: Use the Rule object for robust validation
				'name' => [
					'required',
					'string',
					Rule::in([$expectedName]) // <-- This is the fix
				],
				'description' => 'required|string|min:1',
			]);

			if ($validator->fails()) {
				Log::error('AI Single Entity Validation Failed: ', $validator->errors()->toArray());
				Log::error('Invalid AI Entity Data: ', $data ?? []);
				throw new ValidationException($validator);
			}
		}

		/**
		 * Saves the initial story and pages from validated AI content data.
		 *
		 * @param array $data
		 * @param array $validatedRequestData
		 * @param array $defaultPrompts
		 * @return Story
		 */
		private function saveStoryFromAiContent(array $data, array $validatedRequestData, array $defaultPrompts): Story
		{
			$story = Story::create([
				'user_id' => auth()->id(),
				'title' => $data['title'],
				'short_description' => $data['description'],
				'level' => $validatedRequestData['level'],
				'initial_prompt' => $validatedRequestData['prompt_content_generation'],
				'model' => $validatedRequestData['model'],
				'prompt_content_generation' => $validatedRequestData['prompt_content_generation'],
				'prompt_entity_generation' => $defaultPrompts['entities'],
				'prompt_character_description' => $defaultPrompts['character'],
				'prompt_place_description' => $defaultPrompts['place'],
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
		 * Saves characters and places and links them to pages.
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
