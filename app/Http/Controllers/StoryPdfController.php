<?php

	namespace App\Http\Controllers;

	use App\Models\Prompt;
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

			// START MODIFICATION: Add logo loading
			$logoPath = resource_path('logos');
			$logos = [];

			if (File::isDirectory($logoPath)) {
				$files = File::files($logoPath);
				foreach ($files as $file) {
					if (in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'svg'])) {
						$logos[] = $file->getFilename();
					}
				}
			}
			// END MODIFICATION

			$fontPath = resource_path('fonts');
			$fonts = [];
			if (File::isDirectory($fontPath)) {
				$fontFiles = File::files($fontPath);
				foreach ($fontFiles as $fontFile) {
					if (strtolower($fontFile->getExtension()) === 'ttf') {
						$fontName = preg_replace('/(-Regular)?\.ttf$/i', '', $fontFile->getFilename());
						$fonts[] = [
							'name' => $fontName,
							'filename' => $fontFile->getFilename(),
						];
					}
				}
			}

			$story->load('user');
			$defaultCopyright = '© ' . date('Y') . ' ' . $story->user->name . ". All rights reserved.\nNo part of this publication may be reproduced, distributed, or transmitted in any form or by any means, including photocopying, recording, or other electronic or mechanical methods, without the prior written permission of the publisher, except in the case of brief quotations embodied in critical reviews and certain other noncommercial uses permitted by copyright law.";
			// START MODIFICATION: Replace single title page text with structured defaults
			$defaultTitleTopText = 'An EQ Original';
			$defaultTitleMainText = $story->title;
			$defaultTitleAuthorText = 'by ' . $story->user->name;
			$defaultTitleBottomText = 'EQ Books';
			// END MODIFICATION
			$defaultIntroduction = "This is the introduction to the story. It can contain a brief overview, background information, or any other relevant details that set the stage for the narrative.\n\nFeel free to customize this text as needed.";

			// START MODIFICATION: Pass new variables to the view
			return view('story.pdf.setup', compact('story', 'wallpapers', 'logos', 'fonts', 'defaultCopyright', 'defaultTitleTopText', 'defaultTitleMainText', 'defaultTitleAuthorText', 'defaultTitleBottomText', 'defaultIntroduction'));
			// END MODIFICATION
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
			// START MODIFICATION: Added validation for new title page fields.
			$validator = Validator::make($request->all(), [
				// Page Layout
				'width' => 'required|numeric|min:1|max:50',
				'height' => 'required|numeric|min:1|max:50',
				'bleed' => 'required|numeric|min:0|max:5',
				'dpi' => 'required|integer|min:72|max:1200',
				'show_bleed_marks' => 'nullable|boolean',
				// Content
				'title_wallpaper' => 'nullable|string|max:255',
				'title_logo' => 'nullable|string|max:255',
				'title_top_text' => 'nullable|string|max:255',
				'title_main_text' => 'nullable|string|max:255',
				'title_author_text' => 'nullable|string|max:255',
				'title_bottom_text' => 'nullable|string|max:255',
				'copyright_text' => 'nullable|string|max:5000',
				'introduction_text' => 'nullable|string|max:10000',
				'wallpaper' => 'nullable|string',
				// Styling - Fonts
				'font_name_main' => 'required|string|max:100',
				'font_name_title' => 'required|string|max:100',
				'font_name_copyright' => 'required|string|max:100',
				'font_name_introduction' => 'required|string|max:100',
				// Styling - Font Sizes
				'font_size_main' => 'required|numeric|min:6|max:72',
				'font_size_footer' => 'required|numeric|min:6|max:72',
				'font_size_title' => 'required|numeric|min:6|max:72',
				'font_size_copyright' => 'required|numeric|min:6|max:72',
				'font_size_introduction' => 'required|numeric|min:6|max:72',
				// Styling - Line Heights
				'line_height_main' => 'required|numeric|min:0.5|max:5',
				'line_height_title' => 'required|numeric|min:0.5|max:5',
				'line_height_copyright' => 'required|numeric|min:0.5|max:5',
				'line_height_introduction' => 'required|numeric|min:0.5|max:5',
				// Styling - Colors
				'color_main' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_footer' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_title' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_copyright' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				'color_introduction' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
				// Margin and Alignment fields (Copyright/Intro only now)
				'valign_copyright' => 'required|string|in:top,middle,bottom',
				'margin_horizontal_copyright' => 'required|numeric|min:0',
				'valign_introduction' => 'required|string|in:top,middle,bottom',
				'margin_horizontal_introduction' => 'required|numeric|min:0',
				'page_number_margin_bottom' => 'required|numeric|min:0',
				// Text Page Styling fields
				'text_box_width' => 'required|numeric|min:10|max:100',
				'use_text_background' => 'nullable|boolean',
				'text_background_color' => 'required_if:use_text_background,1|string|regex:/^#[a-fA-F0-9]{6}$/',
				// Dashed Border fields
				'enable_dashed_border' => 'nullable|boolean',
				'dashed_border_width' => 'required_if:enable_dashed_border,1|numeric|min:0',
				'dashed_border_color' => 'required_if:enable_dashed_border,1|string|regex:/^#[a-fA-F0-9]{6}$/',
			]);
			// END MODIFICATION

			if ($validator->fails()) {
				return back()->withErrors($validator)->withInput();
			}

			$validated = $validator->validated();

			$tempDir = storage_path('app/temp/pdfgen_' . uniqid());
			File::makeDirectory($tempDir, 0755, true, true);

			try {
				$story->load('pages', 'user');

				$storyData = [
					'title' => $story->title,
					'author' => $story->user->name,
					'pages' => $story->pages->map(function ($page) {
						$imageUrl = $page->image_path;

						if (!empty($page->image_path)) {
							$prompt = Prompt::where('filename', $page->image_path)
								->where('upscale_status', '2')
								->whereNotNull('upscale_url')
								->first();

							if ($prompt) {
								$imageUrl = asset('storage/upscaled/' . $prompt->upscale_url);
							}
						}

						return [
							'text' => $page->story_text ?? '',
							'image_url' => $imageUrl,
						];
					})->toArray(),
				];
				$dataFile = $tempDir . '/data.json';
				File::put($dataFile, json_encode($storyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

				$outputFile = $tempDir . '/' . Str::slug($story->title) . '.pdf';
				$pythonScriptPath = base_path('python/storybook-html2pdf.py');

				$fontTypes = ['main', 'title', 'copyright', 'introduction'];
				$fontPaths = [];
				foreach ($fontTypes as $type) {
					$fontNameKey = 'font_name_' . $type;
					$fontName = $validated[$fontNameKey];
					$fontFile = resource_path('fonts/' . $fontName . '-Regular.ttf');
					if (!File::exists($fontFile)) {
						$fontFile = resource_path('fonts/' . $fontName . '.ttf');
						if (!File::exists($fontFile)) {
							throw new \Exception("Font file not found for type '{$type}': " . basename($validated[$fontNameKey]));
						}
					}
					$fontPaths[$type] = $fontFile;
				}

				$wallpaperFile = null;
				if (!empty($validated['wallpaper'])) {
					$wallpaperPath = resource_path('wallpapers/' . $validated['wallpaper']);
					if (File::exists($wallpaperPath)) {
						$wallpaperFile = $wallpaperPath;
					}
				}

				// START MODIFICATION: Get paths for title page assets
				$titleWallpaperFile = null;
				if (!empty($validated['title_wallpaper'])) {
					$path = resource_path('wallpapers/' . $validated['title_wallpaper']);
					if (File::exists($path)) {
						$titleWallpaperFile = $path;
					}
				}

				$titleLogoFile = null;
				if (!empty($validated['title_logo'])) {
					$path = resource_path('logos/' . $validated['title_logo']);
					if (File::exists($path)) {
						$titleLogoFile = $path;
					}
				}
				// END MODIFICATION

				$inch_to_mm = 25.4;
				$width_mm = $validated['width'] * $inch_to_mm;
				$height_mm = $validated['height'] * $inch_to_mm;
				$bleed_mm = $validated['bleed'] * $inch_to_mm;
				$margin_h_copyright_mm = $validated['margin_horizontal_copyright'] * $inch_to_mm;
				$margin_h_introduction_mm = $validated['margin_horizontal_introduction'] * $inch_to_mm;
				$page_number_margin_bottom_mm = $validated['page_number_margin_bottom'] * $inch_to_mm;

				$pythonExecutable = config('services.python.executable', 'python3');
				$command = [
					$pythonExecutable,
					$pythonScriptPath,
					'--data-file', $dataFile,
					'--output-file', $outputFile,
					'--width-mm', $width_mm,
					'--height-mm', $height_mm,
					'--bleed-mm', $bleed_mm,
					'--dpi', $validated['dpi'],
					// START MODIFICATION: Pass new title page text fields
					'--title-top-text', $validated['title_top_text'] ?? '',
					'--title-main-text', $validated['title_main_text'] ?? '',
					'--title-author-text', $validated['title_author_text'] ?? '',
					'--title-bottom-text', $validated['title_bottom_text'] ?? '',
					// END MODIFICATION
					'--copyright-text', $validated['copyright_text'] ?? '',
					'--introduction-text', $validated['introduction_text'] ?? '',
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
					'--valign-copyright', $validated['valign_copyright'],
					'--margin-horizontal-copyright-mm', $margin_h_copyright_mm,
					'--valign-introduction', $validated['valign_introduction'],
					'--margin-horizontal-introduction-mm', $margin_h_introduction_mm,
					'--page-number-margin-bottom-mm', $page_number_margin_bottom_mm,
					'--text-box-width', $validated['text_box_width'],
				];

				foreach ($fontTypes as $type) {
					$command[] = '--font-name-' . $type;
					$command[] = $validated['font_name_' . $type];
					$command[] = '--font-file-' . $type;
					$command[] = $fontPaths[$type];
					$command[] = '--line-height-' . $type;
					$command[] = $validated['line_height_' . $type];
				}

				if ($validated['enable_dashed_border'] ?? false) {
					$command[] = '--enable-dashed-border';
					$command[] = '--dashed-border-width';
					$command[] = $validated['dashed_border_width'];
					$command[] = '--dashed-border-color';
					$command[] = $validated['dashed_border_color'];
				}

				$textBackgroundColor = 'transparent';
				if ($validated['use_text_background'] ?? false) {
					$textBackgroundColor = $validated['text_background_color'];
				}
				$command[] = '--text-background-color';
				$command[] = $textBackgroundColor;


				if ($validated['show_bleed_marks'] ?? false) {
					$command[] = '--show-bleed-marks';
				}

				if ($wallpaperFile) {
					$command[] = '--wallpaper-file';
					$command[] = $wallpaperFile;
				}

				// START MODIFICATION: Add new title page asset arguments to the command.
				if ($titleWallpaperFile) {
					$command[] = '--title-wallpaper-file';
					$command[] = $titleWallpaperFile;
				}
				if ($titleLogoFile) {
					$command[] = '--title-logo-file';
					$command[] = $titleLogoFile;
				}
				// END MODIFICATION

				$process = new Process($command);
				$process->setTimeout(300);
				$process->run();

				if (!$process->isSuccessful()) {
					throw new ProcessFailedException($process);
				}

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
				if (File::isDirectory($tempDir)) {
					// File::deleteDirectory($tempDir);
				}
			}
		}

		// START MODIFICATION: Add methods to serve assets from the non-public resources directory.

		/**
		 * Serves a font file from the resources/fonts directory.
		 *
		 * @param string $filename
		 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
		 */
		public function serveFont(string $filename)
		{
			// Sanitize filename to prevent directory traversal attacks
			$filename = basename($filename);
			$path = resource_path('fonts/' . $filename);

			if (!File::exists($path)) {
				abort(404, 'Font not found.');
			}

			// response()->file() handles the Content-Type and other headers automatically.
			return response()->file($path);
		}

		/**
		 * Serves a wallpaper image from the resources/wallpapers directory.
		 *
		 * @param string $filename
		 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
		 */
		public function serveWallpaper(string $filename)
		{
			// Sanitize filename to prevent directory traversal attacks
			$filename = basename($filename);
			$path = resource_path('wallpapers/' . $filename);

			if (!File::exists($path)) {
				abort(404, 'Wallpaper not found.');
			}

			return response()->file($path);
		}

		/**
		 * Serves a logo image from the resources/logos directory.
		 *
		 * @param string $filename
		 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
		 */
		public function serveLogo(string $filename)
		{
			// Sanitize filename to prevent directory traversal attacks
			$filename = basename($filename);
			$path = resource_path('logos/' . $filename);

			if (!File::exists($path)) {
				abort(404, 'Logo not found.');
			}

			return response()->file($path);
		}
		// END MODIFICATION
	}
