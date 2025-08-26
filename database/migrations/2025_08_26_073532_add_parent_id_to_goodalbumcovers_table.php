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
				// START MODIFICATION: Add parent_id for nesting generated images
				$table->unsignedBigInteger('parent_id')->nullable()->after('id');
				$table->foreign('parent_id')->references('id')->on('goodalbumcovers')->onDelete('cascade');
				// END MODIFICATION
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				// START MODIFICATION: Drop foreign key and column
				$table->dropForeign(['parent_id']);
				$table->dropColumn('parent_id');
				// END MODIFICATION
			});
		}
	};
