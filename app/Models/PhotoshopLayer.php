<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PhotoshopLayer extends Model
{
    use HasFactory;

    protected $fillable = ['photoshop_project_id', 'name', 'file_path', 'x', 'y', 'width', 'height', 'rotation', 'opacity', 'visible', 'z_index'];
    protected $casts = ['x' => 'float', 'y' => 'float', 'width' => 'float', 'height' => 'float', 'rotation' => 'float', 'opacity' => 'integer', 'visible' => 'boolean', 'z_index' => 'integer'];
    protected $appends = ['image_url'];

    public function project() { return $this->belongsTo(PhotoshopProject::class, 'photoshop_project_id'); }
    public function getImageUrlAttribute(): string { return Storage::disk('public')->url($this->file_path); }
}
