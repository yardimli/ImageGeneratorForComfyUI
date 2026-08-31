<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photoshop_layers', function (Blueprint $table) {
            $table->boolean('is_committed')->default(true)->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('photoshop_layers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_committed');
        });
    }
};
