<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class PhotoshopLayer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['photoshop_project_id', 'name', 'file_path', 'x', 'y', 'width', 'height', 'original_width', 'original_height', 'rotation', 'opacity', 'visible', 'z_index', 'is_committed'];
    protected $casts = ['x' => 'float', 'y' => 'float', 'width' => 'float', 'height' => 'float', 'original_width' => 'float', 'original_height' => 'float', 'rotation' => 'float', 'opacity' => 'integer', 'visible' => 'boolean', 'z_index' => 'integer', 'is_committed' => 'boolean'];

    public function project() { return $this->belongsTo(PhotoshopProject::class, 'photoshop_project_id'); }
}
