<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photoshop_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
        });

        Schema::create('photoshop_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photoshop_project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->decimal('x', 12, 3)->default(0);
            $table->decimal('y', 12, 3)->default(0);
            $table->decimal('width', 12, 3);
            $table->decimal('height', 12, 3);
            $table->decimal('rotation', 8, 3)->default(0);
            $table->unsignedTinyInteger('opacity')->default(100);
            $table->boolean('visible')->default(true);
            $table->integer('z_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photoshop_layers');
        Schema::dropIfExists('photoshop_projects');
    }
};
