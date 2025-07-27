<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class Story extends Model
	{
		use HasFactory;

		protected $fillable = [
			'user_id',
			'title',
			'short_description',
		];

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
	}
