<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class GoodAlbumCover extends Model
	{
		use HasFactory;

		protected $table = 'goodalbumcovers';

		protected $fillable = [
			'album_path',
			'liked',
			'mixed',
			'mix_prompt',
			'upscaled',
			'mixed_path',
			'upscaled_path',
		];

		protected $casts = [
			'liked' => 'boolean',
			'mixed' => 'boolean',
			'upscaled' => 'boolean',
		];
	}
