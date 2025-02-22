<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class Prompt extends Model
	{
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
		];

		protected $casts = [
			'upload_to_s3' => 'boolean',
		];

		public function setting()
		{
			return $this->belongsTo(PromptSetting::class, 'prompt_setting_id');
		}

	}
