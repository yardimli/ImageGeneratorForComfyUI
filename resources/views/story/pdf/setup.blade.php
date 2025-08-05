@extends('layouts.bootstrap-app')

{{-- START MODIFICATION: Add styles for font previews and wallpaper modal --}}
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
			
/* Wallpaper modal styles */
      .wallpaper-preview {
          cursor: pointer;
          border: 3px solid transparent;
          transition: border-color 0.2s ease-in-out;
          width: 100%;
          height: 150px;
          object-fit: cover;
      }

      .wallpaper-preview:hover {
          border-color: #0d6efd;
      }

      .wallpaper-preview.selected {
          border-color: #0d6efd;
          box-shadow: 0 0 10px rgba(13, 110, 253, 0.5);
      }
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
								
								{{-- Title Page --}}
								<div class="mb-3 p-3 border rounded">
									<label for="title_page_text" class="form-label fw-bold">Title Page Content</label>
									<textarea class="form-control" id="title_page_text" name="title_page_text" rows="4">{{ old('title_page_text', $defaultTitlePage) }}</textarea>
									<div class="row mt-2">
										<div class="col-md-4">
											<label for="valign_title" class="form-label small text-muted">Vertical Alignment</label>
											<select class="form-select form-select-sm" id="valign_title" name="valign_title">
												<option value="top" {{ old('valign_title') == 'top' ? 'selected' : '' }}>Top</option>
												<option value="middle" {{ old('valign_title', 'middle') == 'middle' ? 'selected' : '' }}>Middle</option>
												<option value="bottom" {{ old('valign_title') == 'bottom' ? 'selected' : '' }}>Bottom</option>
											</select>
										</div>
										<div class="col-md-4">
											<label for="margin_horizontal_title" class="form-label small text-muted">Horiz. Margin (in)</label>
											<input type="number" class="form-control form-control-sm" id="margin_horizontal_title" name="margin_horizontal_title" value="{{ old('margin_horizontal_title', '1.0') }}" step="0.1" required>
										</div>
									</div>
								</div>
								
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
								<div class="row mb-3">
									<div class="col-md-6">
										<label for="font_name" class="form-label">Main Font</label>
										<select class="form-select" id="font_name" name="font_name" required>
											@forelse($fonts as $font)
												<option value="{{ $font['name'] }}" style="font-family: '{{ $font['name'] }}', sans-serif; font-size: 1.2rem;" {{ old('font_name', 'LoveYaLikeASister') == $font['name'] ? 'selected' : '' }}>
													{{ $font['name'] }}
												</option>
											@empty
												<option value="" disabled>No fonts found in resources/fonts.</option>
											@endforelse
										</select>
										<div class="form-text">Fonts are loaded from <code>resources/fonts/</code>. Previews are best-effort.</div>
									</div>
									<div class="col-md-6">
										<label class="form-label">Text Page Wallpaper</label>
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
											<input class="form-check-input" type="checkbox" id="use_text_background" name="use_text_background" value="1" {{ old('use_text_background') !== null ? 'checked' : (request()->isMethod('get') ? 'checked' : '') }}>
											<label class="form-check-label" for="use_text_background">
												Enable background color
											</label>
										</div>
									</div>
									<div class="col-md-4">
										<label for="text_background_color" class="form-label">Background Color</label>
										<input type="color" class="form-control form-control-color" name="text_background_color" id="text_background_color" value="{{ old('text_background_color', '#ffffff') }}">
									</div>
								</div>
								
								<h6 class="mt-4">Font Sizes (pt)</h6>
								<div class="row mb-3">
									<div class="col-md col-6"><label for="font_size_title" class="form-label">Title</label><input type="number" class="form-control" name="font_size_title" id="font_size_title" value="{{ old('font_size_title', 28) }}"></div>
									<div class="col-md col-6"><label for="font_size_copyright" class="form-label">Copyright</label><input type="number" class="form-control" name="font_size_copyright" id="font_size_copyright" value="{{ old('font_size_copyright', 8) }}"></div>
									<div class="col-md col-6"><label for="font_size_introduction" class="form-label">Intro</label><input type="number" class="form-control" name="font_size_introduction" id="font_size_introduction" value="{{ old('font_size_introduction', 12) }}"></div>
									<div class="col-md col-6"><label for="font_size_main" class="form-label">Main Text</label><input type="number" class="form-control" name="font_size_main" id="font_size_main" value="{{ old('font_size_main', 14) }}"></div>
									<div class="col-md col-6"><label for="font_size_footer" class="form-label">Footer</label><input type="number" class="form-control" name="font_size_footer" id="font_size_footer" value="{{ old('font_size_footer', 10) }}"></div>
								</div>
								
								<h6 class="mt-4">Margins (in)</h6>
								<div class="row mb-3">
									<div class="col-md-4">
										<label for="page_number_margin_bottom" class="form-label">Footer Bottom Margin</label>
										<input type="number" class="form-control" name="page_number_margin_bottom" value="{{ old('page_number_margin_bottom', '0.5') }}" step="0.1" required>
									</div>
								</div>
								
								<h6 class="mt-4">Colors</h6>
								<div class="row mb-3">
									<div class="col-md col-6"><label for="color_title" class="form-label">Title</label><input type="color" class="form-control form-control-color" name="color_title" id="color_title" value="{{ old('color_title', '#1E1E64') }}"></div>
									<div class="col-md col-6"><label for="color_copyright" class="form-label">Copyright</label><input type="color" class="form-control form-control-color" name="color_copyright" id="color_copyright" value="{{ old('color_copyright', '#000000') }}"></div>
									<div class="col-md col-6"><label for="color_introduction" class="form-label">Intro</label><input type="color" class="form-control form-control-color" name="color_introduction" id="color_introduction" value="{{ old('color_introduction', '#000000') }}"></div>
									<div class="col-md col-6"><label for="color_main" class="form-label">Main Text</label><input type="color" class="form-control form-control-color" name="color_main" id="color_main" value="{{ old('color_main', '#000000') }}"></div>
									<div class="col-md col-6"><label for="color_footer" class="form-label">Footer</label><input type="color" class="form-control form-control-color" name="color_footer" id="color_footer" value="{{ old('color_footer', '#808080') }}"></div>
								</div>
							</fieldset>
							{{-- END MODIFICATION --}}
							
							<div class="d-flex justify-content-end mt-4">
								<a href="{{ route('stories.show', $story) }}" class="btn btn-secondary me-2">Cancel</a>
								<button type="submit" class="btn btn-primary">Generate PDF</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	{{-- START MODIFICATION: Wallpaper selection modal --}}
	<div class="modal fade" id="wallpaperModal" tabindex="-1" aria-labelledby="wallpaperModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="wallpaperModalLabel">Select a Wallpaper</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-12 mb-3">
							<button type="button" class="btn btn-secondary btn-sm w-100" id="clearWallpaper">
								No Wallpaper
							</button>
						</div>
						@forelse($wallpapers as $wallpaper)
							<div class="col-lg-3 col-md-4 col-6 mb-3">
								{{-- Use the new route to serve the wallpaper image --}}
								<img src="{{ route('assets.wallpaper', ['filename' => $wallpaper]) }}"
								     alt="{{ $wallpaper }}"
								     class="img-fluid rounded wallpaper-preview"
								     data-filename="{{ $wallpaper }}">
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

{{-- START MODIFICATION: Add JavaScript for interactive form elements --}}
@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// --- Text Background Color Logic ---
			const useBgCheckbox = document.getElementById('use_text_background');
			const bgColorInput = document.getElementById('text_background_color');
			
			function toggleBgColorInput() {
				bgColorInput.disabled = !useBgCheckbox.checked;
			}
			
			useBgCheckbox.addEventListener('change', toggleBgColorInput);
			toggleBgColorInput(); // Set initial state on page load
			
			// --- Wallpaper Modal Logic ---
			const wallpaperModalEl = document.getElementById('wallpaperModal');
			const wallpaperModal = new bootstrap.Modal(wallpaperModalEl);
			const wallpaperInput = document.getElementById('wallpaperInput');
			const selectedWallpaperName = document.getElementById('selectedWallpaperName');
			const previews = document.querySelectorAll('.wallpaper-preview');
			
			function updateSelectedVisuals(filename) {
				previews.forEach(p => {
					if (p.dataset.filename === filename) {
						p.classList.add('selected');
					} else {
						p.classList.remove('selected');
					}
				});
			}
			
			previews.forEach(preview => {
				preview.addEventListener('click', function () {
					const filename = this.dataset.filename;
					wallpaperInput.value = filename;
					selectedWallpaperName.textContent = filename;
					updateSelectedVisuals(filename);
					wallpaperModal.hide();
				});
			});
			
			document.getElementById('clearWallpaper').addEventListener('click', function() {
				wallpaperInput.value = '';
				selectedWallpaperName.textContent = 'No wallpaper selected';
				updateSelectedVisuals('');
				wallpaperModal.hide();
			});
			
			// Set initial selected state on page load
			updateSelectedVisuals(wallpaperInput.value);
		});
	</script>
@endsection
{{-- END MODIFICATION --}}
