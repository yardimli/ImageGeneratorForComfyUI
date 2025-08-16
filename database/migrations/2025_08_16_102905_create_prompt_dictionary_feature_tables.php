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
			Schema::create('prompt_dictionary_entries', function (Blueprint $table) {
				$table->id();
				$table->foreignId('user_id')->constrained()->onDelete('cascade');
				$table->string('name');
				$table->text('description')->nullable();
				$table->text('image_prompt')->nullable();
				$table->string('image_path', 2048)->nullable();
				$table->timestamps();
			});

			Schema::table('prompts', function (Blueprint $table) {
				$table->foreignId('prompt_dictionary_entry_id')->nullable()->after('story_place_id')->constrained('prompt_dictionary_entries')->onDelete('set null');
			});

			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->foreignId('prompt_dictionary_entry_id')->nullable()->after('story_place_id')->constrained('prompt_dictionary_entries')->onDelete('set null');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('prompts', function (Blueprint $table) {
				$table->dropForeign(['prompt_dictionary_entry_id']);
				$table->dropColumn('prompt_dictionary_entry_id');
			});

			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropForeign(['prompt_dictionary_entry_id']);
				$table->dropColumn('prompt_dictionary_entry_id');
			});

			Schema::dropIfExists('prompt_dictionary_entries');
		}
	};
