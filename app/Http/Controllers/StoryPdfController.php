<?php

	namespace App\Http\Controllers;

	use App\Models\Story;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;
	use Symfony\Component\Process\Exception\ProcessFailedException;
	use Symfony\Component\Process\Process;
	use Throwable;

	/**
	 * Handles PDF generation for stories.
	 *
	 * Note: This controller requires the 'tecnickcom/tc-lib-pdf' package.
	 * Please run `composer require tecnickcom/tc-lib-pdf tecnickcom/tc-lib-pdf-font tecnickcom/tc-lib-pdf-image`.
	 *
	 * IMPORTANT: This controller executes a Python script. Ensure Python 3 is installed.
	 * On some systems (like Windows), the Python executable might be 'python' instead of 'python3'.
	 * You can configure this in your .env file:
	 * PYTHON_EXECUTABLE_PATH=python
	 *
	 * And reference it in config/services.php:
	 * 'python' => [
	 *     'executable' => env('PYTHON_EXECUTABLE_PATH', 'python3'),
	 * ],
	 */
	class StoryPdfController extends Controller
	{
		/**
		 * Show the setup form for PDF generation.
		 *
		 * @param \App\Models\Story $story
		 * @return \Illuminate\Contracts\View\View
		 */
		public function setup(Story $story)
		{
			$wallpaperPath = resource_path('wallpapers');
			$wallpapers = [];

			if (File::isDirectory($wallpaperPath)) {
				$files = File::files($wallpaperPath);
				foreach ($files as $file) {
					if (in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png'])) {
						$wallpapers[] = $file->getFilename();
					}
				}
			}

			// START MODIFICATION: Prepare default text for new content pages.
			$story->load('user');
			$defaultCopyright = '© ' . date('Y') . ' ' . $story->user->name . ". All rights reserved.\nNo part of this publication may be reproduced, distributed, or transmitted in any form or by any means, including photocopying, recording, or other electronic or mechanical methods, without the prior written permission of the publisher, except in the case of brief quotations embodied in critical reviews and certain other noncommercial uses permitted by copyright law.";
			$defaultTitlePage = $story->title . "\n\nBy\n" . $story->user->name;
			// END MODIFICATION

			return view('story.pdf.setup', compact('story', 'wallpapers', 'defaultCopyright', 'defaultTitlePage'));
		}

		/**
		 * Generate and stream the storybook PDF.
		 * The underlying service terminates the script upon successful generation.
		 *
		 * @param \Illuminate\Http\Request $request
		 * @param \App\Models\Story $story
		 * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
		 */
		public function generate(Request $request, Story $story)
		{
			// START MODIFICATION: Add validation for all new PDF settings.
			$validator = Validator::make($request->all(), [
				// Page Layout
				'width' => 'required|numeric|min:1|max:50',
				'height' => 'required|numeric|min:1|max:50',
				'bleed' => 'required|numeric|min:0|max:5',
				'dpi' => 'required|integer|min:72|max:1200',
				'show_bleed_marks' => 'nullable|boolean',
				// Content
				'title_page_text' => 'nullable|string|max:5000',
				'copyright_text' => 'nullable|string|max:5000',
				'introduction_text' => 'nullable|string|max:10000',
				'wallpaper' => 'nullable|string',
				// Styling
				'font_name' => 'required|string|max:100',
				'font_size_main' => 'required|numeric|min:6|max:72',
				'font_size_footer' => 'required|numeric|min:6|max:72',
				'font_size_title' => 'required|numeric|min:6|max:72',
				'font_size_copyright' => 'required|numeric|min:6|max:72',
				'font_size_introduction' => 'required|numeric|min:6|max:72',
				'color_main' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_footer' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_title' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_copyright' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_introduction' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
			]);
			// END MODIFICATION

			if ($validator->fails()) {
				return back()->withErrors($validator)->withInput();
			}

			$validated = $validator->validated();

			$tempDir = storage_path('app/temp/pdfgen_' . uniqid());
			File::makeDirectory($tempDir, 0755, true, true);

			try {
				// Load story with relations
				$story->load('pages', 'user');

				// 1. Prepare data JSON file
				$storyData = [
					'title' => $story->title,
					'author' => $story->user->name,
					'pages' => $story->pages->map(function ($page) {
						return [
							'text' => $page->story_text ?? '',
							'image_url' => $page->image_path,
						];
					})->toArray(),
				];
				$dataFile = $tempDir . '/data.json';
				File::put($dataFile, json_encode($storyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

				// 2. Prepare paths and arguments for the Python script
				$outputFile = $tempDir . '/' . Str::slug($story->title) . '.pdf';
				$pythonScriptPath = base_path('python/storybook.py');

				// Font path validation
				$fontFile = resource_path('fonts/' . $validated['font_name'] . '-Regular.ttf');
				if (!File::exists($fontFile)) {
					throw new \Exception("Font file not found: " . basename($fontFile));
				}

				// Wallpaper path validation
				$wallpaperFile = null;
				if (!empty($validated['wallpaper'])) {
					$wallpaperPath = resource_path('wallpapers/' . $validated['wallpaper']);
					if (File::exists($wallpaperPath)) {
						$wallpaperFile = $wallpaperPath;
					}
				}

				// START MODIFICATION: Build the full command with all new arguments.
				// Convert inches to mm for the script
				$width_mm = $validated['width'] * 25.4;
				$height_mm = $validated['height'] * 25.4;
				$bleed_mm = $validated['bleed'] * 25.4;

				$pythonExecutable = config('services.python.executable', 'python3');
				$command = [
					$pythonExecutable,
					$pythonScriptPath,
					'--data-file', $dataFile,
					'--output-file', $outputFile,
					// Page Layout
					'--width-mm', $width_mm,
					'--height-mm', $height_mm,
					'--bleed-mm', $bleed_mm,
					'--dpi', $validated['dpi'],
					// Content
					'--title-page-text', $validated['title_page_text'] ?? '',
					'--copyright-text', $validated['copyright_text'] ?? '',
					'--introduction-text', $validated['introduction_text'] ?? '',
					// Styling
					'--font-name', $validated['font_name'],
					'--font-file', $fontFile,
					'--font-size-main', $validated['font_size_main'],
					'--font-size-footer', $validated['font_size_footer'],
					'--font-size-title', $validated['font_size_title'],
					'--font-size-copyright', $validated['font_size_copyright'],
					'--font-size-introduction', $validated['font_size_introduction'],
					'--color-main', $validated['color_main'],
					'--color-footer', $validated['color_footer'],
					'--color-title', $validated['color_title'],
					'--color-copyright', $validated['color_copyright'],
					'--color-introduction', $validated['color_introduction'],
				];

				if ($validated['show_bleed_marks'] ?? false) {
					$command[] = '--show-bleed-marks';
				}

				if ($wallpaperFile) {
					$command[] = '--wallpaper-file';
					$command[] = $wallpaperFile;
				}
				// END MODIFICATION

				// 4. Execute the process
				$process = new Process($command);
				$process->setTimeout(300); // 5-minute timeout
				$process->run();

				// 5. Check for errors
				if (!$process->isSuccessful()) {
					throw new ProcessFailedException($process);
				}

				// 6. Read file content, then prepare response. Cleanup will happen in `finally`.
				$pdfContent = File::get($outputFile);
				$pdfFileName = basename($outputFile);

				return response($pdfContent, 200, [
					'Content-Type' => 'application/pdf',
					'Content-Disposition' => 'attachment; filename="' . $pdfFileName . '"',
				]);

			} catch (ProcessFailedException $e) {
				Log::error("PDF Generation Failed: " . $e->getMessage());
				Log::error("Python stderr: " . $e->getProcess()->getErrorOutput());
				Log::error("Python stdout: " . $e->getProcess()->getOutput());
				return back()->with('error', 'There was an error generating the PDF. Please check the logs for more details.');
			} catch (Throwable $e) {
				Log::error('PDF Generation Failed: ' . $e->getMessage());
				return back()->with('error', 'An unexpected error occurred: ' . $e->getMessage());
			} finally {
				// 7. Clean up the temporary directory and all its contents
				if (File::isDirectory($tempDir)) {
					// Commenting out for debugging purposes if needed
					// File::deleteDirectory($tempDir);
				}
			}
		}

	}
