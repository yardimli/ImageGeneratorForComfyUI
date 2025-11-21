<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class UpscaleModel extends Model
	{
		protected $fillable = ['name', 'slug', 'replicate_version_id', 'image_input_key', 'input_schema', 'default_settings'];

		protected $casts = [
			'input_schema' => 'array',
			'default_settings' => 'array',
		];
	}
