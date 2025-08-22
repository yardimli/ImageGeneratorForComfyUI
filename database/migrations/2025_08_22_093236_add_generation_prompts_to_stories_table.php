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
			Schema::table('stories', function (Blueprint $table) {
				// Add columns to store the exact prompts used for AI story generation.
				// These are TEXT type to accommodate potentially long prompt templates.
				// They are nullable because stories can also be created manually.
				$table->text('prompt_content_generation')->nullable()->after('model');
				$table->text('prompt_entity_generation')->nullable()->after('prompt_content_generation');
				$table->text('prompt_character_description')->nullable()->after('prompt_entity_generation');
				$table->text('prompt_place_description')->nullable()->after('prompt_character_description');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('stories', function (Blueprint $table) {
				// Drop the columns if the migration is rolled back.
				$table->dropColumn([
					'prompt_content_generation',
					'prompt_entity_generation',
					'prompt_character_description',
					'prompt_place_description',
				]);
			});
		}
	};
