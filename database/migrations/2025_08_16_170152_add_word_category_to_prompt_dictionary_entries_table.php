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
			Schema::table('prompt_dictionary_entries', function (Blueprint $table) {
				// START MODIFICATION: Add word_category column
				$table->string('word_category')->nullable()->after('description');
				// END MODIFICATION
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('prompt_dictionary_entries', function (Blueprint $table) {
				// START MODIFICATION: Drop word_category column
				$table->dropColumn('word_category');
				// END MODIFICATION
			});
		}
	};
