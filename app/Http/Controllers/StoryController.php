<?php

	namespace App\Http\Controllers;

	use App\Models\Story;
	use App\Models\StoryCharacter;
	use App\Models\StoryPage;
	use App\Models\StoryPlace;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;

// START MODIFICATION: Removed Gate facade as it's no longer used.
// use Illuminate\Support\Facades\Gate;
// END MODIFICATION

	class StoryController extends Controller
	{
		/**
		 * Display a listing of the user's stories.
		 */
		public function index()
		{
			$stories = Story::where('user_id', auth()->id())->orderBy('title')->get();
			return view('story.index', compact('stories'));
		}

		/**
		 * Show the form for creating a new story.
		 */
		public function create()
		{
			return view('story.create');
		}

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

		/**
		 * Show the form for editing the specified story.
		 */
		public function edit(Story $story)
		{
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

			$story->load(['pages.characters', 'pages.places', 'characters', 'places']);
			return view('story.edit', compact('story'));
		}

		/**
		 * Update the specified story in storage.
		 */
		public function update(Request $request, Story $story)
		{
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

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
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

			$story->delete();
			return redirect()->route('stories.index')->with('success', 'Story deleted successfully.');
		}

		/**
		 * Show the character management page for a story.
		 */
		public function characters(Story $story)
		{
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

			$story->load('characters');
			return view('story.characters', compact('story'));
		}

		/**
		 * Update the characters for a story.
		 */
		public function updateCharacters(Request $request, Story $story)
		{
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

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
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

			$story->load('places');
			return view('story.places', compact('story'));
		}

		/**
		 * Update the places for a story.
		 */
		public function updatePlaces(Request $request, Story $story)
		{
			// START MODIFICATION: Replaced Gate with a direct ownership check.
			if ($story->user_id !== auth()->id()) {
				abort(403, 'Unauthorized action.');
			}
			// END MODIFICATION

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
