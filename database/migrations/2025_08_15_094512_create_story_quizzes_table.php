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
			Schema::create('story_quizzes', function (Blueprint $table) {
				$table->id();
				$table->foreignId('story_id')->constrained()->onDelete('cascade');
				$table->text('question');
				$table->text('answers'); // Will store multiple choice answers, one per line.
				$table->timestamps();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('story_quizzes');
		}
	};
