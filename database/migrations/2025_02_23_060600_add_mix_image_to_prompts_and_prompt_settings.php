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
					$table->string('generation_type', 256)->default('prompt');
					$table->string('input_image_1', 256)->nullable();
					$table->integer('input_image_1_strength')->default(3);
					$table->string('input_image_2', 256)->nullable();
					$table->integer('input_image_2_strength')->default(3);
				});
			});

			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->after('upload_to_s3', function ($table) {
					$table->string('generation_type', 256)->default('prompt');
					$table->mediumText('input_images_1')->nullable();
					$table->mediumText('input_images_2')->nullable();
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
