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
			'mixed_path',
			'upscaled_path',
			'kontext_path',
			'notes',
			'upscale_status',
			'upscale_prediction_id',
			'upscale_status_url',
		];

		protected $casts = [
			'liked' => 'boolean',
			'mixed' => 'boolean',
		];
	}
