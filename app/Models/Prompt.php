<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class Prompt extends Model
	{
		use HasFactory;

		protected $fillable = [
			'user_id',
			'render_status',
			'prompt_setting_id',
			'original_prompt',
			'generated_prompt',
			'width',
			'height',
			'model',
			'upload_to_s3',
			'filename',
			'notes',
			'upscale_url',
			'upscale_result',
			'upscale_prediction_id',
			'upscale_status',
			'upscale_status_url',
		];

		protected $casts = [
			'upload_to_s3' => 'boolean',
		];

		public function setting()
		{
			return $this->belongsTo(PromptSetting::class, 'prompt_setting_id');
		}

	}
