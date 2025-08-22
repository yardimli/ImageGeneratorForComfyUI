@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center">
			<h1>Create a Story with AI - Step 2: Identify Entities</h1>
			<span class="badge bg-primary fs-6">Step 2 of 3</span>
		</div>
		<p class="text-muted">Review the story content below. If it looks good, click "Identify Characters & Places". You can edit the prompt that will be used for this step. After generation, you can edit the results before proceeding.</p>
		
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
				{{-- MODIFIED: Form action will be changed by JS. --}}
				<form action="{{ route('stories.ai-generate.entities', $story) }}" method="POST" id="entity-form">
					@csrf
					<div class="mb-3">
						<label for="prompt_entity_generation" class="form-label">2. Entity Identification Prompt</label>
						<textarea class="form-control" id="prompt_entity_generation" name="prompt_entity_generation" rows="10">{{ old('prompt_entity_generation', $story->prompt_entity_generation) }}</textarea>
						<small class="form-text text-muted">This prompt will be used to extract characters and places from the story text above.</small>
					</div>
					
					{{-- NEW: This section will be populated by AJAX after generation --}}
					<div id="entity-results" class="d-none mt-4">
						<hr>
						<h3 class="mb-3">Review & Edit Identified Entities</h3>
						<p class="text-muted">The AI has identified the following characters and places. You can edit their names, the pages they appear on, or delete them. Page numbers must be separated by commas.</p>
						<div class="row">
							<div class="col-md-6" id="characters-container">
								<h4>Characters</h4>
								{{-- Character fields will be added here --}}
							</div>
							<div class="col-md-6" id="places-container">
								<h4>Places</h4>
								{{-- Place fields will be added here --}}
							</div>
						</div>
					</div>
					
					<div class="d-flex justify-content-end align-items-center gap-3 mt-3">
						<button type="submit" class="btn btn-primary btn-lg" id="generate-button">Identify Characters & Places</button>
						{{-- NEW: This button is initially hidden --}}
						<button type="submit" class="btn btn-success btn-lg d-none" id="next-step-button">Save & Continue to Step 3</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.getElementById('entity-form');
			const generateButton = document.getElementById('generate-button');
			const nextStepButton = document.getElementById('next-step-button');
			const resultsContainer = document.getElementById('entity-results');
			const charactersContainer = document.getElementById('characters-container');
			const placesContainer = document.getElementById('places-container');
			const originalButtonText = generateButton.innerHTML;
			
			const generateEntitiesAction = "{{ route('stories.ai-generate.entities', $story) }}";
			const storeEntitiesAction = "{{ route('stories.ai-store.entities', $story) }}";
			
			const createEntityInputs = (entity, index, type, container) => {
				const entityHtml = `
					<div class="entity-row mb-2">
						<div class="input-group">
							<span class="input-group-text">Name</span>
							<input type="text" class="form-control" name="${type}[${index}][name]" value="${entity.name}">
							<span class="input-group-text">Pages</span>
							<input type="text" class="form-control" data-role="pages-str" data-type="${type}" data-index="${index}" value="${entity.pages.join(', ')}">
							<button type="button" class="btn btn-danger btn-delete-entity" title="Delete this item">&times;</button>
						</div>
						<div data-role="hidden-pages-container" data-type="${type}" data-index="${index}"></div>
					</div>
				`;
				container.insertAdjacentHTML('beforeend', entityHtml);
			};
			
			resultsContainer.addEventListener('click', function (e) {
				if (e.target && e.target.classList.contains('btn-delete-entity')) {
					e.target.closest('.entity-row').remove();
				}
			});
			
			// MODIFIED: This function handles the AJAX call for generation/regeneration.
			async function handleGeneration(e) {
				e.preventDefault();
				
				generateButton.disabled = true;
				generateButton.innerHTML = `
					<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
					Identifying...
				`;
				
				resultsContainer.classList.add('d-none');
				charactersContainer.innerHTML = '<h4>Characters</h4>'; // Reset with header
				placesContainer.innerHTML = '<h4>Places</h4>'; // Reset with header
				
				try {
					const formData = new FormData(form);
					const response = await fetch(generateEntitiesAction, { // Always use generation action
						method: 'POST',
						body: formData,
						headers: {
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
						}
					});
					
					const data = await response.json();
					
					if (!response.ok) {
						alert(data.message || 'An error occurred during entity identification.');
						throw new Error(data.message);
					}
					
					data.characters.forEach((char, index) => createEntityInputs(char, index, 'characters', charactersContainer));
					data.places.forEach((place, index) => createEntityInputs(place, index, 'places', placesContainer));
					
					const updateHiddenPageInputs = (inputElement) => {
						const type = inputElement.dataset.type;
						const index = inputElement.dataset.index;
						const hiddenContainer = document.querySelector(`div[data-role="hidden-pages-container"][data-type="${type}"][data-index="${index}"]`);
						if (!hiddenContainer) return; // In case the row was deleted
						hiddenContainer.innerHTML = ''; // Clear old hidden inputs
						
						const pages = inputElement.value.split(',').map(p => p.trim()).filter(p => p && !isNaN(p));
						pages.forEach(p => {
							const hiddenInput = document.createElement('input');
							hiddenInput.type = 'hidden';
							hiddenInput.name = `${type}[${index}][pages][]`;
							hiddenInput.value = p;
							hiddenContainer.appendChild(hiddenInput);
						});
					};
					
					document.querySelectorAll('input[data-role="pages-str"]').forEach(input => {
						updateHiddenPageInputs(input); // Initial population
						input.addEventListener('input', () => updateHiddenPageInputs(input));
					});
					
					resultsContainer.classList.remove('d-none');
					nextStepButton.classList.remove('d-none');
					
					generateButton.innerHTML = 'Regenerate Entities';
					generateButton.classList.remove('btn-primary');
					generateButton.classList.add('btn-secondary');
					
				} catch (error) {
					console.error('Entity generation failed:', error);
					if (nextStepButton.classList.contains('d-none')) {
						generateButton.innerHTML = originalButtonText;
					}
				} finally {
					generateButton.disabled = false;
				}
			}
			
			// MODIFIED: This function handles the final submission to the next step.
			function handleNextStep(e) {
				e.preventDefault();
				
				nextStepButton.disabled = true;
				nextStepButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...`;
				
				form.action = storeEntitiesAction; // Ensure correct action before submitting
				form.submit();
			}
			
			// MODIFIED: Removed the single form 'submit' listener and attached separate 'click' listeners to each button.
			generateButton.addEventListener('click', handleGeneration);
			nextStepButton.addEventListener('click', handleNextStep);
		});
	</script>
@endsection
