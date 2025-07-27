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
			Schema::create('story_pages', function (Blueprint $table) {
				$table->id();
				$table->foreignId('story_id')->constrained()->onDelete('cascade');
				$table->integer('page_number');
				$table->text('story_text')->nullable();
				$table->text('image_prompt')->nullable();
				$table->string('image_path', 2048)->nullable();
				$table->timestamps();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('story_pages');
		}
	};
