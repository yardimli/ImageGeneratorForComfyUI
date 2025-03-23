<?php namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Rolandstarke\Thumbnail\Facades\Thumbnail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
	public function index(Request $request)
	{
		$sort = $request->query('sort', 'updated_at');
		$search = $request->query('search');

		// If search is active, select all types
		if (!empty($search)) {
			$selectedTypes = ['mix', 'mix-one', 'schnell', 'dev', 'minimax', 'minimax-expand', 'imagen3', 'aura-flow','ideogram-v2a'];
		} else {
			$selectedTypes = $request->query('types', ['dev']);
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
			$query->where(function($q) use ($search) {
				$q->where('generated_prompt', 'like', '%' . $search . '%')
					->orWhere('notes', 'like', '%' . $search . '%');
			});
		}

		// Separate generation types from models
		$generationTypes = array_intersect($selectedTypes, ['mix', 'mix-one']);
		$models = array_diff($selectedTypes, ['mix', 'mix-one']);

		// Apply filtering logic
		if (!empty($generationTypes) || !empty($models)) {
			$query->where(function($q) use ($generationTypes, $models) {
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
			]);
		}
	}

	public function filter(Request $request)
	{
		$sourceImage = $request->query('source_image');
		$sort = $request->query('sort', 'updated_at');
		$search = $request->query('search');

		// If search is active, select all types
		if (!empty($search)) {
			$selectedTypes = ['mix', 'mix-one', 'schnell', 'dev', 'minimax', 'minimax-expand', 'imagen3', 'aura-flow','ideogram-v2a'];
		} else {
			$selectedTypes = $request->query('types', ['dev']);
			if (!is_array($selectedTypes)) {
				$selectedTypes = [$selectedTypes];
			}
		}

		$query = Prompt::where('user_id', auth()->id())
			->whereNotNull('filename');

		// Apply search if provided
		if (!empty($search)) {
			$query->where(function($q) use ($search) {
				$q->where('generated_prompt', 'like', '%' . $search . '%')
					->orWhere('notes', 'like', '%' . $search . '%');
			});
		}

		// Separate generation types from models
		$generationTypes = array_intersect($selectedTypes, ['mix', 'mix-one']);
		$models = array_diff($selectedTypes, ['mix', 'mix-one']);

		// Apply filtering logic
		if (!empty($generationTypes) || !empty($models)) {
			$query->where(function($q) use ($generationTypes, $models) {
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

		return view('gallery.index', compact('images', 'filterActive', 'filterDescription', 'sort', 'selectedTypes'));
	}
}
