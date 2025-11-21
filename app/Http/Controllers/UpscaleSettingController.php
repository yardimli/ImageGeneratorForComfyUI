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

			$activeSetting = UserUpscaleSetting::where('user_id', auth()->id())
				->where('is_active', true)
				->first();

			$activeModelId = $activeSetting ? $activeSetting->upscale_model_id : ($models->first()->id ?? 0);

			$modelSettings = [];
			foreach ($models as $model) {
				$userSetting = UserUpscaleSetting::where('user_id', auth()->id())
					->where('upscale_model_id', $model->id)
					->first();

				// Merge user settings with defaults to ensure all keys exist
				$defaults = $model->default_settings ?? [];
				$saved = $userSetting ? $userSetting->settings : [];
				$modelSettings[$model->id] = array_merge($defaults, $saved);
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
			$model = UpscaleModel::findOrFail($validated['upscale_model_id']);
			$schema = $model->input_schema ?? [];
			$rawSettings = $validated['settings'];

			// Start with defaults to preserve non-form fields (like format, version, etc.)
			$finalSettings = $model->default_settings ?? [];

			// Process and cast form inputs based on schema definition
			foreach ($schema as $field) {
				$key = $field['name'];

				// Skip if not in request (unless it's a checkbox which might be missing when unchecked)
				if (!isset($rawSettings[$key]) && $field['type'] !== 'boolean') {
					continue;
				}

				$value = $rawSettings[$key] ?? null;

				if ($field['type'] === 'number') {
					// Cast to integer or float
					// Adding 0 is a simple way to cast "5" to 5 and "5.5" to 5.5
					$finalSettings[$key] = is_numeric($value) ? $value + 0 : 0;
				} elseif ($field['type'] === 'boolean') {
					// Handle boolean casting
					$finalSettings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
				} else {
					// Strings (text, textarea, select)
					$finalSettings[$key] = (string)$value;
				}
			}

			// Deactivate all other settings for this user
			UserUpscaleSetting::where('user_id', $userId)->update(['is_active' => false]);

			// Update or Create the setting
			UserUpscaleSetting::updateOrCreate(
				[
					'user_id' => $userId,
					'upscale_model_id' => $validated['upscale_model_id']
				],
				[
					'settings' => $finalSettings,
					'is_active' => true
				]
			);

			return redirect()->route('upscale-settings.index')->with('success', 'Upscale settings updated successfully.');
		}
	}
