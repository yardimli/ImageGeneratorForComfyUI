<?php

	namespace App\Models;

	// START MODIFICATION: Import Attribute for accessor.
	use Illuminate\Database\Eloquent\Casts\Attribute;
	// END MODIFICATION
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class Story extends Model
	{
		use HasFactory;

		// START MODIFICATION: Add new fields to the fillable array.
		protected $fillable = [
			'user_id',
			'title',
			'short_description',
			'level',
			'initial_prompt',
			'model',
		];
		// END MODIFICATION

		// START MODIFICATION: Add an accessor for the total image count.
		/**
		 * Get the total number of images generated for the story.
		 *
		 * This accessor relies on the withCount of the prompt relationships.
		 *
		 * @return \Illuminate\Database\Eloquent\Casts\Attribute
		 */
		protected function imageCount(): Attribute
		{
			return Attribute::make(
				get: fn () => ($this->page_prompts_count ?? 0) +
					($this->character_prompts_count ?? 0) +
					($this->place_prompts_count ?? 0),
			);
		}
		// END MODIFICATION

		public function user()
		{
			return $this->belongsTo(User::class);
		}

		public function characters()
		{
			return $this->hasMany(StoryCharacter::class);
		}

		public function places()
		{
			return $this->hasMany(StoryPlace::class);
		}

		public function pages()
		{
			return $this->hasMany(StoryPage::class)->orderBy('page_number');
		}

		// START MODIFICATION: Add relationships to count prompts.
		/**
		 * Get all of the prompts for the story's pages.
		 */
		public function pagePrompts()
		{
			return $this->hasManyThrough(Prompt::class, StoryPage::class);
		}

		/**
		 * Get all of the prompts for the story's characters.
		 */
		public function characterPrompts()
		{
			return $this->hasManyThrough(Prompt::class, StoryCharacter::class);
		}

		/**
		 * Get all of the prompts for the story's places.
		 */
		public function placePrompts()
		{
			return $this->hasManyThrough(Prompt::class, StoryPlace::class);
		}
		// END MODIFICATION
	}
