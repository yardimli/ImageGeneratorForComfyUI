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
		$sort = $request->query('sort', 'updated_at'); // Default to updated_at
		$type = $request->query('type', 'all'); // Default to all types
		$groupByDay = $request->query('group') !== 'false'; // Convert to boolean properly
		$date = $request->query('date');

		$query = Prompt::where('user_id', auth()->id())
			->whereNotNull('filename');

		// Apply type filter
		if ($type === 'mix') {
			$query->whereIn('generation_type', ['mix', 'mix-one']);
		} elseif ($type === 'mix-one') {
			$query->where('generation_type', 'mix-one');
		} elseif ($type === 'mix-dual') {
			$query->where('generation_type', 'mix');
		} elseif ($type === 'other') {
			$query->whereNotIn('generation_type', ['mix', 'mix-one']);
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
			// Get distinct days first
			$days = $query->clone()
				->select(DB::raw('DATE(created_at) as date'))
				->groupBy('date')
				->orderBy('date', 'desc')
				->paginate(5, ['*'], 'day_page');

			$groupedImages = [];

			foreach ($days as $day) {
				$totalCount = $query->clone()
					->whereDate('created_at', $day->date)
					->count();

				$dayImages = $query->clone()
					->whereDate('created_at', $day->date)
					->limit(8) // Show only 8 images per day initially
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
				'type' => $type,
				'groupByDay' => $groupByDay,
				'date' => $date,
			]);
		} else {
			// Regular pagination for specific day view
			$images = $query->paginate(32);

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
				'type' => $type,
				'groupByDay' => $groupByDay,
				'date' => $date,
			]);
		}
	}

	public function filter(Request $request)
	{
		$sourceImage = $request->query('source_image');
		$sort = $request->query('sort', 'updated_at');

		$query = Prompt::where('user_id', auth()->id())
			->whereNotNull('filename');

		if ($sourceImage) {
			$query->where(function($q) use ($sourceImage) {
				$q->where('input_image_1', $sourceImage)
					->orWhere('input_image_2', $sourceImage);
			});
		}

		$images = $query->orderBy($sort, 'desc')
			->paginate(32);

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

		return view('gallery.index', compact('images', 'filterActive', 'filterDescription', 'sort'));
	}
}
