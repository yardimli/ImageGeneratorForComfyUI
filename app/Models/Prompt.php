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
			// START MODIFICATION
			'lora_name',
			'strength_model',
			'guidance',
			// START NEW MODIFICATION
			'story_page_id',
			'story_character_id',
			'story_place_id',
			// END NEW MODIFICATION
			// END MODIFICATION
			'upload_to_s3',
			'generation_type',
			'input_image_1',
			'input_image_1_strength',
			'input_image_2',
			'input_image_2_strength',
			'left_padding',
			'right_padding',
			'top_padding',
			'bottom_padding',
			'feathering',
			'source_image',
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
