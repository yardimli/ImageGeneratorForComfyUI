<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoshopProject extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'width', 'height'];
    protected $casts = ['width' => 'integer', 'height' => 'integer'];

    public function user() { return $this->belongsTo(User::class); }
    public function layers() { return $this->hasMany(PhotoshopLayer::class)->where('is_committed', true)->orderBy('z_index')->orderBy('id'); }
}
