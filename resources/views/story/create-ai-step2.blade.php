@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center">
			<h1>Create a Story with AI - Step 2: Review Content</h1>
			<span class="badge bg-primary fs-6">Step 2 of 3</span>
		</div>
		<p class="text-muted">Review the generated story content below. If it looks good, proceed to the next step to identify characters and places. You can also edit the prompt that will be used for this next step.</p>
		
		@include('story.partials.alerts')
		
		<div class="card mb-4">
			<div class="card-header">
				<h2 class="h4 mb-0">{{ $story->title }}</h2>
			</div>
			<div class="card-body">
				<p><strong>Description:</strong> {{ $story->short_description }}</p>
				<p><strong>Level:</strong> {{ $story->level }}</p>
				<hr>
				<h3 class="h5">Story Pages</h3>
				<div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; padding: 1rem; border-radius: 0.375rem;">
					@foreach($story->pages as $page)
						<p><strong>Page {{ $page->page_number }}:</strong><br>{{ $page->story_text }}</p>
						@if(!$loop->last)
							<hr class="my-3">
						@endif
					@endforeach
				</div>
			</div>
		</div>
		
		<div class="card">
			<div class="card-body">
				<form action="{{ route('stories.ai-generate.entities', $story) }}" method="POST" id="entity-form">
					@csrf
					<div class="mb-3">
						<label for="prompt_entity_generation" class="form-label">2. Entity Identification Prompt</label>
						<textarea class="form-control" id="prompt_entity_generation" name="prompt_entity_generation" rows="10">{{ old('prompt_entity_generation', $story->prompt_entity_generation) }}</textarea>
						<small class="form-text text-muted">This prompt will be used to extract characters and places from the story text above.</small>
					</div>
					
					<div class="d-flex justify-content-end">
						<button type="submit" class="btn btn-primary btn-lg">Identify Characters & Places</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		// Add a loading state to the submit button to prevent double-clicks.
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.getElementById('entity-form');
			if (form) {
				form.addEventListener('submit', function () {
					const button = form.querySelector('button[type="submit"]');
					button.disabled = true;
					button.innerHTML = `
              <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              Identifying...
            `;
				});
			}
		});
	</script>
@endsection
