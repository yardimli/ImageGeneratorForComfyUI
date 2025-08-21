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
\				$table->string('level')->nullable()->after('short_description');
				$table->text('initial_prompt')->nullable()->after('level');
				$table->string('model')->nullable()->after('initial_prompt');
\			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('stories', function (Blueprint $table) {
\				$table->dropColumn(['level', 'initial_prompt', 'model']);
\			});
		}
	};
