<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Casts\Attribute;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class Story extends Model
	{
		use HasFactory;

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array
		 */
		protected $fillable = [
			'user_id',
			'title',
			'short_description',
			'level',
			'initial_prompt',
			'model',
		];

		/**
		 * The attributes that should be cast.
		 *
		 * @var array
		 */
		protected $casts = [
			'image_cost' => 'float',
		];

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

		/**
		 * Get the user that owns the story.
		 */
		public function user()
		{
			return $this->belongsTo(User::class);
		}

		/**
		 * Get the characters for the story.
		 */
		public function characters()
		{
			return $this->hasMany(StoryCharacter::class);
		}

		/**
		 * Get the places for the story.
		 */
		public function places()
		{
			return $this->hasMany(StoryPlace::class);
		}

		/**
		 * Get the pages for the story.
		 */
		public function pages()
		{
			return $this->hasMany(StoryPage::class)->orderBy('page_number');
		}

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

		/**
		 * Get the dictionary entries for the story.
		 */
		public function dictionary()
		{
			return $this->hasManyThrough(StoryDictionary::class, StoryPage::class);
		}


		/**
		 * Get the quiz questions for the story.
		 */
		public function quiz()
		{
			return $this->hasMany(StoryQuiz::class);
		}
	}
