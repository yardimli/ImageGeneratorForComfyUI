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
				// START MODIFICATION: Add new columns for story level and AI generation details.
				$table->string('level')->nullable()->after('short_description');
				$table->text('initial_prompt')->nullable()->after('level');
				$table->string('model')->nullable()->after('initial_prompt');
				// END MODIFICATION
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('stories', function (Blueprint $table) {
				// START MODIFICATION: Drop the new columns on rollback.
				$table->dropColumn(['level', 'initial_prompt', 'model']);
				// END MODIFICATION
			});
		}
	};
