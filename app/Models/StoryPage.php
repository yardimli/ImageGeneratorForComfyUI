<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class StoryPage extends Model
	{
		use HasFactory;

		protected $table = 'story_pages';

		protected $fillable = [
			'story_id',
			'page_number',
			'story_text',
			'image_prompt',
			'image_path',
		];

		public function story()
		{
			return $this->belongsTo(Story::class);
		}

		public function characters()
		{
			return $this->belongsToMany(StoryCharacter::class, 'story_page_character');
		}

		public function places()
		{
			return $this->belongsToMany(StoryPlace::class, 'story_page_place');
		}
	}
