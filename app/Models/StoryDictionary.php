<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	/**
	 * Represents a single dictionary entry for a story.
	 */
	class StoryDictionary extends Model
	{
		use HasFactory;

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array
		 */
		// START MODIFICATION: Link to story_page_id instead of story_id.
		protected $fillable = [
			'story_page_id',
			'word',
			'explanation',
		];
		// END MODIFICATION

		/**
		 * Get the story that this dictionary entry belongs to.
		 */
		// START MODIFICATION: Change relationship to belong to a StoryPage.
		public function page()
		{
			return $this->belongsTo(StoryPage::class, 'story_page_id');
		}

		/**
		 * Get the story through the page.
		 */
		public function story()
		{
			return $this->hasOneThrough(Story::class, StoryPage::class, 'id', 'id', 'story_page_id', 'story_id');
		}
		// END MODIFICATION
	}
