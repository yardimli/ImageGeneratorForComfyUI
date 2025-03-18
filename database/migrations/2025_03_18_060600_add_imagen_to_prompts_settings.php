<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->after('upload_to_s3', function ($table) {
					$table->integer('create_imagen')->default(1);
				});
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropColumn(['create_imagen']);
			});
		}
	};
