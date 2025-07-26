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
			Schema::table('prompt_settings', function (Blueprint $table) {
				// Add new fields for Lora settings, nullable to not affect existing records.
				$table->string('lora_name')->nullable()->after('model');
				$table->float('strength_model')->nullable()->after('lora_name');
				$table->float('guidance')->nullable()->after('strength_model');
			});

			Schema::table('prompts', function (Blueprint $table) {
				// Add corresponding fields to individual prompts for the generation worker.
				$table->string('lora_name')->nullable()->after('model');
				$table->float('strength_model')->nullable()->after('lora_name');
				$table->float('guidance')->nullable()->after('strength_model');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('prompt_settings', function (Blueprint $table) {
				$table->dropColumn(['lora_name', 'strength_model', 'guidance']);
			});

			Schema::table('prompts', function (Blueprint $table) {
				$table->dropColumn(['lora_name', 'strength_model', 'guidance']);
			});
		}
	};
