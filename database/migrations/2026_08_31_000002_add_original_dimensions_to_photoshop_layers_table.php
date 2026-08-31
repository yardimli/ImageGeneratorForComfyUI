<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photoshop_layers', function (Blueprint $table) {
            $table->decimal('original_width', 12, 3)->nullable()->after('height');
            $table->decimal('original_height', 12, 3)->nullable()->after('original_width');
        });

        DB::table('photoshop_layers')->update([
            'original_width' => DB::raw('width'),
            'original_height' => DB::raw('height'),
        ]);
    }

    public function down(): void
    {
        Schema::table('photoshop_layers', function (Blueprint $table) {
            $table->dropColumn(['original_width', 'original_height']);
        });
    }
};
