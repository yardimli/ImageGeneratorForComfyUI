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
			Schema::table('prompts', function (Blueprint $table) {
				$table->after('upload_to_s3', function ($table) {
					$table->integer('left_padding')->default(0);
					$table->integer('right_padding')->default(0);
					$table->integer('top_padding')->default(0);
					$table->integer('bottom_padding')->default(0);
					$table->integer('feathering')->default(0);
					$table->string('source_image', 256)->nullable();
				});
			});

			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->after('upload_to_s3', function ($table) {
					$table->integer('left_padding')->default(0);
					$table->integer('right_padding')->default(0);
					$table->integer('top_padding')->default(0);
					$table->integer('bottom_padding')->default(0);
					$table->integer('feathering')->default(0);
					$table->string('source_image', 256)->nullable();
				});
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('prompts', function (Blueprint $table) {
				$table->dropColumn(['left_padding', 'right_padding', 'top_padding', 'bottom_padding', 'feathering', 'source_image']);
			});

			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropColumn(['left_padding', 'right_padding', 'top_padding', 'bottom_padding', 'feathering', 'source_image']);
			});
		}
	};
