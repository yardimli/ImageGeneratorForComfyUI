<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prompt_id',
        'model',
        'input_image',
        'status',
        'images',
        'layers',
        'error',
    ];

    protected $casts = [
        'status' => 'integer',
        'images' => 'array',
        'layers' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }
}
