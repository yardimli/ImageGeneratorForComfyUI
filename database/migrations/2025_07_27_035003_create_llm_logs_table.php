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
			Schema::create('llm_logs', function (Blueprint $table) {
				$table->id();
				$table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
				$table->timestamp('timestamp')->useCurrent();
				$table->string('reason');
				$table->string('model_id');
				$table->unsignedInteger('prompt_tokens')->default(0);
				$table->unsignedInteger('completion_tokens')->default(0);
				$table->timestamps();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('llm_logs');
		}
	};
