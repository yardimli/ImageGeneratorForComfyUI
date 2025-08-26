<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				// START MODIFICATION
				// We need to allow the album_path to be null for generated child records.
				// The ->change() method modifies an existing column.
				$table->string('album_path')->nullable()->change();
				// END MODIFICATION
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				// START MODIFICATION
				// This will revert the column to NOT be nullable if you ever roll back.
				// Note: This might fail if there are already null values in the column.
				$table->string('album_path')->nullable(false)->change();
				// END MODIFICATION
			});
		}
	};
