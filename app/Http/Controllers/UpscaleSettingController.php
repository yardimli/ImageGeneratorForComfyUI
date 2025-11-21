<?php

	namespace App\Http\Controllers;

	use App\Models\UpscaleModel;
	use App\Models\UserUpscaleSetting;
	use Illuminate\Http\Request;

	class UpscaleSettingController extends Controller
	{
		public function index()
		{
			$models = UpscaleModel::all();

			// Get user's active setting, or default to first model
			$activeSetting = UserUpscaleSetting::where('user_id', auth()->id())
				->where('is_active', true)
				->first();

			$activeModelId = $activeSetting ? $activeSetting->upscale_model_id : $models->first()->id;

			// Prepare settings for view (merge defaults with user saved settings)
			$modelSettings = [];
			foreach ($models as $model) {
				$userSetting = UserUpscaleSetting::where('user_id', auth()->id())
					->where('upscale_model_id', $model->id)
					->first();

				$modelSettings[$model->id] = $userSetting ? $userSetting->settings : $model->default_settings;
			}

			return view('admin.upscale-settings.index', compact('models', 'activeModelId', 'modelSettings'));
		}

		public function update(Request $request)
		{
			$validated = $request->validate([
				'upscale_model_id' => 'required|exists:upscale_models,id',
				'settings' => 'required|array',
			]);

			$userId = auth()->id();

			// Deactivate all other settings for this user
			UserUpscaleSetting::where('user_id', $userId)->update(['is_active' => false]);

			// Update or Create the setting for the selected model and set as active
			UserUpscaleSetting::updateOrCreate(
				[
					'user_id' => $userId,
					'upscale_model_id' => $validated['upscale_model_id']
				],
				[
					'settings' => $validated['settings'],
					'is_active' => true
				]
			);

			return redirect()->route('upscale-settings.index')->with('success', 'Upscale settings updated successfully.');
		}
	}
