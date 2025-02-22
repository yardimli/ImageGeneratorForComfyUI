<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class UserTemplate extends Model
	{
		protected $fillable = ['user_id', 'name', 'content'];

		public function user()
		{
			return $this->belongsTo(User::class);
		}
	}
