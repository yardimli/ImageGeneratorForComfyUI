<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('goodalbumcovers');
    }

    public function down(): void
    {
        Schema::create('goodalbumcovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('goodalbumcovers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('album_path')->nullable()->unique();
            $table->string('image_source')->default('s3');
            $table->boolean('liked')->default(false);
            $table->boolean('mixed')->default(false);
            $table->text('mix_prompt')->nullable();
            $table->boolean('upscaled')->default(false);
            $table->string('mixed_path')->nullable();
            $table->string('upscaled_path')->nullable();
            $table->string('kontext_path')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('upscale_status')->default(0);
            $table->string('upscale_prediction_id')->nullable();
            $table->string('upscale_status_url')->nullable();
            $table->timestamps();
        });
    }
};