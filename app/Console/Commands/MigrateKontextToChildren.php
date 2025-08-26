<?php

	namespace App\Console\Commands;

	use App\Models\GoodAlbumCover;
	use Illuminate\Console\Command;
	use Illuminate\Support\Facades\DB;
	use Throwable;

	class MigrateKontextToChildren extends Command
	{
		/**
		 * The name and signature of the console command.
		 *
		 * @var string
		 */
		protected $signature = 'app:migrate-kontext-to-children';

		/**
		 * The console command description.
		 *
		 * @var string
		 */
		protected $description = 'Migrates existing Kontext images from parent records to new child records to match the new data structure.';

		/**
		 * Execute the console command.
		 */
		public function handle()
		{
			$this->info('Starting migration of Kontext images to child records...');

			$parentsToMigrate = GoodAlbumCover::whereNotNull('kontext_path')
				->whereNull('parent_id')
				->get();

			if ($parentsToMigrate->isEmpty()) {
				$this->info('No records found that need migration. All good!');
				return self::SUCCESS;
			}

			$count = $parentsToMigrate->count();
			$this->info("Found {$count} records to migrate.");

			$progressBar = $this->output->createProgressBar($count);
			$progressBar->start();

			foreach ($parentsToMigrate as $parent) {
				try {
					DB::transaction(function () use ($parent) {
						$child = new GoodAlbumCover();

						$child->parent_id = $parent->id;
						$child->user_id = $parent->user_id; // Keep user_id for easier queries
						$child->mix_prompt = $parent->mix_prompt;

						// START MODIFICATION: Fix the unique constraint violation
						// A child record is a generated image, so its album_path should be null.
						// Its source image is defined by its parent relationship.
						$child->album_path = null;
						$child->image_source = 'generated'; // Set a new source type for clarity
						// END MODIFICATION

						// These are the fields we are migrating from the parent to the child
						$child->kontext_path = $parent->kontext_path;
						$child->upscaled_path = $parent->upscaled_path;
						$child->upscale_status = $parent->upscale_status;
						$child->upscale_prediction_id = $parent->upscale_prediction_id;
						$child->upscale_status_url = $parent->upscale_status_url;

						$child->liked = false;
						$child->save();

						// Clear the migrated data from the parent record.
						$parent->kontext_path = null;
						$parent->upscaled_path = null;
						$parent->upscale_status = null;
						$parent->upscale_prediction_id = null;
						$parent->upscale_status_url = null;
						$parent->save();
					});

					$progressBar->advance();
				} catch (Throwable $e) {
					$this->error("\nFailed to process parent record ID {$parent->id}. Error: " . $e->getMessage());
				}
			}

			$progressBar->finish();
			$this->newLine(2);
			$this->info("Migration complete. Successfully processed {$count} records.");

			return self::SUCCESS;
		}
	}
