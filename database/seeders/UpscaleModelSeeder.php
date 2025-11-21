<?php

	namespace Database\Seeders;

	use Illuminate\Database\Seeder;
	use Illuminate\Support\Facades\DB;

	class UpscaleModelSeeder extends Seeder
	{
		public function run(): void
		{
			// 1. High Resolution ControlNet Tile
			DB::table('upscale_models')->updateOrInsert(
				['slug' => 'high-resolution-controlnet-tile'],
				[
					'name' => 'High Resolution ControlNet Tile',
					'replicate_version_id' => '4af11083a13ebb9bf97a88d7906ef21cf79d1f2e5fa9d87b70739ce6b8113d29', // Note: Prompt had specific hash in curl, using that or generic
					// The prompt used: 8e6a54d7b2848c48dc741a109d3fb0ea2a7f554eb4becd39a25cc532536ea975 in curl example
					// The controller used: 4af11083a13ebb9bf97a88d7906ef21cf79d1f2e5fa9d87b70739ce6b8113d29 in original code
					// We will use the one from the curl example provided in instructions for the DB,
					// but fallback to the one in code if needed. Let's use the one from the curl request.
					'replicate_version_id' => '8e6a54d7b2848c48dc741a109d3fb0ea2a7f554eb4becd39a25cc532536ea975',
					'image_input_key' => 'image',
					'input_schema' => json_encode([
						[
							'name' => 'scheduler',
							'label' => 'Scheduler',
							'type' => 'select',
							'options' => ['DDIM', 'DPMSolverMultistep', 'K_EULER_ANCESTRAL', 'K_EULER'],
						],
						['name' => 'hdr', 'label' => 'HDR', 'type' => 'number', 'step' => 0.1],
						['name' => 'steps', 'label' => 'Steps', 'type' => 'number'],
						['name' => 'prompt', 'label' => 'Prompt', 'type' => 'textarea'],
						['name' => 'negative_prompt', 'label' => 'Negative Prompt', 'type' => 'textarea'],
						['name' => 'creativity', 'label' => 'Creativity', 'type' => 'number', 'step' => 0.05],
						['name' => 'resemblance', 'label' => 'Resemblance', 'type' => 'number', 'step' => 0.05],
						['name' => 'guidance_scale', 'label' => 'Guidance Scale', 'type' => 'number', 'step' => 0.1],
						['name' => 'lora_details_strength', 'label' => 'LoRA Details Strength', 'type' => 'number', 'step' => 0.05],
						['name' => 'lora_sharpness_strength', 'label' => 'LoRA Sharpness Strength', 'type' => 'number', 'step' => 0.05],
						['name' => 'resolution', 'label' => 'Resolution', 'type' => 'number'],
					]),
					'default_settings' => json_encode([
						'scheduler' => 'DDIM',
						'hdr' => 0,
						'steps' => 8,
						'prompt' => '4k, enhance, high detail',
						'negative_prompt' => 'Teeth, tooth, open mouth, longbody, lowres, bad anatomy, bad hands, missing fingers, extra digit, fewer digits, cropped, worst quality, low quality, mutant',
						'creativity' => 0.4,
						'resemblance' => 0.85,
						'guidance_scale' => 0,
						'lora_details_strength' => -0.25,
						'lora_sharpness_strength' => 0.75,
						'resolution' => 2560,
						'guess_mode' => false,
						'format' => 'jpg'
					]),
				]
			);

			// 2. GFPGAN
			DB::table('upscale_models')->updateOrInsert(
				['slug' => 'gfpgan'],
				[
					'name' => 'GFPGAN (Face Restoration)',
					'replicate_version_id' => '0fbacf7afc6c144e5be9767cff80f25aff23e52b0708f17e20f9879b2f21516c',
					'image_input_key' => 'img',
					'input_schema' => json_encode([
						[
							'name' => 'version',
							'label' => 'Version',
							'type' => 'select',
							'options' => ['v1.3', 'v1.4'],
							'description' => 'v1.3: better quality. v1.4: more details and better identity.'
						],
						['name' => 'scale', 'label' => 'Scale', 'type' => 'number', 'step' => 0.1],
					]),
					'default_settings' => json_encode([
						'version' => 'v1.4',
						'scale' => 2,
					]),
				]
			);
		}
	}
