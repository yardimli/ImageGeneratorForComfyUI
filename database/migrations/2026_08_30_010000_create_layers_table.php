<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->string('model')->default('bytedance/seedream/v5/pro/layerize');
            $table->text('input_image');
            $table->unsignedTinyInteger('status')->default(0);
            $table->json('images')->nullable();
            $table->json('layers')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layers');
    }
};
