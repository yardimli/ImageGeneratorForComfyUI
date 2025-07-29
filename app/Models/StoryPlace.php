<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class StoryPlace extends Model
	{
		use HasFactory;

		protected $table = 'story_places';

		// START MODIFICATION: Add image_prompt to fillable fields.
		protected $fillable = [
			'story_id',
			'name',
			'description',
			'image_prompt',
			'image_path',
		];
		// END MODIFICATION

		public function story()
		{
			return $this->belongsTo(Story::class);
		}

		public function pages()
		{
			return $this->belongsToMany(StoryPage::class, 'story_page_place');
		}
	}
