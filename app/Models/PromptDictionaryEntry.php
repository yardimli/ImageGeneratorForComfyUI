<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class PromptDictionaryEntry extends Model
	{
		use HasFactory;

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array
		 */
		protected $fillable = [
			'user_id',
			'name',
			'description',
			'word_category',
			'image_prompt',
			'image_path',
		];

		/**
		 * Get the user that owns the dictionary entry.
		 */
		public function user()
		{
			return $this->belongsTo(User::class);
		}
	}
