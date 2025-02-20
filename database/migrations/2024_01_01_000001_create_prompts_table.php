<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {

		public function up()
		{
			Schema::create('prompts', function (Blueprint $table) {
				$table->id();
				$table->integer('user_id');
				$table->integer('prompt_setting_id')->nullable();
				$table->text('original_prompt')->nullable();
				$table->text('generated_prompt');
				$table->integer('width')->default(1024);
				$table->integer('height')->default(1024);
				$table->string('file_path')->nullable();
				$table->timestamps();
			});

			Schema::create('prompt_settings', function (Blueprint $table) {
				$table->id();
				$table->integer('user_id');
				$table->string('name')->nullable();
				$table->text('template_path');
				$table->text('prompt');
				$table->text('original_prompt')->nullable();
				$table->string('precision');
				$table->integer('count')->default(1);
				$table->integer('render_each_prompt_times')->default(1);
				$table->integer('width')->default(1024);
				$table->integer('height')->default(1024);
				$table->string('aspect_ratio',50)->default('1:1');
				$table->string('prepend_text')->nullable();
				$table->string('append_text')->nullable();
				$table->boolean('generate_original_prompt')->default(false);
				$table->boolean('append_to_prompt')->default(false);
				$table->timestamps();
			});
		}

		public function down()
		{
			Schema::dropIfExists('prompts');
			Schema::dropIfExists('prompt_settings');
		}

	};
