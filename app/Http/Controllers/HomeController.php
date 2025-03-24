<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;

	class HomeController extends Controller
	{
		/**
		 * Create a new controller instance.
		 *
		 * @return void
		 */
		public function __construct()
		{
			$this->middleware('auth');
		}

		/**
		 * Show the application dashboard.
		 *
		 * @return \Illuminate\Contracts\Support\Renderable
		 */
		public function index()
		{
			$userId = auth()->id();

			// Count images by model
			$modelStats = Prompt::where('user_id', $userId)
				->whereNotNull('filename')
				->select('model', DB::raw('count(*) as count'))
				->groupBy('model')
				->get()
				->pluck('count', 'model')
				->toArray();

			// Count images by generation type
			$generationTypeStats = Prompt::where('user_id', $userId)
				->whereNotNull('filename')
				->select('generation_type', DB::raw('count(*) as count'))
				->groupBy('generation_type')
				->get()
				->pluck('count', 'generation_type')
				->toArray();

			// Total images
			$totalImages = Prompt::where('user_id', $userId)
				->whereNotNull('filename')
				->count();

			// Count of upscaled images
			$upscaledImages = Prompt::where('user_id', $userId)
				->where('upscale_status', 2)
				->count();

			// Count of images with notes/comments
			$imagesWithNotes = Prompt::where('user_id', $userId)
				->whereNotNull('notes')
				->whereRaw("LENGTH(TRIM(notes)) > 0")
				->count();

			return view('home', compact(
				'modelStats',
				'generationTypeStats',
				'totalImages',
				'upscaledImages',
				'imagesWithNotes'
			));
		}
	}
