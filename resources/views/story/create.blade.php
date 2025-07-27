@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<h1>Create a New Story</h1>
		
		<div class="card">
			<div class="card-body">
				<form action="{{ route('stories.store') }}" method="POST">
					@csrf
					<div class="mb-3">
						<label for="title" class="form-label">Title</label>
						<input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
						@error('title')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					
					<div class="mb-3">
						<label for="short_description" class="form-label">Short Description</label>
						<textarea class="form-control @error('short_description') is-invalid @enderror" id="short_description" name="short_description" rows="3">{{ old('short_description') }}</textarea>
						@error('short_description')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					
					<div class="d-flex justify-content-end">
						<a href="{{ route('stories.index') }}" class="btn btn-secondary me-2">Cancel</a>
						<button type="submit" class="btn btn-primary">Create Story</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection
