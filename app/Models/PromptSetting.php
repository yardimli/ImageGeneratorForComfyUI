<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class PromptSetting extends Model
	{
		protected $fillable = [
			'user_id',
			'name',
			'template_path',
			'prompt_template',
			'original_prompt',
			'precision',
			'count',
			'width',
			'height',
			'model',
			'lora_name',
			'strength_model',
			'guidance',
			'story_page_id',
			'story_character_id',
			'story_place_id',
			'prompt_dictionary_entry_id',
			'upload_to_s3',
			'generation_type',
			'input_images_1',
			'input_images_2',
			'input_images', // START MODIFICATION
			'left_padding',
			'right_padding',
			'top_padding',
			'bottom_padding',
			'feathering',
			'source_image',
			'aspect_ratio',
			'prepend_text',
			'append_text',
			'generate_original_prompt',
			'append_to_prompt',
			'render_each_prompt_times',
		];

		protected $casts = [
			'generate_original_prompt' => 'boolean',
			'append_to_prompt' => 'boolean',
			'upload_to_s3' => 'boolean',
			'input_images' => 'array', // END MODIFICATION
		];


		public function prompts()
		{
			return $this->hasMany(Prompt::class);
		}
	}
