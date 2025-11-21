<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class UserUpscaleSetting extends Model
	{
		protected $fillable = ['user_id', 'upscale_model_id', 'settings', 'is_active'];

		protected $casts = [
			'settings' => 'array',
			'is_active' => 'boolean',
		];

		public function model()
		{
			return $this->belongsTo(UpscaleModel::class, 'upscale_model_id');
		}
	}
