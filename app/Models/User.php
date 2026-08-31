<?php

	namespace App\Models;

	// use Illuminate\Contracts\Auth\MustVerifyEmail;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Foundation\Auth\User as Authenticatable;
	use Illuminate\Notifications\Notifiable;
	use Laravel\Sanctum\HasApiTokens;

	class User extends Authenticatable
	{
		use HasApiTokens, HasFactory, Notifiable;

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array<int, string>
		 */
		protected $fillable = [
			'name',
			'email',
			'password',
			'is_admin',
		];

		/**
		 * The attributes that should be hidden for serialization.
		 *
		 * @var array<int, string>
		 */
		protected $hidden = [
			'password',
			'remember_token',
		];

		/**
		 * The attributes that should be cast.
		 *
		 * @var array<string, string>
		 */
		protected $casts = [
			'email_verified_at' => 'datetime',
			'password' => 'hashed',
			'is_admin' => 'boolean',
		];

		public function stories()
		{
			return $this->hasMany(Story::class);
		}

		public function prompts()
		{
			return $this->hasMany(Prompt::class);
		}

		public function albumCovers()
		{
			return $this->hasMany(GoodAlbumCover::class);
		}

		public function dictionaryEntries()
		{
			return $this->hasMany(PromptDictionaryEntry::class);
		}

		public function layers()
		{
			return $this->hasMany(Layer::class);
		}

		public function photoshopProjects()
		{
			return $this->hasMany(PhotoshopProject::class);
		}
	}
