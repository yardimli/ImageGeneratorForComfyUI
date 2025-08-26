<?php namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodAlbumCover extends Model
{
	use HasFactory;

	protected $table = 'goodalbumcovers';

	protected $fillable = [
		'user_id',
		'album_path',
		'image_source',
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
		'parent_id', // START MODIFICATION: Add parent_id
	];

	protected $casts = [
		'liked' => 'boolean',
		'mixed' => 'boolean',
	];

	/**
	 * Get the parent cover for a generated image.
	 */
	public function parent()
	{
		return $this->belongsTo(GoodAlbumCover::class, 'parent_id');
	}

	/**
	 * Get the generated child images for a cover.
	 */
	public function children()
	{
		return $this->hasMany(GoodAlbumCover::class, 'parent_id')->orderBy('created_at', 'asc');
	}
	// END MODIFICATION
}
