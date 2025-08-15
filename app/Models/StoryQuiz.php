<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	/**
	 * Represents a single quiz entry for a story.
	 */
	class StoryQuiz extends Model
	{
		use HasFactory;

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array
		 */
		protected $fillable = [
			'story_id',
			'question',
			'answers',
		];

		/**
		 * Get the story that this quiz entry belongs to.
		 */
		public function story()
		{
			return $this->belongsTo(Story::class);
		}
	}
