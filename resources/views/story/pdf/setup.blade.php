{{-- resources/views/story/pdf/setup.blade.php --}}
@extends('layouts.bootstrap-app')

{{-- START MODIFICATION: Add styles for font previews and new logo modal --}}
@section('styles')
	<style>
      /* Dynamically generate @font-face rules for each available font */
			@php
				if (!empty($fonts)) {
					foreach($fonts as $font) {
						// Use the new route to serve the font file securely
						$fontUrl = route('assets.font', ['filename' => $font['filename']]);
						echo "@font-face { font-family: '{$font['name']}'; src: url('{$fontUrl}'); }";
					}
				}
			@endphp
			
/* Wallpaper and Logo modal styles */
      .asset-preview {
          cursor: pointer;
          border: 3px solid transparent;
          transition: border-color 0.2s ease-in-out;
          width: 100%;
          height: 150px;
          object-fit: cover;
      }

      .asset-preview:hover {
          border-color: #0d6efd;
      }

      .asset-preview.selected {
          border-color: #0d6efd;
          box-shadow: 0 0 10px rgba(13, 110, 253, 0.5);
      }

      /* START MODIFICATION: Style for multi-select sticker previews */
      .asset-preview[data-asset-type="sticker"].selected {
          border-color: #198754; /* Green for stickers */
          box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
      }
      /* END MODIFICATION */
	</style>
@endsection
{{-- END MODIFICATION --}}

@section('content')
	<div class="container py-4">
		<div class="row justify-content-center">
			<div class="col-md-10"> {{-- Increased width for more space --}}
				@if(session('error'))
					<div class="alert alert-danger">
						{{ session('error') }}
					</div>
				@endif
				
				@if($errors->any())
					<div class="alert alert-danger">
						<h5 class="alert-heading">Errors Found</h5>
						<p>Please correct the following errors:</p>
						<ul>
							@foreach ($errors->all() as $error)
								<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
				@endif
				
				<div class="card">
					<div class="card-header">
						<h3 class="mb-0">PDF Settings for "{{ $story->title }}"</h3>
					</div>
					<div class="card-body">
						<form action="{{ route('stories.pdf.generate', $story) }}" method="POST">
							@csrf
							
							{{-- START MODIFICATION: Re-structured form with new fields and added margin/alignment settings --}}
							<fieldset class="mb-4">
								<legend class="h5">Page Layout</legend>
								<div class="row mb-3">
									<div class="col-md-3">
										<label for="width" class="form-label">Page Width (in)</label>
										<input type="number" class="form-control" id="width" name="width" value="{{ old('width', '8.5') }}" step="0.1" required>
									</div>
									<div class="col-md-3">
										<label for="height" class="form-label">Page Height (in)</label>
										<input type="number" class="form-control" id="height" name="height" value="{{ old('height', '8.5') }}" step="0.1" required>
									</div>
									<div class="col-md-3">
										<label for="bleed" class="form-label">Bleed (in)</label>
										<input type="number" class="form-control" id="bleed" name="bleed" value="{{ old('bleed', '0.125') }}" step="0.01" required>
										<div class="form-text">Amount image extends past trim edge.</div>
									</div>
									<div class="col-md-3">
										<label for="dpi" class="form-label">Image DPI</label>
										<input type="number" class="form-control" id="dpi" name="dpi" value="{{ old('dpi', '300') }}" step="1" required>
									</div>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" id="show_bleed_marks" name="show_bleed_marks" value="1" {{ old('show_bleed_marks', true) ? 'checked' : '' }}>
									<label class="form-check-label" for="show_bleed_marks">
										Show Bleed/Crop Marks
									</label>
								</div>
							</fieldset>
							
							<hr class="my-4">
							
							<fieldset class="mb-4">
								<legend class="h5">Content Pages</legend>
								
								{{-- START MODIFICATION: New structured title page settings --}}
								<div class="mb-3 p-3 border rounded">
									<h6 class="fw-bold">Title Page Designer</h6>
									<div class="row g-3">
										<div class="col-md-6">
											<label class="form-label">Background Image</label>
											<div>
												<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#titleWallpaperModal">
													Select Background...
												</button>
												<span id="selectedTitleWallpaperName" class="ms-2 fst-italic text-muted">{{ old('title_wallpaper') ?: 'None' }}</span>
											</div>
											<input type="hidden" name="title_wallpaper" id="titleWallpaperInput" value="{{ old('title_wallpaper') }}">
										</div>
										<div class="col-md-6">
											<label class="form-label">Logo</label>
											<div>
												<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#logoModal">
													Select Logo...
												</button>
												<span id="selectedLogoName" class="ms-2 fst-italic text-muted">{{ old('title_logo') ?: 'None' }}</span>
											</div>
											<input type="hidden" name="title_logo" id="logoInput" value="{{ old('title_logo') }}">
										</div>
										{{-- START MODIFICATION: Add sticker selector --}}
										<div class="col-md-12">
											<label class="form-label">Stickers (up to 3)</label>
											<div>
												<button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#stickerModal">
													Select Stickers...
												</button>
												<span id="selectedStickersName" class="ms-2 fst-italic text-muted">None</span>
											</div>
											<div id="stickerInputs">
												{{-- Hidden inputs for stickers will be generated by JS --}}
											</div>
										</div>
										{{-- END MODIFICATION --}}
										<div class="col-md-12">
											<label for="title_top_text" class="form-label">Top Text (Optional)</label>
											<input type="text" class="form-control" id="title_top_text" name="title_top_text" value="{{ old('title_top_text', $defaultTitleTopText) }}">
										</div>
										<div class="col-md-12">
											<label for="title_main_text" class="form-label">Main Title</label>
											<input type="text" class="form-control" id="title_main_text" name="title_main_text" value="{{ old('title_main_text', $defaultTitleMainText) }}">
										</div>
										<div class="col-md-12">
											<label for="title_author_text" class="form-label">Author Text</label>
											<input type="text" class="form-control" id="title_author_text" name="title_author_text" value="{{ old('title_author_text', $defaultTitleAuthorText) }}">
										</div>
										<div class="col-md-12">
											<label for="title_bottom_text" class="form-label">Bottom Text (Optional)</label>
											<input type="text" class="form-control" id="title_bottom_text" name="title_bottom_text" value="{{ old('title_bottom_text', $defaultTitleBottomText) }}">
										</div>
									</div>
									<div class="form-text mt-2">Use the Styling section below to control fonts and colors for the title page.</div>
								</div>
								{{-- END MODIFICATION --}}
								
								{{-- Copyright Page --}}
								<div class="mb-3 p-3 border rounded">
									<label for="copyright_text" class="form-label fw-bold">Copyright Page Content</label>
									<textarea class="form-control" id="copyright_text" name="copyright_text" rows="4">{{ old('copyright_text', $defaultCopyright) }}</textarea>
									<div class="row mt-2">
										<div class="col-md-4">
											<label for="valign_copyright" class="form-label small text-muted">Vertical Alignment</label>
											<select class="form-select form-select-sm" id="valign_copyright" name="valign_copyright">
												<option value="top" {{ old('valign_copyright') == 'top' ? 'selected' : '' }}>Top</option>
												<option value="middle" {{ old('valign_copyright') == 'middle' ? 'selected' : '' }}>Middle</option>
												<option value="bottom" {{ old('valign_copyright', 'bottom') == 'bottom' ? 'selected' : '' }}>Bottom</option>
											</select>
										</div>
										<div class="col-md-4">
											<label for="margin_horizontal_copyright" class="form-label small text-muted">Horiz. Margin (in)</label>
											<input type="number" class="form-control form-control-sm" id="margin_horizontal_copyright" name="margin_horizontal_copyright" value="{{ old('margin_horizontal_copyright', '1.0') }}" step="0.1" required>
										</div>
									</div>
								</div>
								
								{{-- Introduction Page --}}
								<div class="mb-3 p-3 border rounded">
									<label for="introduction_text" class="form-label fw-bold">Introduction / Dedication (Optional)</label>
									<textarea class="form-control" id="introduction_text" name="introduction_text" rows="4">{{ old('introduction_text', $defaultIntroduction) }}</textarea>
									<div class="row mt-2">
										<div class="col-md-4">
											<label for="valign_introduction" class="form-label small text-muted">Vertical Alignment</label>
											<select class="form-select form-select-sm" id="valign_introduction" name="valign_introduction">
												<option value="top" {{ old('valign_introduction', 'top') == 'top' ? 'selected' : '' }}>Top</option>
												<option value="middle" {{ old('valign_introduction') == 'middle' ? 'selected' : '' }}>Middle</option>
												<option value="bottom" {{ old('valign_introduction') == 'bottom' ? 'selected' : '' }}>Bottom</option>
											</select>
										</div>
										<div class="col-md-4">
											<label for="margin_horizontal_introduction" class="form-label small text-muted">Horiz. Margin (in)</label>
											<input type="number" class="form-control form-control-sm" id="margin_horizontal_introduction" name="margin_horizontal_introduction" value="{{ old('margin_horizontal_introduction', '1.0') }}" step="0.1" required>
										</div>
									</div>
								</div>
							</fieldset>
							
							<hr class="my-4">
							
							<fieldset>
								<legend class="h5">Styling</legend>
								
								{{-- New table for font/color/size/line-height settings --}}
								<h6 class="mt-4">Fonts, Sizes, Line Height & Colors</h6>
								<div class="table-responsive">
									<table class="table table-bordered align-middle text-center">
										<thead class="table-light">
										<tr>
											<th class="text-start">Page Type</th>
											<th>Font</th>
											<th>Font Size (pt)</th>
											<th>Line Height</th>
											<th>Color</th>
										</tr>
										</thead>
										<tbody>
										@php
											$styleTypes = [
												'title' => ['label' => 'Title Page', 'size' => 28, 'line_height' => 1.2, 'color' => '#D63346', 'font' => 'LoveYaLikeASister'],
												'copyright' => ['label' => 'Copyright', 'size' => 8, 'line_height' => 1.2, 'color' => '#000000', 'font' => 'Arial'],
												'introduction' => ['label' => 'Introduction', 'size' => 12, 'line_height' => 1.5, 'color' => '#000000', 'font' => 'Arial'],
												'main' => ['label' => 'Main Text', 'size' => 14, 'line_height' => 1.6, 'color' => '#000000', 'font' => 'LoveYaLikeASister'],
											];
										@endphp
										@foreach($styleTypes as $type => $details)
											<tr>
												<td class="text-start fw-bold">{{ $details['label'] }}</td>
												<td style="width: 25%;">
													<select class="form-select" name="font_name_{{ $type }}" required>
														@forelse($fonts as $font)
															<option value="{{ $font['name'] }}" style="font-family: '{{ $font['name'] }}', sans-serif; font-size: 1.1rem;" {{ old('font_name_' . $type, $details['font']) == $font['name'] ? 'selected' : '' }}>
																{{ $font['name'] }}
															</option>
														@empty
															<option value="" disabled>No fonts found.</option>
														@endforelse
													</select>
												</td>
												<td><input type="number" class="form-control" name="font_size_{{ $type }}" value="{{ old('font_size_' . $type, $details['size']) }}"></td>
												<td><input type="number" class="form-control" name="line_height_{{ $type }}" value="{{ old('line_height_' . $type, $details['line_height']) }}" step="0.1"></td>
												<td><input type="color" class="form-control form-control-color w-100" name="color_{{ $type }}" value="{{ old('color_' . $type, $details['color']) }}"></td>
											</tr>
										@endforeach
										<tr>
											<td class="text-start fw-bold">Footer / Page #</td>
											<td class="text-muted" colspan="2">Uses 'Main Text' font</td>
											<td><input type="number" class="form-control" name="font_size_footer" value="{{ old('font_size_footer', 10) }}"></td>
											<td><input type="color" class="form-control form-control-color w-100" name="color_footer" value="{{ old('color_footer', '#808080') }}"></td>
										</tr>
										</tbody>
									</table>
								</div>
								
								<h6 class="mt-4">Text Page Styling</h6>
								<div class="row mb-3 p-3 border rounded align-items-end">
									<div class="col-md-4">
										<label for="text_box_width" class="form-label">Text Box Width (%)</label>
										<input type="number" class="form-control" id="text_box_width" name="text_box_width" value="{{ old('text_box_width', '80') }}" min="10" max="100" step="1" required>
										<div class="form-text">Width of the text area relative to the page.</div>
									</div>
									<div class="col-md-4">
										<label class="form-label">Text Box Background</label>
										<div class="form-check">
											<input class="form-check-input" type="checkbox" id="use_text_background" name="use_text_background" value="1" {{ old('use_text_background', true) ? 'checked' : '' }}>
											<label class="form-check-label" for="use_text_background">
												Enable background color
											</label>
										</div>
									</div>
									<div class="col-md-4">
										<label for="text_background_color" class="form-label">Background Color</label>
										<input type="color" class="form-control form-control-color" name="text_background_color" id="text_background_color" value="{{ old('text_background_color', '#ffffff') }}">
									</div>
									<div class="col-md-12 mt-3">
										<label class="form-label">Wallpaper (for text pages)</label>
										<div>
											<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#wallpaperModal">
												Select Wallpaper
											</button>
											<span id="selectedWallpaperName" class="ms-2 fst-italic text-muted">{{ old('wallpaper') ?: 'No wallpaper selected' }}</span>
										</div>
										<input type="hidden" name="wallpaper" id="wallpaperInput" value="{{ old('wallpaper') }}">
										<div class="form-text">Wallpapers are loaded from <code>resources/wallpapers/</code>.</div>
									</div>
								</div>
								
								{{-- New Dashed Border controls --}}
								<h6 class="mt-4">Text Page Border Styling</h6>
								<div class="row mb-3 p-3 border rounded align-items-end">
									<div class="col-md-4">
										<label class="form-label">Dashed Border</label>
										<div class="form-check form-switch">
											<input class="form-check-input" type="checkbox" id="enable_dashed_border" name="enable_dashed_border" value="1" {{ old('enable_dashed_border', true) ? 'checked' : '' }}>
											<label class="form-check-label" for="enable_dashed_border">Enable Border</label>
										</div>
									</div>
									<div class="col-md-4">
										<label for="dashed_border_width" class="form-label">Border Width (pt)</label>
										<input type="number" class="form-control" name="dashed_border_width" id="dashed_border_width" value="{{ old('dashed_border_width', 5) }}" min="0" step="0.5">
									</div>
									<div class="col-md-4">
										<label for="dashed_border_color" class="form-label">Border Color</label>
										<input type="color" class="form-control form-control-color" name="dashed_border_color" id="dashed_border_color" value="{{ old('dashed_border_color', '#333333') }}">
									</div>
								</div>
								
								<h6 class="mt-4">Margins (in)</h6>
								<div class="row mb-3">
									<div class="col-md-4">
										<label for="page_number_margin_bottom" class="form-label">Footer Bottom Margin</label>
										<input type="number" class="form-control" name="page_number_margin_bottom" value="{{ old('page_number_margin_bottom', '0.5') }}" step="0.1" required>
									</div>
								</div>
							</fieldset>
							{{-- END MODIFICATION --}}
							
							<div class="d-flex justify-content-end mt-4">
								<a href="{{ route('stories.show', $story) }}" class="btn btn-secondary me-2">Cancel</a>
								{{-- START MODIFICATION: Add an ID to the submit button for easier selection in JS --}}
								<button type="submit" class="btn btn-primary" id="generate-pdf-btn">Generate PDF</button>
								{{-- END MODIFICATION --}}
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	{{-- START MODIFICATION: Add modals for title page assets and general text page wallpaper --}}
	<div class="modal fade" id="titleWallpaperModal" tabindex="-1" aria-labelledby="titleWallpaperModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="titleWallpaperModalLabel">Select a Title Page Background</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-12 mb-3">
							<button type="button" class="btn btn-secondary btn-sm w-100" id="clearTitleWallpaper">No Background</button>
						</div>
						@forelse($wallpapers as $wallpaper)
							<div class="col-lg-3 col-md-4 col-6 mb-3">
								<img src="{{ route('assets.wallpaper', ['filename' => $wallpaper]) }}" alt="{{ $wallpaper }}" class="img-fluid rounded asset-preview" data-asset-type="title-wallpaper" data-filename="{{ $wallpaper }}">
							</div>
						@empty
							<p class="text-center">No wallpapers found in <code>resources/wallpapers/</code>.</p>
						@endforelse
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<div class="modal fade" id="logoModal" tabindex="-1" aria-labelledby="logoModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="logoModalLabel">Select a Logo</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-12 mb-3">
							<button type="button" class="btn btn-secondary btn-sm w-100" id="clearLogo">No Logo</button>
						</div>
						@forelse($logos as $logo)
							<div class="col-lg-3 col-md-4 col-6 mb-3">
								<img src="{{ route('assets.logo', ['filename' => $logo]) }}" alt="{{ $logo }}" class="img-fluid rounded asset-preview" data-asset-type="logo" data-filename="{{ $logo }}">
							</div>
						@empty
							<p class="text-center">No logos found in <code>resources/logos/</code>.</p>
						@endforelse
					</div>
				</div>
			</div>
		</div>
	</div>
	
	{{-- START MODIFICATION: Add modal for stickers --}}
	<div class="modal fade" id="stickerModal" tabindex="-1" aria-labelledby="stickerModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="stickerModalLabel">Select Stickers (up to 3)</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-12 mb-3">
							<button type="button" class="btn btn-secondary btn-sm w-100" id="clearStickers">Clear Selection</button>
						</div>
						@forelse($stickers as $sticker)
							<div class="col-lg-3 col-md-4 col-6 mb-3">
								<img src="{{ route('assets.sticker', ['filename' => $sticker]) }}" alt="{{ $sticker }}" class="img-fluid rounded asset-preview" data-asset-type="sticker" data-filename="{{ $sticker }}">
							</div>
						@empty
							<p class="text-center">No stickers found in <code>resources/stickers/</code>.</p>
						@endforelse
					</div>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
	
	<div class="modal fade" id="wallpaperModal" tabindex="-1" aria-labelledby="wallpaperModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="wallpaperModalLabel">Select a Wallpaper (for Text Pages)</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-12 mb-3">
							<button type="button" class="btn btn-secondary btn-sm w-100" id="clearWallpaper">No Wallpaper</button>
						</div>
						@forelse($wallpapers as $wallpaper)
							<div class="col-lg-3 col-md-4 col-6 mb-3">
								<img src="{{ route('assets.wallpaper', ['filename' => $wallpaper]) }}" alt="{{ $wallpaper }}" class="img-fluid rounded asset-preview" data-asset-type="wallpaper" data-filename="{{ $wallpaper }}">
							</div>
						@empty
							<p class="text-center">No wallpapers found in <code>resources/wallpapers/</code>.</p>
						@endforelse
					</div>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// --- START: LOCALSTORAGE PERSISTENCE ---
			const storyId = {{ $story->id }};
			const storageKey = `pdf_settings_${storyId}`;
			const form = document.querySelector('form');
			// We assume validation errors exist if the error alert is present.
			// This means Laravel's `old()` helper has populated the form.
			const hasServerProvidedData = document.querySelector('.alert-danger') !== null;
			
			/**
			 * Saves all form settings to localStorage.
			 */
			function saveSettings() {
				const settings = {};
				
				form.querySelectorAll('input, textarea, select').forEach(el => {
					if (!el.name || el.type === 'submit' || el.type === 'button' || el.name === '_token') {
						return; // Skip elements without a name, buttons, or the CSRF token
					}
					
					if (el.type === 'checkbox') {
						settings[el.name] = el.checked;
					} else if (el.name.endsWith('[]')) { // Handle array inputs like stickers
						const key = el.name.slice(0, -2);
						if (!settings[key]) {
							settings[key] = [];
						}
						settings[key].push(el.value);
					} else {
						settings[el.name] = el.value;
					}
				});
				
				localStorage.setItem(storageKey, JSON.stringify(settings));
				console.log('PDF settings saved to localStorage.');
			}
			
			/**
			 * Loads all form settings from localStorage.
			 */
			function loadSettings() {
				const savedSettings = localStorage.getItem(storageKey);
				if (!savedSettings) {
					return; // No settings saved, do nothing.
				}
				
				const settings = JSON.parse(savedSettings);
				
				for (const key in settings) {
					const value = settings[key];
					
					if (key === 'stickers' && Array.isArray(value)) {
						// For stickers, we just mark the previews as selected.
						// The `updateStickerSelection` function will handle creating the inputs.
						document.querySelectorAll('.asset-preview[data-asset-type="sticker"]').forEach(p => p.classList.remove('selected'));
						value.forEach(filename => {
							const preview = document.querySelector(`.asset-preview[data-filename="${filename}"][data-asset-type="sticker"]`);
							if (preview) {
								preview.classList.add('selected');
							}
						});
						continue; // Handled, move to next key
					}
					
					const el = form.querySelector(`[name="${key}"]`);
					if (el) {
						if (el.type === 'checkbox') {
							el.checked = value;
						} else {
							el.value = value;
						}
					}
				}
				console.log('PDF settings loaded from localStorage.');
			}
			// --- END: LOCALSTORAGE PERSISTENCE ---
			
			// --- Text Background Color Logic ---
			const useBgCheckbox = document.getElementById('use_text_background');
			const bgColorInput = document.getElementById('text_background_color');
			
			function toggleBgColorInput() {
				bgColorInput.disabled = !useBgCheckbox.checked;
			}
			
			useBgCheckbox.addEventListener('change', toggleBgColorInput);
			
			// --- Dashed Border Logic ---
			const useBorderCheckbox = document.getElementById('enable_dashed_border');
			const borderWidthInput = document.getElementById('dashed_border_width');
			const borderColorInput = document.getElementById('dashed_border_color');
			
			function toggleBorderInputs() {
				const isDisabled = !useBorderCheckbox.checked;
				borderWidthInput.disabled = isDisabled;
				borderColorInput.disabled = isDisabled;
			}
			
			useBorderCheckbox.addEventListener('change', toggleBorderInputs);
			
			// --- Asset Modal Logic (Unified for all asset types) ---
			const modals = {
				'title-wallpaper': new bootstrap.Modal(document.getElementById('titleWallpaperModal')),
				'logo': new bootstrap.Modal(document.getElementById('logoModal')),
				'wallpaper': new bootstrap.Modal(document.getElementById('wallpaperModal')),
			};
			
			const inputs = {
				'title-wallpaper': document.getElementById('titleWallpaperInput'),
				'logo': document.getElementById('logoInput'),
				'wallpaper': document.getElementById('wallpaperInput'),
			};
			
			const nameSpans = {
				'title-wallpaper': document.getElementById('selectedTitleWallpaperName'),
				'logo': document.getElementById('selectedLogoName'),
				'wallpaper': document.getElementById('selectedWallpaperName'),
			};
			
			const noAssetTexts = {
				'title-wallpaper': 'None',
				'logo': 'None',
				'wallpaper': 'No wallpaper selected',
			};
			
			function updateSelectedVisuals(assetType, filename) {
				document.querySelectorAll(`.asset-preview[data-asset-type="${assetType}"]`).forEach(p => {
					p.classList.toggle('selected', p.dataset.filename === filename);
				});
			}
			
			document.querySelectorAll('.asset-preview').forEach(preview => {
				if (preview.dataset.assetType === 'sticker') {
					return;
				}
				preview.addEventListener('click', function () {
					const assetType = this.dataset.assetType;
					const filename = this.dataset.filename;
					
					inputs[assetType].value = filename;
					nameSpans[assetType].textContent = filename;
					updateSelectedVisuals(assetType, filename);
					modals[assetType].hide();
				});
			});
			
			document.getElementById('clearTitleWallpaper').addEventListener('click', function() {
				inputs['title-wallpaper'].value = '';
				nameSpans['title-wallpaper'].textContent = noAssetTexts['title-wallpaper'];
				updateSelectedVisuals('title-wallpaper', '');
				modals['title-wallpaper'].hide();
			});
			
			document.getElementById('clearLogo').addEventListener('click', function() {
				inputs['logo'].value = '';
				nameSpans['logo'].textContent = noAssetTexts['logo'];
				updateSelectedVisuals('logo', '');
				modals['logo'].hide();
			});
			
			document.getElementById('clearWallpaper').addEventListener('click', function() {
				inputs['wallpaper'].value = '';
				nameSpans['wallpaper'].textContent = noAssetTexts['wallpaper'];
				updateSelectedVisuals('wallpaper', '');
				modals['wallpaper'].hide();
			});
			
			// --- Multi-select sticker modal logic ---
			const stickerModal = new bootstrap.Modal(document.getElementById('stickerModal'));
			const stickerPreviews = document.querySelectorAll('.asset-preview[data-asset-type="sticker"]');
			const stickerInputsContainer = document.getElementById('stickerInputs');
			const selectedStickersNameSpan = document.getElementById('selectedStickersName');
			const MAX_STICKERS = 3;
			
			function updateStickerSelection() {
				const selectedPreviews = document.querySelectorAll('.asset-preview[data-asset-type="sticker"].selected');
				
				stickerInputsContainer.innerHTML = '';
				const selectedFilenames = [];
				selectedPreviews.forEach(preview => {
					const filename = preview.dataset.filename;
					selectedFilenames.push(filename);
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'stickers[]';
					input.value = filename;
					stickerInputsContainer.appendChild(input);
				});
				
				selectedStickersNameSpan.textContent = selectedFilenames.length > 0 ? selectedFilenames.join(', ') : 'None';
			}
			
			stickerPreviews.forEach(preview => {
				preview.addEventListener('click', function() {
					const isSelected = this.classList.contains('selected');
					const selectedCount = document.querySelectorAll('.asset-preview[data-asset-type="sticker"].selected').length;
					
					if (!isSelected && selectedCount >= MAX_STICKERS) {
						alert(`You can select a maximum of ${MAX_STICKERS} stickers.`);
						return;
					}
					
					this.classList.toggle('selected');
					updateStickerSelection();
				});
			});
			
			document.getElementById('clearStickers').addEventListener('click', function() {
				stickerPreviews.forEach(p => p.classList.remove('selected'));
				updateStickerSelection();
				stickerModal.hide();
			});
			
			// --- INITIALIZATION LOGIC ---
			
			// If the server did not provide data (i.e., no validation errors), load from localStorage.
			// Otherwise, the form is already populated by Laravel's `old()` helper.
			if (!hasServerProvidedData) {
				loadSettings();
			}
			
			// The `old()` helper in Blade populates most fields.
			// The sticker selection is a special case that needs JS to set the 'selected' class.
			// This will correctly override any selection from localStorage if `old` data exists.
			const initialStickers = {!! json_encode(old('stickers', [])) !!};
			if (initialStickers.length > 0) {
				document.querySelectorAll('.asset-preview[data-asset-type="sticker"]').forEach(p => p.classList.remove('selected'));
				initialStickers.forEach(filename => {
					const preview = document.querySelector(`.asset-preview[data-filename="${filename}"][data-asset-type="sticker"]`);
					if (preview) {
						preview.classList.add('selected');
					}
				});
			}
			
			// Now, synchronize the entire UI based on the current state of the form,
			// which is now populated either by `old()` or `localStorage`.
			
			// Sync enabled/disabled state of conditional inputs.
			toggleBgColorInput();
			toggleBorderInputs();
			
			// Sync visual selection for single-select modals.
			for (const assetType in inputs) {
				const filename = inputs[assetType].value;
				nameSpans[assetType].textContent = filename || noAssetTexts[assetType];
				updateSelectedVisuals(assetType, filename);
			}
			
			// Sync sticker inputs and display text based on 'selected' classes.
			updateStickerSelection();
			
			// START MODIFICATION: Attach an event listener to the submit button's click event.
			// This is more reliable than the form's 'submit' event when the response is a file download,
			// as the click event fires just before the form submission is initiated.
			const submitButton = document.getElementById('generate-pdf-btn');
			if (submitButton) {
				submitButton.addEventListener('click', function() {
					saveSettings();
					// The button's default action (submitting the form) will proceed after this handler runs.
				});
			}
			// END MODIFICATION
		});
	</script>
@endsection
