<?php

	namespace App\Http\Controllers;

	use App\Models\Story;
	use App\Services\StorybookPdf;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Throwable;

	/**
	 * Handles PDF generation for stories.
	 *
	 * Note: This controller requires the 'setasign/fpdf' package.
	 * Please run `composer require setasign/fpdf`.
	 */
	class StoryPdfController extends Controller
	{
		/**
		 * Show the setup form for PDF generation.
		 *
		 * @param  \App\Models\Story  $story
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

			return view('story.pdf.setup', compact('story', 'wallpapers'));
		}

		/**
		 * Generate and stream the storybook PDF.
		 * The underlying service terminates the script upon successful generation.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @param  \App\Models\Story  $story
		 * @return \Illuminate\Http\RedirectResponse|void
		 */
		public function generate(Request $request, Story $story)
		{
			$validator = Validator::make($request->all(), [
				'width' => 'required|numeric|min:1|max:50',
				'height' => 'required|numeric|min:1|max:50',
				'dpi' => 'required|integer|min:72|max:1200',
				'font_name' => 'required|string|max:100|regex:/^[a-zA-Z0-9\s-_]+$/',
				'wallpaper' => 'nullable|string',
			]);

			if ($validator->fails()) {
				return back()->withErrors($validator)->withInput();
			}

			$validated = $validator->validated();

			// Convert inches to mm for FPDF
			$width_mm = $validated['width'] * 25.4;
			$height_mm = $validated['height'] * 25.4;

			// Load story with relations
			$story->load('pages', 'user');

			try {
				// The StorybookPdf service will handle the PDF generation and output.
				$pdf = new StorybookPdf('P', 'mm', [$width_mm, $height_mm]);
				$pdf->generate($story, $validated);
				// The script will terminate in the generate() method upon PDF output.
			} catch (Throwable $e) {
				// Log the full error for debugging
				Log::error('PDF Generation Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
				// Return to setup page with a user-friendly error
				return back()->withInput()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
			}
		}
	}
