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
		protected $fillable = [
			'story_id',
			'word',
			'explanation',
		];

		/**
		 * Get the story that this dictionary entry belongs to.
		 */
		public function story()
		{
			return $this->belongsTo(Story::class);
		}
	}
