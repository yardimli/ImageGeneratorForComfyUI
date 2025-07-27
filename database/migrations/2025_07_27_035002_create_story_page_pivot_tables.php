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
			Schema::create('story_page_character', function (Blueprint $table) {
				$table->foreignId('story_page_id')->constrained('story_pages')->onDelete('cascade');
				$table->foreignId('story_character_id')->constrained('story_characters')->onDelete('cascade');
				$table->primary(['story_page_id', 'story_character_id']);
			});

			Schema::create('story_page_place', function (Blueprint $table) {
				$table->foreignId('story_page_id')->constrained('story_pages')->onDelete('cascade');
				$table->foreignId('story_place_id')->constrained('story_places')->onDelete('cascade');
				$table->primary(['story_page_id', 'story_place_id']);
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('story_page_place');
			Schema::dropIfExists('story_page_character');
		}
	};
