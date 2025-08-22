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
			Schema::create('llm_prompts', function (Blueprint $table) {
				$table->id();
				$table->string('name')->unique()->comment('The programmatic key for the prompt (e.g., story.core.generate)');
				$table->string('label')->comment('A user-friendly name for the prompt UI (e.g., Story Core Generation)');
				$table->text('description')->nullable()->comment('A short explanation of what the prompt does and where it is used.');
				$table->longText('system_prompt')->nullable()->comment('Contains system-level instructions, role-playing, JSON structure, etc.');
				$table->json('placeholders')->nullable()->comment('An array of available placeholders for display in the UI.');
				$table->json('options')->nullable()->comment('A JSON object for dynamic options, like dropdowns in the UI.');
				$table->timestamps();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('llm_prompts');
		}
	};
