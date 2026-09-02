<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->json('model_parameters')->nullable()->after('input_images');
        });

        Schema::table('prompt_settings', function (Blueprint $table) {
            $table->json('model_parameters')->nullable()->after('input_images');
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropColumn('model_parameters');
        });

        Schema::table('prompt_settings', function (Blueprint $table) {
            $table->dropColumn('model_parameters');
        });
    }
};
