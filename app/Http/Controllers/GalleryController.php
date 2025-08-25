<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use Illuminate\Http\Request;
	use Rolandstarke\Thumbnail\Facades\Thumbnail;
	use Carbon\Carbon;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	use Exception;

	class GalleryController extends Controller
	{
		// MODIFICATION START: Added helper to get all filterable types from JSON.
		/**
		 * Gets a list of all filterable types, including models from JSON and static generation types.
		 *
		 * @return array
		 */
		private function getAllFilterableTypes(): array
		{
			try {
				$jsonString = file_get_contents(resource_path('text-to-image-models/models.json'));
				$allModels = json_decode($jsonString, true);
			} catch (Exception $e) {
				Log::error('Failed to load image models from JSON for gallery filter: ' . $e->getMessage());
				// Fallback to a hardcoded list if the file is missing or invalid
				return ['mix', 'mix-one', 'schnell', 'dev', 'minimax', 'minimax-expand', 'imagen3', 'aura-flow', 'ideogram-v2a', 'luma-photon', 'recraft-20b', 'fal-ai/qwen-image'];
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

			$modelShortNames = [];
			$foundModels = [];

			foreach ($allModels as $modelData) {
				$shortName = array_search($modelData['name'], $supportedModelsMap);
				if ($shortName !== false) {
					$modelShortNames[] = $shortName;
					$foundModels[$shortName] = true;
				}
			}

			// Manually add minimax-expand if minimax was found
			if (isset($foundModels['minimax'])) {
				$modelShortNames[] = 'minimax-expand';
			}

			// Add the generation types that are not models
			$generationTypes = ['mix', 'mix-one'];

			return array_merge($generationTypes, array_unique($modelShortNames));
		}

		/**
		 * Prepares a formatted list of models for the view's filter dropdown.
		 *
		 * @return array
		 */
		private function getModelsForView(): array
		{
			$allFilterableTypes = $this->getAllFilterableTypes();
			$models = array_diff($allFilterableTypes, ['mix', 'mix-one']);
			$viewModels = [];

			foreach ($models as $modelId) {
				$viewModels[] = [
					'id' => $modelId,
					'name' => ucfirst(str_replace(['-', '_', '/'], ' ', $modelId)),
				];
			}

			usort($viewModels, fn($a, $b) => strcmp($a['name'], $b['name']));
			return $viewModels;
		}
		// MODIFICATION END

		public function index(Request $request)
		{
			$sort = $request->query('sort', 'updated_at');
			$search = $request->query('search');

			// MODIFICATION START: Get all filterable types dynamically.
			$allFilterableTypes = $this->getAllFilterableTypes();
			// MODIFICATION END

			// If search is active, select all types
			if (!empty($search)) {
				// MODIFICATION START: Use dynamic list.
				$selectedTypes = $allFilterableTypes;
				// MODIFICATION END
			} else {
				// MODIFICATION START: Use dynamic list as default.
				$selectedTypes = $request->query('types', $allFilterableTypes);
				// MODIFICATION END

				//check if selectedTypes contains 'all' then select all types
				if (in_array('all', $selectedTypes)) {
					// MODIFICATION START: Use dynamic list.
					$selectedTypes = $allFilterableTypes;
					// MODIFICATION END
				}

				if (!is_array($selectedTypes)) {
					$selectedTypes = [$selectedTypes];
				}
			}

			$groupByDay = $request->query('group') !== 'false';
			$date = $request->query('date');

			$query = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename');

			// Apply search if provided
			if (!empty($search)) {
				$query->where(function ($q) use ($search) {
					$q->where('generated_prompt', 'like', '%' . $search . '%')
						->orWhere('notes', 'like', '%' . $search . '%');
				});
			}

			// Separate generation types from models
			$generationTypes = array_intersect($selectedTypes, ['mix', 'mix-one']);
			$models = array_diff($selectedTypes, ['mix', 'mix-one']);

			// Apply filtering logic
			if (!empty($generationTypes) || !empty($models)) {
				$query->where(function ($q) use ($generationTypes, $models) {
					if (!empty($generationTypes)) {
						$q->whereIn('generation_type', $generationTypes);
					}

					if (!empty($models)) {
						if (!empty($generationTypes)) {
							$q->orWhereIn('model', $models);
						} else {
							$q->whereIn('model', $models);
						}
					}
				});
			}

			// Apply date filter if viewing a specific day
			if ($date) {
				$selectedDate = Carbon::parse($date);
				$query->whereDate('created_at', $selectedDate);
				$groupByDay = false; // Disable grouping when viewing a specific day
			}

			// Apply sorting
			$query->orderBy($sort, 'desc');

			// MODIFICATION START: Get models for the view's filter dropdown.
			$viewModels = $this->getModelsForView();
			// MODIFICATION END

			if ($groupByDay && !$date) {
				// Get distinct days first - now showing 14 days instead of 5
				$days = $query->clone()
					->select(DB::raw('DATE(created_at) as date'))
					->groupBy('date')
					->orderBy('date', 'desc')
					->paginate(14, ['*'], 'day_page');

				$groupedImages = [];
				foreach ($days as $day) {
					$totalCount = $query->clone()
						->whereDate('created_at', $day->date)
						->count();

					$dayImages = $query->clone()
						->whereDate('created_at', $day->date)
						->limit(8)
						->get();

					$dayImages->transform(function ($prompt) {
						if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
							$prompt->thumbnail = Thumbnail::src($prompt->filename)
								->preset('thumbnail_450_jpg')
								->url();
						}
						return $prompt;
					});

					$dayImages->totalCount = $totalCount;
					$groupedImages[$day->date] = $dayImages;
				}

				return view('gallery.index', [
					'groupedImages' => $groupedImages,
					'days' => $days,
					'sort' => $sort,
					'selectedTypes' => $selectedTypes,
					'groupByDay' => $groupByDay,
					'date' => $date,
					'search' => $search,
					'viewModels' => $viewModels, // Pass models to the view
				]);
			} else {
				// Regular pagination for specific day view
				$images = $query->paginate(60);

				$images->getCollection()->transform(function ($prompt) {
					if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
						$prompt->thumbnail = Thumbnail::src($prompt->filename)
							->preset('thumbnail_450_jpg')
							->url();
					}
					return $prompt;
				});

				return view('gallery.index', [
					'images' => $images,
					'sort' => $sort,
					'selectedTypes' => $selectedTypes,
					'groupByDay' => $groupByDay,
					'date' => $date,
					'search' => $search,
					'viewModels' => $viewModels, // Pass models to the view
				]);
			}
		}

		public function filter(Request $request)
		{
			$sourceImage = $request->query('source_image');
			$sort = $request->query('sort', 'updated_at');
			$search = $request->query('search');

			// MODIFICATION START: Get all filterable types dynamically.
			$allFilterableTypes = $this->getAllFilterableTypes();
			// MODIFICATION END

			// If search is active, select all types
			if (!empty($search)) {
				// MODIFICATION START: Use dynamic list.
				$selectedTypes = $allFilterableTypes;
				// MODIFICATION END
			} else {
				// MODIFICATION START: Use dynamic list as default.
				$selectedTypes = $request->query('types', $allFilterableTypes);
				// MODIFICATION END
				if (!is_array($selectedTypes)) {
					$selectedTypes = [$selectedTypes];
				}
			}

			$query = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename');

			// Apply search if provided
			if (!empty($search)) {
				$query->where(function ($q) use ($search) {
					$q->where('generated_prompt', 'like', '%' . $search . '%')
						->orWhere('notes', 'like', '%' . $search . '%');
				});
			}

			// Separate generation types from models
			$generationTypes = array_intersect($selectedTypes, ['mix', 'mix-one']);
			$models = array_diff($selectedTypes, ['mix', 'mix-one']);

			// Apply filtering logic
			if (!empty($generationTypes) || !empty($models)) {
				$query->where(function ($q) use ($generationTypes, $models) {
					if (!empty($generationTypes)) {
						$q->whereIn('generation_type', $generationTypes);
					}

					if (!empty($models)) {
						if (!empty($generationTypes)) {
							$q->orWhereIn('model', $models);
						} else {
							$q->whereIn('model', $models);
						}
					}
				});
			}

			$images = $query->orderBy($sort, 'desc')
				->paginate(60);

			$images->getCollection()->transform(function ($prompt) {
				if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
					$prompt->thumbnail = Thumbnail::src($prompt->filename)
						->preset('thumbnail_450_jpg')
						->url();
				}
				return $prompt;
			});

			$filterActive = !empty($sourceImage);
			$filterDescription = "";

			if ($filterActive && $sourceImage) {
				$filterDescription = "Images generated using source: " . basename($sourceImage);
			}

			// MODIFICATION START: Get models for the view's filter dropdown.
			$viewModels = $this->getModelsForView();
			// MODIFICATION END

			return view('gallery.index', compact('images', 'filterActive', 'filterDescription', 'sort', 'selectedTypes', 'viewModels'));
		}
	}
