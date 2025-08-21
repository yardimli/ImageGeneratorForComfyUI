<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		/**
		 * Run the migrations.
		 *
		 * @return void
		 */
		public function up()
		{
			// IMPORTANT: This migration will remove all existing dictionary entries
			// because the relationship is changing from Story to StoryPage.
			// If you need to preserve data, you must handle it manually before running this.
			DB::table('story_dictionaries')->delete();

			Schema::table('story_dictionaries', function (Blueprint $table) {
				// 1. Drop the old foreign key and column for story_id
				$table->dropForeign(['story_id']);
				$table->dropColumn('story_id');

				// 2. Add the new story_page_id column and its foreign key constraint
				$table->unsignedBigInteger('story_page_id')->after('id');
				$table->foreign('story_page_id')->references('id')->on('story_pages')->onDelete('cascade');
			});
		}

		/**
		 * Reverse the migrations.
		 *
		 * @return void
		 */
		public function down()
		{
			Schema::table('story_dictionaries', function (Blueprint $table) {
				// 1. Drop the new foreign key and column for story_page_id
				$table->dropForeign(['story_page_id']);
				$table->dropColumn('story_page_id');

				// 2. Add back the old story_id column and its foreign key constraint
				$table->unsignedBigInteger('story_id')->after('id');
				$table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
			});
		}
	};
