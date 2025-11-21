<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::create('upscale_models', function (Blueprint $table) {
				$table->id();
				$table->string('name');
				$table->string('slug')->unique();
				$table->string('replicate_version_id');
				$table->string('image_input_key')->default('image'); // 'image' or 'img'
				$table->json('input_schema'); // Defines form fields
				$table->json('default_settings'); // Default values
				$table->timestamps();
			});

			Schema::create('user_upscale_settings', function (Blueprint $table) {
				$table->id();
				$table->foreignId('user_id')->constrained()->onDelete('cascade');
				$table->foreignId('upscale_model_id')->constrained('upscale_models')->onDelete('cascade');
				$table->json('settings');
				$table->boolean('is_active')->default(false);
				$table->timestamps();
			});
		}

		public function down(): void
		{
			Schema::dropIfExists('user_upscale_settings');
			Schema::dropIfExists('upscale_models');
		}
	};
