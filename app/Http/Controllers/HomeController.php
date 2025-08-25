<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
	use Exception;
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
		 * Gets a list of all supported model short names from the JSON file.
		 * This ensures the dashboard stats page is always in sync with the models the app supports.
		 *
		 * @return array
		 */
		private function getSupportedModels(): array
		{
			try {
				$jsonString = file_get_contents(resource_path('text-to-image-models/models.json'));
				$allModels = json_decode($jsonString, true);
			} catch (Exception $e) {
				Log::error('Failed to load image models from JSON for home page stats: ' . $e->getMessage());
				// Fallback to a hardcoded list if the file is missing or invalid
				return ['schnell', 'dev', 'minimax', 'minimax-expand', 'imagen3', 'aura-flow', 'ideogram-v2a', 'luma-photon', 'recraft-20b', 'fal-ai/qwen-image'];
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

			// Manually add minimax-expand if minimax was found, as it's a variant
			if (isset($foundModels['minimax'])) {
				$modelShortNames[] = 'minimax-expand';
			}

			// Sort for consistent display
			sort($modelShortNames);
			return array_unique($modelShortNames);
		}

		/**
		 * Show the application dashboard.
		 *
		 * @return \Illuminate\Contracts\Support\Renderable
		 */
		public function index()
		{
			$userId = auth()->id();

			// Fetch statistics for the authenticated user
			$modelStats = Prompt::where('user_id', $userId)
				->whereNotNull('filename')
				->groupBy('model')
				->selectRaw('model, count(*) as count')
				->pluck('count', 'model');

			$generationTypeStats = Prompt::where('user_id', $userId)
				->whereNotNull('filename')
				->groupBy('generation_type')
				->selectRaw('generation_type, count(*) as count')
				->pluck('count', 'generation_type');

			$totalImages = Prompt::where('user_id', $userId)->whereNotNull('filename')->count();
			$upscaledImages = Prompt::where('user_id', $userId)->where('upscale_status', 2)->count();
			$imagesWithNotes = Prompt::where('user_id', $userId)->whereNotNull('notes')->where('notes', '!=', '')->count();

			// MODIFICATION: Get the model list dynamically instead of hardcoding in the view
			$supportedModels = $this->getSupportedModels();

			return view('home', compact(
				'modelStats',
				'generationTypeStats',
				'totalImages',
				'upscaledImages',
				'imagesWithNotes',
				'supportedModels' // Pass the dynamic list to the view
			));
		}
	}
