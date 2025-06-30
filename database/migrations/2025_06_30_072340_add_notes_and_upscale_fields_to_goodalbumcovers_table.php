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
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				// Add a field for user-editable notes.
				$table->text('notes')->nullable()->after('kontext_path');

				// Add fields for the upscaling process.
				// 0: Not upscaled, 1: In progress, 2: Completed, 3: Failed
				$table->tinyInteger('upscale_status')->default(0)->after('notes');
				$table->string('upscale_prediction_id')->nullable()->after('upscale_status');
				$table->string('upscale_status_url')->nullable()->after('upscale_prediction_id');

				// The 'upscaled_path' field was already in the model's fillable array,
				// but let's ensure it exists in the table. If it already exists from a
				// previous migration, this might cause an error. If so, you can comment
				// out or remove the line below.
				if (!Schema::hasColumn('goodalbumcovers', 'upscaled_path')) {
					$table->string('upscaled_path')->nullable()->after('upscale_status_url');
				}
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				$table->dropColumn([
					'notes',
					'upscale_status',
					'upscale_prediction_id',
					'upscale_status_url'
				]);

				// Only drop this column if you are sure it was added by this migration.
				if (Schema::hasColumn('goodalbumcovers', 'upscaled_path')) {
					// $table->dropColumn('upscaled_path'); // Uncomment if you added it here.
				}
			});
		}
	};
