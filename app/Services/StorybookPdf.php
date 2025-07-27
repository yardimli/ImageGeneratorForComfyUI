<?php

	namespace App\Services;

	use App\Models\Story;
	use FPDF;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Str;

	/**
	 * Custom PDF class to generate a storybook, ported from storybook.py.
	 * Extends the FPDF library.
	 */
	class StorybookPdf extends FPDF
	{
		protected string $fontName = 'Arial';
		protected ?string $wallpaperPath = null;
		protected bool $showFooter = false;
		protected int $logicalPageNumber = 0;

		/**
		 * Overridden Footer method.
		 */
		public function Footer(): void
		{
			if ($this->showFooter) {
				$this->SetY(-20);
				$this->SetFont($this->fontName, 'I', 10);
				$this->SetTextColor(128, 128, 128);
				$this->Cell(0, 10, $this->logicalPageNumber, 0, 0, 'C');
			}
		}

		/**
		 * Draws a rounded rectangle with a dashed border.
		 */
		public function drawRoundedDottedBorder(float $x, float $y, float $w, float $h, float $r): void
		{
			$this->SetDrawColor(180, 180, 180);
			$this->SetLineWidth(0.3);
			$this->SetDash(1, 1); // 1mm dash, 1mm gap

			$this->RoundedRect($x, $y, $w, $h, $r, 'D');

			$this->SetDash(); // Reset dash pattern
		}

		/**
		 * The main generation method that builds and outputs the PDF.
		 *
		 * @param  \App\Models\Story  $story
		 * @param  array  $config
		 * @throws \Exception
		 */
		public function generate(Story $story, array $config): void
		{
			$this->fontName = preg_replace('/[^a-zA-Z0-9]/', '', $config['font_name']);
			$fontTtfFile = resource_path('fonts/' . $config['font_name'] . '-Regular.ttf');

			if (! File::exists($fontTtfFile)) {
				throw new \Exception("Font file not found: " . $fontTtfFile);
			}
			$this->AddFont($this->fontName, '', $config['font_name'] . '-Regular.ttf');
			$this->AddFont($this->fontName, 'I', $config['font_name'] . '-Regular.ttf'); // Fallback for Italic

			if (! empty($config['wallpaper'])) {
				$wallpaperFile = resource_path('wallpapers/' . $config['wallpaper']);
				if (File::exists($wallpaperFile)) {
					$this->wallpaperPath = $wallpaperFile;
				}
			}

			$this->SetAutoPageBreak(true, 15);

			// --- Title Page ---
			$this->AddPage();
			$this->SetFont($this->fontName, '', 24);
			$this->SetTextColor(30, 30, 100);
			$this->SetY($this->h / 2 - 20);
			$this->MultiCell(0, 10, $this->GetStringAsUtf8($story->title), 0, 'C');
			$this->Ln(15);
			$this->SetFont($this->fontName, '', 14);
			$this->SetTextColor(0);
			$this->MultiCell(0, 10, $this->GetStringAsUtf8("A simplified storybook"), 0, 'C');
			$this->showFooter = false;

			// --- Text and Image Pages ---
			foreach ($story->pages as $page) {
				// --- TEXT PAGE ---
				$this->AddPage();
				$this->logicalPageNumber++;

				if ($this->wallpaperPath) {
					$this->Image($this->wallpaperPath, 0, 0, $this->w, $this->h);
				}

				$this->drawRoundedDottedBorder(10, 10, $this->w - 20, $this->h - 20, 10);
				$this->SetFont($this->fontName, '', 14);
				$this->SetTextColor(0);

				// Vertical Centering Logic
				$borderMargin = 10;
				$textAreaHeight = $this->h - (2 * $borderMargin);
				$cellWidth = $this->w - (2 * $borderMargin) - 20; // Inner padding
				$lineHeight = 8;
				$storyText = $this->GetStringAsUtf8($page->story_text);

				$textBlockHeight = $this->getMultiCellHeight($cellWidth, $lineHeight, $storyText);
				$yStart = (($textAreaHeight - $textBlockHeight) / 2) + $borderMargin;
				if ($yStart < $borderMargin + 10) {
					$yStart = $borderMargin + 10;
				}

				$this->SetXY(($this->w - $cellWidth) / 2, $yStart);
				$this->MultiCell($cellWidth, $lineHeight, $storyText, 0, 'C');
				$this->showFooter = true;

				// --- IMAGE PAGE ---
				$this->AddPage();
				$imagePath = $page->image_path;
				$fullPath = null;
				if ($imagePath) {
					if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
						$fullPath = $imagePath;
					} else {
						$publicPath = public_path(str_replace(url('/'), '', $imagePath));
						if (File::exists($publicPath)) {
							$fullPath = $publicPath;
						}
					}
				}

				if ($fullPath) {
					$this->Image($fullPath, 0, 0, $this->w, $this->h, '', '', '', false, $config['dpi']);
				} else {
					$this->SetFont($this->fontName, '', 14);
					$this->SetXY(0, $this->h / 2 - 5);
					$this->Cell(0, 10, "Image not found", 0, 0, 'C');
				}
				$this->showFooter = false;
			}

			$this->Output('D', Str::slug($story->title) . '.pdf', true);
		}

		/**
		 * Converts a string to UTF-8 for FPDF.
		 */
		private function GetStringAsUtf8(string $string): string
		{
			return iconv('UTF-8', 'windows-1252//TRANSLIT', $string);
		}

		/**
		 * A helper to calculate the height of a MultiCell.
		 */
		private function getMultiCellHeight(float $w, float $h, string $txt): float
		{
			$cw = &$this->CurrentFont['cw'];
			if ($w == 0) {
				$w = $this->w - $this->rMargin - $this->x;
			}
			$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
			$s = str_replace("\r", '', $txt);
			$nb = strlen($s);
			if ($nb > 0 && $s[$nb - 1] == "\n") {
				$nb--;
			}
			$sep = -1;
			$i = 0;
			$j = 0;
			$l = 0;
			$nl = 1;
			while ($i < $nb) {
				$c = $s[$i];
				if ($c == "\n") {
					$i++;
					$sep = -1;
					$j = $i;
					$l = 0;
					$nl++;
					continue;
				}
				if ($c == ' ') {
					$sep = $i;
				}
				$l += $cw[ord($c)] ?? 0;
				if ($l > $wmax) {
					if ($sep == -1) {
						if ($i == $j) {
							$i++;
						}
					} else {
						$i = $sep + 1;
					}
					$sep = -1;
					$j = $i;
					$l = 0;
					$nl++;
				} else {
					$i++;
				}
			}
			return $nl * $h;
		}

		/**
		 * Draws a rounded rectangle.
		 */
		protected function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
		{
			$k = $this->k;
			$hp = $this->h;
			$op = ($style == 'F') ? 'f' : (($style == 'FD' || $style == 'DF') ? 'B' : 'S');
			$MyArc = 4 / 3 * (sqrt(2) - 1);
			$this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
			$xc = $x + $w - $r;
			$yc = $y + $r;
			$this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
			$this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
			$xc = $x + $w - $r;
			$yc = $y + $h - $r;
			$this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
			$this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
			$xc = $x + $r;
			$yc = $y + $h - $r;
			$this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
			$this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
			$xc = $x + $r;
			$yc = $y + $r;
			$this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
			$this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
			$this->_out($op);
		}

		/**
		 * Helper for drawing arcs in RoundedRect.
		 */
		protected function _Arc($x1, $y1, $x2, $y2, $x3, $y3): void
		{
			$h = $this->h;
			$this->_out(sprintf(
				'%.2F %.2F %.2F %.2F %.2F %.2F c',
				$x1 * $this->k,
				($h - $y1) * $this->k,
				$x2 * $this->k,
				($h - $y2) * $this->k,
				$x3 * $this->k,
				($h - $y3) * $this->k
			));
		}

		/**
		 * Sets the dash pattern for lines.
		 */
		protected function SetDash(float $dash = 0, float $gap = 0): void
		{
			if ($dash > 0 || $gap > 0) {
				$this->_out(sprintf('[%.2F %.2F] 0 d', $dash * $this->k, $gap * $this->k));
			} else {
				$this->_out('[] 0 d');
			}
		}
	}
