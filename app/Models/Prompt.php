<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class Prompt extends Model
	{
		protected $fillable = [
			'user_id',
			'prompt_setting_id',
			'original_prompt',
			'generated_prompt',
			'width',
			'height',
			'filename',
		];

		public function setting()
		{
			return $this->belongsTo(PromptSetting::class, 'prompt_setting_id');
		}

	}
