<?php

	namespace App\Console\Commands;

	use Illuminate\Console\Command;
	use FPDF;
	use Throwable;

	class MakeFontDefinitions extends Command
	{
		/**
		 * The name and signature of the console command.
		 *
		 * @var string
		 */
		protected $signature = 'make:font-definitions {font_name : The name of the font family (e.g., CactusClassicalSerif)}';

		/**
		 * The console command description.
		 *
		 * @var string
		 */
		protected $description = 'Generates FPDF font definition files (.php and .z) from a .ttf file in resources/fonts';

		/**
		 * Execute the console command.
		 */
		public function handle()
		{
			$fontFamilyName = $this->argument('font_name');
			$fontTtfFile = $fontFamilyName . '-Regular.ttf';
			$fontPath = resource_path('fonts/');

			$fullTtfPath = $fontPath . $fontTtfFile;

			if (!file_exists($fullTtfPath)) {
				$this->error("TTF file not found at: " . $fullTtfPath);
				$this->line("Please ensure the file exists and is named correctly (e.g., {$fontTtfFile}).");
				return 1;
			}

			$this->info("Attempting to generate definition files for {$fontTtfFile}...");
			$this->line("Font Path: {$fontPath}");

			try {
				// Define the path for FPDF to look for and write files
				if (!defined('FPDF_FONTPATH')) {
					define('FPDF_FONTPATH', $fontPath);
				}

				// We just need to instantiate FPDF and call AddFont.
				// This triggers the definition file generation.
				// We suppress the output of the FPDF constructor which can sometimes echo things.
				ob_start();
				$pdf = new FPDF();
				$pdf->AddFont($fontFamilyName, '', $fontTtfFile);
				ob_end_clean();

				$phpFile = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fontFamilyName)) . '.php';
				$zFile = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fontFamilyName)) . '.z';

				if (file_exists($fontPath . $phpFile) && file_exists($fontPath . $zFile)) {
					$this->info("----------------------------------------");
					$this->info("✅ Success! Files generated:");
					$this->line("   - " . $phpFile);
					$this->line("   - " . $zFile);
					$this->info("----------------------------------------");
					$this->comment("You should commit these new files to your Git repository.");
					return 0;
				} else {
					$this->error("Failed to generate definition files. This is almost always a file permission issue.");
					$this->line("Try running this command as the web server user, for example:");
					$this->line("sudo -u www-data php artisan " . $this->signature);
					return 1;
				}
			} catch (Throwable $e) {
				ob_end_clean(); // Clean buffer on error too
				$this->error("An unexpected error occurred: " . $e->getMessage());
				$this->line("This might be a permission issue or a problem with the font file itself.");
				$this->line("Try running the command with 'sudo -u www-data ...'");
				return 1;
			}
		}
	}
