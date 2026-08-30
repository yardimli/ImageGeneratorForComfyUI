<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use Illuminate\Http\Request;
	use Rolandstarke\Thumbnail\Facades\Thumbnail;

	class GalleryController extends Controller
	{

		public function index(Request $request)
		{
			$sort = $request->query('sort', 'updated_at');
			if (!in_array($sort, ['created_at', 'updated_at'], true)) {
				$sort = 'updated_at';
			}

			$search = $request->query('search');
			$perPage = (int) $request->query('perPage', 40);
			$perPage = in_array($perPage, [10, 40, 80, 160, 240], true) ? $perPage : 40;

			$query = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename');

			// Apply search if provided
			if (!empty($search)) {
				$query->where(function ($q) use ($search) {
					$q->where('generated_prompt', 'like', '%' . $search . '%')
						->orWhere('notes', 'like', '%' . $search . '%');
				});
			}

			$images = $query->orderByDesc($sort)->paginate($perPage)->withQueryString();

			$this->addThumbnails($images);

			return view('gallery.index', compact('images', 'sort', 'search', 'perPage'));
		}

		public function filter(Request $request)
		{
			$sourceImage = $request->query('source_image');
			$sort = $request->query('sort', 'updated_at');
			if (!in_array($sort, ['created_at', 'updated_at'], true)) {
				$sort = 'updated_at';
			}

			$search = $request->query('search');
			$perPage = (int) $request->query('perPage', 40);
			$perPage = in_array($perPage, [10, 40, 80, 160, 240], true) ? $perPage : 40;

			$query = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename');

			// Apply search if provided
			if (!empty($search)) {
				$query->where(function ($q) use ($search) {
					$q->where('generated_prompt', 'like', '%' . $search . '%')
						->orWhere('notes', 'like', '%' . $search . '%');
				});
			}

			$images = $query->orderByDesc($sort)->paginate($perPage)->withQueryString();

			$this->addThumbnails($images);

			$filterActive = !empty($sourceImage);
			$filterDescription = "";

			if ($filterActive && $sourceImage) {
				$filterDescription = "Images generated using source: " . basename($sourceImage);
			}

			return view('gallery.index', compact(
				'images',
				'filterActive',
				'filterDescription',
				'sort',
				'search',
				'perPage'
			));
		}

		private function addThumbnails($images): void
		{
			$images->getCollection()->transform(function ($prompt) {
				if ($prompt->filename && stripos($prompt->filename, 'https') !== false) {
					$prompt->thumbnail = Thumbnail::src($prompt->filename)
						->preset('thumbnail_450_jpg')
						->url();
				}

				return $prompt;
			});
		}
	}
