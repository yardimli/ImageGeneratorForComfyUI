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
			// Add image_prompt column to characters and places
			Schema::table('story_characters', function (Blueprint $table) {
				$table->text('image_prompt')->nullable()->after('description');
			});

			Schema::table('story_places', function (Blueprint $table) {
				$table->text('image_prompt')->nullable()->after('description');
			});

			// Add foreign keys to prompts table for characters and places
			Schema::table('prompts', function (Blueprint $table) {
				$table->foreignId('story_character_id')->nullable()->constrained('story_characters')->onDelete('cascade')->after('story_page_id');
				$table->foreignId('story_place_id')->nullable()->constrained('story_places')->onDelete('cascade')->after('story_character_id');
				$table->index(['story_character_id']);
				$table->index(['story_place_id']);
			});

			// Add foreign keys to prompt_settings table for characters and places
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->foreignId('story_character_id')->nullable()->constrained('story_characters')->onDelete('cascade')->after('story_page_id');
				$table->foreignId('story_place_id')->nullable()->constrained('story_places')->onDelete('cascade')->after('story_character_id');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('story_characters', function (Blueprint $table) {
				$table->dropColumn('image_prompt');
			});

			Schema::table('story_places', function (Blueprint $table) {
				$table->dropColumn('image_prompt');
			});

			Schema::table('prompts', function (Blueprint $table) {
				$table->dropForeign(['story_character_id']);
				$table->dropForeign(['story_place_id']);
				$table->dropColumn(['story_character_id', 'story_place_id']);
			});

			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropForeign(['story_character_id']);
				$table->dropForeign(['story_place_id']);
				$table->dropColumn(['story_character_id', 'story_place_id']);
			});
		}
	};
