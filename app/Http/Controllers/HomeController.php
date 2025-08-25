<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use Exception;
	use Illuminate\Contracts\Support\Renderable;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;

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
		 * @return Renderable
		 */
		public function index(): Renderable
		{
			// MODIFICATION START: Load models from JSON file to display stats.
			$supportedModels = [];
			try {
				$jsonString = file_get_contents(resource_path('text-to-image-models/models.json'));
				$allModels = json_decode($jsonString, true);
				if (json_last_error() === JSON_ERROR_NONE) {
					// This map defines which models get a "short name".
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

					// Get all full names from JSON
					$fullNames = array_column($allModels, 'name');
					// Get all short names from our map
					$shortNames = array_keys($supportedModelsMap);
					// Combine them, including special cases
					$supportedModels = array_unique(array_merge($fullNames, $shortNames, ['minimax-expand']));
					sort($supportedModels);
				} else {
					Log::error('Failed to parse models.json: ' . json_last_error_msg());
				}
			} catch (Exception $e) {
				Log::error('Failed to load image models from JSON for home page: ' . $e->getMessage());
			}
			// MODIFICATION END

			$modelStats = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename')
				->groupBy('model')
				->select('model', DB::raw('count(*) as total'))
				->pluck('total', 'model');

			$generationTypeStats = Prompt::where('user_id', auth()->id())
				->whereNotNull('filename')
				->groupBy('generation_type')
				->select('generation_type', DB::raw('count(*) as total'))
				->pluck('total', 'generation_type');

			$totalImages = Prompt::where('user_id', auth()->id())->whereNotNull('filename')->count();
			$upscaledImages = Prompt::where('user_id', auth()->id())->where('upscale_status', 2)->count();
			$imagesWithNotes = Prompt::where('user_id', auth()->id())->whereNotNull('notes')->where('notes', '!=', '')->count();

			return view('home', compact(
				'modelStats',
				'generationTypeStats',
				'totalImages',
				'upscaledImages',
				'imagesWithNotes',
				'supportedModels' // MODIFICATION: Pass supported models to the view.
			));
		}
	}
