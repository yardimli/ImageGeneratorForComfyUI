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
			Schema::table('prompt_settings', function (Blueprint $table) {
				// Add new fields for Lora settings, nullable to not affect existing records.
				$table->integer('story_page_id')->default(0)->after('user_id');
			});

			Schema::table('prompts', function (Blueprint $table) {
				// Add corresponding fields to individual prompts for the generation worker.
				$table->integer('story_page_id')->default(0)->after('prompt_setting_id');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropColumn('story_page_id');
			});

			Schema::table('prompts', function (Blueprint $table) {
				$table->dropColumn('story_page_id');
			});
		}
	};
