@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<h1>Create a Story with AI</h1>
		<p class="text-muted">Provide instructions for the story, choose the number of pages and an AI model, and let the magic happen.</p>
		
		@if(session('error'))
			<div class="alert alert-danger">
				{{ session('error') }}
			</div>
		@endif
		
		@if($errors->any())
			<div class="alert alert-danger">
				<p><strong>There were some problems with your input:</strong></p>
				<ul>
					@foreach ($errors->all() as $error)
						<li>{{ $error }}</li>
					@endforeach
				</ul>
			</div>
		@endif
		
		<div class="card">
			<div class="card-body">
				<form action="{{ route('stories.store-ai') }}" method="POST" id="ai-story-form">
					@csrf
					<div class="mb-3">
						<label for="instructions" class="form-label">Story Instructions</label>
						<textarea class="form-control @error('instructions') is-invalid @enderror" id="instructions" name="instructions" rows="6" placeholder="e.g., A story about a brave knight who befriends a shy dragon to save a kingdom from an evil sorcerer. Include a wise old owl as a character." required>{{ old('instructions') }}</textarea>
						<div class="form-text">Describe the plot, characters, and setting you want in your story. Be as descriptive as you like.</div>
						@error('instructions')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					
					<div class="row">
						<div class="col-md-6 mb-3">
							<label for="num_pages" class="form-label">Number of Pages</label>
							<select class="form-select @error('num_pages') is-invalid @enderror" id="num_pages" name="num_pages" required>
								@for ($i = 1; $i <= 20; $i++)
									<option value="{{ $i }}" {{ old('num_pages', 5) == $i ? 'selected' : '' }}>{{ $i }}</option>
								@endfor
							</select>
							@error('num_pages')
							<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>
						<div class="col-md-6 mb-3">
							<label for="model" class="form-label">AI Model</label>
							<select class="form-select @error('model') is-invalid @enderror" id="model" name="model" required>
								<option value="">-- Select a Model --</option>
								@forelse($models as $model)
									<option value="{{ $model['id'] }}" {{ old('model') == $model['id'] ? 'selected' : '' }}>
										{{ $model['name'] }}
									</option>
								@empty
									<option value="" disabled>Could not load models.</option>
								@endforelse
							</select>
							<div class="form-text">Some models are better at creative writing than others. Experiment to see what works best!</div>
							@error('model')
							<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>
					</div>
					
					<div class="d-flex justify-content-end align-items-center gap-3">
						<a href="{{ route('stories.index') }}" class="btn btn-secondary">Cancel</a>
						<button type="submit" class="btn btn-primary btn-lg" id="generate-btn">
							<span id="btn-text">Generate Story</span>
							<span id="btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.getElementById('ai-story-form');
			const generateBtn = document.getElementById('generate-btn');
			const btnText = document.getElementById('btn-text');
			const btnSpinner = document.getElementById('btn-spinner');
			
			if (form) {
				form.addEventListener('submit', function () {
					generateBtn.disabled = true;
					btnText.textContent = 'Generating...';
					btnSpinner.classList.remove('d-none');
				});
			}
		});
	</script>
@endsection
