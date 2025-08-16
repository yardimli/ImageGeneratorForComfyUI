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
		public function up(): void
		{
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->boolean('create_schnell')->default(true)->after('upload_to_s3');
				$table->boolean('create_dev')->default(true)->after('create_schnell');
			});
		}

		/**
		 * Reverse the migrations.
		 *
		 * @return void
		 */
		public function down(): void
		{
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropColumn('create_schnell');
				$table->dropColumn('create_dev');
			});
		}
	};
