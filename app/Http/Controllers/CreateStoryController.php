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

					//  Create a map of characters/places to the text of the pages they appear on.
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


					$fullStoryText = implode("\n\n", array_column($storyData['pages'], 'content'));
					$characterNames = array_column($storyData['characters'] ?? [], 'name');
					$placeNames = array_column($storyData['places'] ?? [], 'name');

					// --- STEP 2: Generate Character Descriptions ---
					if (!empty($characterNames)) {
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
		//  Fetch prompt from the database instead of hardcoding.
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
		 * Builds the prompt for the LLM to generate character descriptions.
		 *
		 * @param string $fullStoryText
		 * @param array $characterNames
		 * @param array $characterPageTextMap
		 * @return string
		 */
		//  Fetch prompt from the database and use it to build context.
		private function buildCharacterDescriptionPrompt(string $fullStoryText, array $characterNames, array $characterPageTextMap): string
		{
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

			$llmPrompt = LlmPrompt::where('name', 'story.character.describe')->firstOrFail();
			return str_replace(
				['{fullStoryText}', '{characterContext}'],
				[$fullStoryText, $characterContext],
				$llmPrompt->system_prompt
			);
		}


		/**
		 * Builds the prompt for the LLM to generate place descriptions.
		 *
		 * @param string $fullStoryText
		 * @param array $placeNames
		 * @param array $placePageTextMap
		 * @return string
		 */
		//  Fetch prompt from the database and use it to build context.
		private function buildPlaceDescriptionPrompt(string $fullStoryText, array $placeNames, array $placePageTextMap): string
		{
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

			$llmPrompt = LlmPrompt::where('name', 'story.place.describe')->firstOrFail();
			return str_replace(
				['{fullStoryText}', '{placeContext}'],
				[$fullStoryText, $placeContext],
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
	}
