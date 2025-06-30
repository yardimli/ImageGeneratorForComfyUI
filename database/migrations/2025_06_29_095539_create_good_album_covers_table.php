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
			Schema::create('goodalbumcovers', function (Blueprint $table) {
				$table->id();
				$table->string('album_path')->unique();
				$table->boolean('liked')->default(false);
				$table->boolean('mixed')->default(false);
				$table->text('mix_prompt')->nullable();
				$table->boolean('upscaled')->default(false);
				$table->string('mixed_path')->nullable();
				$table->string('upscaled_path')->nullable();
				$table->timestamps();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('goodalbumcovers');
		}
	};
