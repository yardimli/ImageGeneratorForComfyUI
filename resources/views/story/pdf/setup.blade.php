@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="row justify-content-center">
			<div class="col-md-8">
				@if(session('error'))
					<div class="alert alert-danger">
						{{ session('error') }}
					</div>
				@endif
				
				<div class="card">
					<div class="card-header">
						<h3 class="mb-0">PDF Settings for "{{ $story->title }}"</h3>
					</div>
					<div class="card-body">
						<form action="{{ route('stories.pdf.generate', $story) }}" method="POST">
							@csrf
							<h5 class="mb-3">Page & Document Settings</h5>
							<div class="row mb-3">
								<div class="col-md-4">
									<label for="width" class="form-label">Page Width (inches)</label>
									<input type="number" class="form-control" id="width" name="width" value="8.5" step="0.1" required>
								</div>
								<div class="col-md-4">
									<label for="height" class="form-label">Page Height (inches)</label>
									<input type="number" class="form-control" id="height" name="height" value="8.5" step="0.1" required>
								</div>
								<div class="col-md-4">
									<label for="dpi" class="form-label">Image DPI</label>
									<input type="number" class="form-control" id="dpi" name="dpi" value="300" step="1" required>
								</div>
							</div>
							
							<hr class="my-4">
							
							<h5 class="mb-3">Content Settings</h5>
							<div class="row mb-3">
								<div class="col-md-6">
									<label for="font_name" class="form-label">Font Name</label>
									<input type="text" class="form-control" id="font_name" name="font_name" value="CactusClassicalSerif" required>
									<div class="form-text">The name of the font to use. The TTF file must exist in <code>resources/fonts/</code>. E.g., for "CactusClassicalSerif", the file should be "CactusClassicalSerif-Regular.ttf".</div>
								</div>
								<div class="col-md-6">
									<label for="wallpaper" class="form-label">Text Page Wallpaper</label>
									<select class="form-select" id="wallpaper" name="wallpaper">
										<option value="">No Wallpaper</option>
										@forelse($wallpapers as $wallpaper)
											<option value="{{ $wallpaper }}" {{ str_contains($wallpaper, 'wallpaper3') ? 'selected' : '' }}>{{ $wallpaper }}</option>
										@empty
											<option value="" disabled>No wallpapers found.</option>
										@endforelse
									</select>
									<div class="form-text">Wallpapers are loaded from <code>resources/wallpapers/</code>.</div>
								</div>
							</div>
							
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
