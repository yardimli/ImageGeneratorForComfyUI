<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;
	use Illuminate\Support\Facades\DB;

	return new class extends Migration
	{
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				$table->unsignedBigInteger('user_id')->after('id')->nullable();
				$table->string('image_source')->after('album_path')->default('s3');

				// Add foreign key constraint, assuming you have a 'users' table
				$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
			});

			// Update existing rows to associate them with user_id 1 and set the source
			DB::table('goodalbumcovers')->update([
				'user_id' => 1,
				'image_source' => 's3'
			]);

			// Make columns non-nullable after updating
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				$table->unsignedBigInteger('user_id')->nullable(false)->change();
				$table->string('image_source')->nullable(false)->change();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('goodalbumcovers', function (Blueprint $table) {
				$table->dropForeign(['user_id']);
				$table->dropColumn('user_id');
				$table->dropColumn('image_source');
			});
		}
	};
