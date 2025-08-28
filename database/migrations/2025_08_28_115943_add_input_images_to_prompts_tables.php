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
			// Add the 'input_images' column to the 'prompts' table.
			// This column will store a JSON array of paths to images used as input for generation.
			Schema::table('prompts', function (Blueprint $table) {
				$table->json('input_images')->nullable()->after('input_image_2_strength');
			});

			// Add the 'input_images' column to the 'prompt_settings' table for consistency.
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->json('input_images')->nullable()->after('input_images_2');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			// Remove the 'input_images' column from the 'prompts' table if the migration is rolled back.
			Schema::table('prompts', function (Blueprint $table) {
				$table->dropColumn('input_images');
			});

			// Remove the 'input_images' column from the 'prompt_settings' table.
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropColumn('input_images');
			});
		}
	};
