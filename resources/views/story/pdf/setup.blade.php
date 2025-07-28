@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="row justify-content-center">
			<div class="col-md-10">
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
							
							{{-- START MODIFICATION: Add margin inputs --}}
							<fieldset class="mb-4">
								<legend class="h5">Page Layout</legend>
								<div class="row mb-3">
									<div class="col-md-4">
										<label for="width" class="form-label">Page Width (in)</label>
										<input type="number" class="form-control" id="width" name="width" value="{{ old('width', '8.5') }}" step="0.1" required>
									</div>
									<div class="col-md-4">
										<label for="height" class="form-label">Page Height (in)</label>
										<input type="number" class="form-control" id="height" name="height" value="{{ old('height', '8.5') }}" step="0.1" required>
									</div>
									<div class="col-md-4">
										<label for="bleed" class="form-label">Bleed (in)</label>
										<input type="number" class="form-control" id="bleed" name="bleed" value="{{ old('bleed', '0.125') }}" step="0.01" required>
										<div class="form-text">Amount image extends past trim edge.</div>
									</div>
								</div>
								<div class="row mb-3">
									<div class="col-md-3">
										<label for="margin_top" class="form-label">Margin Top (in)</label>
										<input type="number" class="form-control" id="margin_top" name="margin_top" value="{{ old('margin_top', '0.5') }}" step="0.01" required>
									</div>
									<div class="col-md-3">
										<label for="margin_bottom" class="form-label">Margin Bottom (in)</label>
										<input type="number" class="form-control" id="margin_bottom" name="margin_bottom" value="{{ old('margin_bottom', '0.5') }}" step="0.01" required>
									</div>
									<div class="col-md-3">
										<label for="margin_inside" class="form-label">Margin Inside (in)</label>
										<input type="number" class="form-control" id="margin_inside" name="margin_inside" value="{{ old('margin_inside', '0.75') }}" step="0.01" required>
										<div class="form-text">Gutter/binding side.</div>
									</div>
									<div class="col-md-3">
										<label for="margin_outside" class="form-label">Margin Outside (in)</label>
										<input type="number" class="form-control" id="margin_outside" name="margin_outside" value="{{ old('margin_outside', '0.5') }}" step="0.01" required>
										<div class="form-text">Outer edge of page.</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<label for="dpi" class="form-label">Image DPI</label>
										<input type="number" class="form-control" id="dpi" name="dpi" value="{{ old('dpi', '300') }}" step="1" required>
									</div>
									<div class="col-md-6 d-flex align-items-end">
										<div class="form-check">
											<input class="form-check-input" type="checkbox" id="show_bleed_marks" name="show_bleed_marks" value="1" {{ old('show_bleed_marks', true) ? 'checked' : '' }}>
											<label class="form-check-label" for="show_bleed_marks">
												Show Bleed/Crop Marks
											</label>
										</div>
									</div>
								</div>
							</fieldset>
							{{-- END MODIFICATION --}}
							
							<hr class="my-4">
							
							<fieldset class="mb-4">
								<legend class="h5">Content Pages</legend>
								<div class="mb-3">
									<label for="title_page_text" class="form-label">Title Page Content</label>
									<textarea class="form-control" id="title_page_text" name="title_page_text" rows="4">{{ old('title_page_text', $defaultTitlePage) }}</textarea>
								</div>
								<div class="mb-3">
									<label for="copyright_text" class="form-label">Copyright Page Content</label>
									<textarea class="form-control" id="copyright_text" name="copyright_text" rows="4">{{ old('copyright_text', $defaultCopyright) }}</textarea>
								</div>
								<div class="mb-3">
									<label for="introduction_text" class="form-label">Introduction / Dedication (Optional)</label>
									<textarea class="form-control" id="introduction_text" name="introduction_text" rows="4">{{ old('introduction_text') }}</textarea>
								</div>
							</fieldset>
							
							<hr class="my-4">
							
							<fieldset>
								<legend class="h5">Styling</legend>
								<div class="row mb-3">
									<div class="col-md-6">
										<label for="font_name" class="form-label">Main Font Name</label>
										<input type="text" class="form-control" id="font_name" name="font_name" value="{{ old('font_name', 'LoveYaLikeASister') }}" required>
										<div class="form-text">The TTF file must exist in <code>resources/fonts/</code>. E.g., for "LoveYaLikeASister", the file should be "LoveYaLikeASister-Regular.ttf".</div>
									</div>
									<div class="col-md-6">
										<label for="wallpaper" class="form-label">Text Page Wallpaper</label>
										<select class="form-select" id="wallpaper" name="wallpaper">
											<option value="">No Wallpaper</option>
											@forelse($wallpapers as $wallpaper)
												<option value="{{ $wallpaper }}" {{ old('wallpaper', (str_contains($wallpaper, 'wallpaper3') ? $wallpaper : '')) == $wallpaper ? 'selected' : '' }}>{{ $wallpaper }}</option>
											@empty
												<option value="" disabled>No wallpapers found.</option>
											@endforelse
										</select>
										<div class="form-text">Wallpapers are loaded from <code>resources/wallpapers/</code>.</div>
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
								
								<h6 class="mt-4">Colors</h6>
								<div class="row mb-3">
									<div class="col-md col-6"><label for="color_title" class="form-label">Title</label><input type="color" class="form-control form-control-color" name="color_title" id="color_title" value="{{ old('color_title', '#1E1E64') }}"></div>
									<div class="col-md col-6"><label for="color_copyright" class="form-label">Copyright</label><input type="color" class="form-control form-control-color" name="color_copyright" id="color_copyright" value="{{ old('color_copyright', '#000000') }}"></div>
									<div class="col-md col-6"><label for="color_introduction" class="form-label">Intro</label><input type="color" class="form-control form-control-color" name="color_introduction" id="color_introduction" value="{{ old('color_introduction', '#000000') }}"></div>
									<div class="col-md col-6"><label for="color_main" class="form-label">Main Text</label><input type="color" class="form-control form-control-color" name="color_main" id="color_main" value="{{ old('color_main', '#000000') }}"></div>
									<div class="col-md col-6"><label for="color_footer" class="form-label">Footer</label><input type="color" class="form-control form-control-color" name="color_footer" id="color_footer" value="{{ old('color_footer', '#808080') }}"></div>
								</div>
							</fieldset>
							
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
@endsection
