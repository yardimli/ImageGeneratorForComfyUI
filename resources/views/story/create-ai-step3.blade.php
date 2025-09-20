@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center">
			<h1>Create a Story with AI - Step 3: Describe Entities</h1>
			<span class="badge bg-primary fs-6">Step 3 of 3</span>
		</div>
		<p class="text-muted">The AI will now generate a description for each character and place. You can edit the prompts below before generating. Review and accept each description to continue.</p>
		
		@include('story.partials.alerts')
		
		<div class="card mb-4">
			<div class="card-body">
				<div class="row">
					<div class="col-md-6">
						<div class="mb-3">
							<label for="prompt_character_description" class="form-label">3. Character Description Prompt</label>
							<textarea class="form-control" id="prompt_character_description" name="prompt_character_description" rows="8">{{ old('prompt_character_description', $story->prompt_character_description) }}</textarea>
							<small class="form-text text-muted">This prompt is used to generate the description for each character below.</small>
						</div>
					</div>
					<div class="col-md-6">
						<div class="mb-3">
							<label for="prompt_place_description" class="form-label">4. Place Description Prompt</label>
							<textarea class="form-control" id="prompt_place_description" name="prompt_place_description" rows="8">{{ old('prompt_place_description', $story->prompt_place_description) }}</textarea>
							<small class="form-text text-muted">This prompt is used to generate the description for each place below.</small>
						</div>
					</div>
				</div>
				{{-- START MODIFICATION: Add model selection dropdown. --}}
				<div class="row">
					<div class="col-md-6">
						<div class="mb-3">
							<label for="description_model" class="form-label">AI Model for Descriptions</label>
							<select class="form-select" id="description_model" name="description_model">
								@if(empty($models))
									<option value="" disabled>Could not load models.</option>
								@else
									@foreach($models as $model)
										{{-- Use the story's model as the default selection --}}
										<option value="{{ $model['id'] }}" {{ $story->model == $model['id'] ? 'selected' : '' }}>{{ $model['name'] }}</option>
									@endforeach
								@endif
							</select>
							<small class="form-text text-muted">Select the model to use for generating all descriptions below.</small>
						</div>
					</div>
				</div>
				{{-- END MODIFICATION --}}
			</div>
		</div>
		
		<div class="row">
			<div class="col-md-6">
				<div class="card">
					<div class="card-header">
						<h2 class="h5 mb-0">Characters</h2>
					</div>
					<ul class="list-group list-group-flush" id="characters-list">
						@forelse($story->characters as $character)
							{{-- MODIFIED: Check for existing description to set initial state --}}
							@php
								$hasDescription = !empty($character->description);
							@endphp
							<li class="list-group-item" data-type="character" data-name="{{ $character->name }}" {{ $hasDescription ? 'data-is-complete="true"' : '' }}>
								<div class="d-flex justify-content-between align-items-center">
									<strong>{{ $character->name }}</strong>
									{{-- MODIFIED: Badge is green and says "Saved" if description exists --}}
									<span class="badge {{ $hasDescription ? 'bg-success' : 'bg-secondary' }} status-badge">{{ $hasDescription ? 'Saved' : 'Pending' }}</span>
								</div>
								{{-- MODIFIED: Description container is visible if description exists --}}
								<div class="description-container mt-2 {{ $hasDescription ? '' : 'd-none' }}">
									{{-- MODIFIED: Textarea shows existing description and is readonly if it exists --}}
									<textarea class="form-control" rows="4" aria-label="Description for {{ $character->name }}" {{ $hasDescription ? 'readonly' : '' }}>{{ $character->description }}</textarea>
									{{-- MODIFIED: Buttons are hidden if description exists --}}
									<div class="mt-2 text-end {{ $hasDescription ? 'd-none' : '' }}">
										<button class="btn btn-sm btn-secondary btn-regenerate">Regenerate</button>
										<button class="btn btn-sm btn-success btn-accept">Accept & Save</button>
									</div>
								</div>
							</li>
						@empty
							<li class="list-group-item text-muted">No characters were identified.</li>
						@endforelse
					</ul>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card">
					<div class="card-header">
						<h2 class="h5 mb-0">Places</h2>
					</div>
					<ul class="list-group list-group-flush" id="places-list">
						@forelse($story->places as $place)
							{{-- MODIFIED: Check for existing description to set initial state --}}
							@php
								$hasDescription = !empty($place->description);
							@endphp
							<li class="list-group-item" data-type="place" data-name="{{ $place->name }}" {{ $hasDescription ? 'data-is-complete="true"' : '' }}>
								<div class="d-flex justify-content-between align-items-center">
									<strong>{{ $place->name }}</strong>
									{{-- MODIFIED: Badge is green and says "Saved" if description exists --}}
									<span class="badge {{ $hasDescription ? 'bg-success' : 'bg-secondary' }} status-badge">{{ $hasDescription ? 'Saved' : 'Pending' }}</span>
								</div>
								{{-- MODIFIED: Description container is visible if description exists --}}
								<div class="description-container mt-2 {{ $hasDescription ? '' : 'd-none' }}">
									{{-- MODIFIED: Textarea shows existing description and is readonly if it exists --}}
									<textarea class="form-control" rows="4" aria-label="Description for {{ $place->name }}" {{ $hasDescription ? 'readonly' : '' }}>{{ $place->description }}</textarea>
									{{-- MODIFIED: Buttons are hidden if description exists --}}
									<div class="mt-2 text-end {{ $hasDescription ? 'd-none' : '' }}">
										<button class="btn btn-sm btn-secondary btn-regenerate">Regenerate</button>
										<button class="btn btn-sm btn-success btn-accept">Accept & Save</button>
									</div>
								</div>
							</li>
						@empty
							<li class="list-group-item text-muted">No places were identified.</li>
						@endforelse
					</ul>
				</div>
			</div>
		</div>
		
		<div class="mt-4 text-center d-none" id="finish-container">
			<p class="text-success fw-bold">All entities have been described!</p>
			<a href="{{ route('stories.edit', $story) }}" class="btn btn-primary btn-lg">Finish & Edit Story</a>
		</div>
	
	</div>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const storyId = {{ $story->id }};
			const csrfToken = '{{ csrf_token() }}';
			// MODIFIED: Select only items that do NOT have the 'data-is-complete' attribute.
			const itemsToProcess = Array.from(document.querySelectorAll('#characters-list li[data-type]:not([data-is-complete]), #places-list li[data-type]:not([data-is-complete])'));
			const finishContainer = document.getElementById('finish-container');
			let currentItemIndex = 0;
			
			// START MODIFICATION: Add logic to save/load model selection from localStorage.
			const descriptionModelSelect = document.getElementById('description_model');
			const descriptionModelKey = 'storyCreateAi_descriptionModel';
			
			if (descriptionModelSelect) {
				const savedModel = localStorage.getItem(descriptionModelKey);
				if (savedModel) {
					descriptionModelSelect.value = savedModel;
				}
				
				descriptionModelSelect.addEventListener('change', (e) => {
					localStorage.setItem(descriptionModelKey, e.target.value);
				});
			}
			// END MODIFICATION
			
			function processNextItem() {
				if (currentItemIndex >= itemsToProcess.length) {
					// MODIFIED: Also check if there were any items to begin with before showing the finish button.
					const totalItems = document.querySelectorAll('#characters-list li[data-type], #places-list li[data-type]').length;
					if (totalItems > 0) {
						finishContainer.classList.remove('d-none');
					}
					return;
				}
				const itemElement = itemsToProcess[currentItemIndex];
				generateDescriptionFor(itemElement);
			}
			
			async function generateDescriptionFor(element) {
				const type = element.dataset.type;
				const name = element.dataset.name;
				const statusBadge = element.querySelector('.status-badge');
				const descriptionContainer = element.querySelector('.description-container');
				const textarea = descriptionContainer.querySelector('textarea');
				const buttons = descriptionContainer.querySelectorAll('button');
				
				statusBadge.className = 'badge bg-info text-dark status-badge';
				statusBadge.textContent = 'Generating...';
				textarea.disabled = true; // Disable textarea during generation
				buttons.forEach(b => b.disabled = true);
				
				try {
					const formData = new FormData();
					formData.append('story_id', storyId);
					formData.append('type', type);
					formData.append('name', name);
					formData.append('_token', csrfToken);
					
					// START MODIFICATION: Get the selected model and add it to the form data.
					const model = document.getElementById('description_model').value;
					formData.append('model', model);
					// END MODIFICATION
					
					if (type === 'character') {
						const promptText = document.getElementById('prompt_character_description').value;
						formData.append('prompt', promptText);
					} else if (type === 'place') {
						const promptText = document.getElementById('prompt_place_description').value;
						formData.append('prompt', promptText);
					}
					
					const response = await fetch("{{ route('stories.ai-generate.description') }}", {
						method: 'POST',
						body: formData,
						headers: { 'Accept': 'application/json' },
					});
					
					const data = await response.json();
					
					if (!response.ok) {
						throw new Error(data.message || 'Failed to generate description.');
					}
					
					textarea.value = data.description;
					descriptionContainer.classList.remove('d-none');
					statusBadge.className = 'badge bg-warning text-dark status-badge';
					statusBadge.textContent = 'Needs Review';
					
				} catch (error) {
					console.error('Description generation failed:', error);
					statusBadge.className = 'badge bg-danger status-badge';
					statusBadge.textContent = 'Error';
				} finally {
					textarea.disabled = false; // Re-enable textarea after generation
					buttons.forEach(b => b.disabled = false);
				}
			}
			
			async function saveDescriptionFor(element) {
				const type = element.dataset.type;
				const name = element.dataset.name;
				const descriptionContainer = element.querySelector('.description-container');
				const textarea = descriptionContainer.querySelector('textarea');
				const acceptBtn = element.querySelector('.btn-accept');
				const originalBtnText = acceptBtn.innerHTML;
				
				acceptBtn.disabled = true;
				acceptBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Saving...`;
				
				try {
					const formData = new FormData();
					formData.append('story_id', storyId);
					formData.append('type', type);
					formData.append('name', name);
					formData.append('description', textarea.value);
					formData.append('_token', csrfToken);
					
					// Also send the current prompts to be saved
					formData.append('prompt_character_description', document.getElementById('prompt_character_description').value);
					formData.append('prompt_place_description', document.getElementById('prompt_place_description').value);
					
					const response = await fetch("{{ route('stories.ai-store.description') }}", {
						method: 'POST',
						body: formData,
						headers: { 'Accept': 'application/json' },
					});
					
					const data = await response.json();
					
					if (!response.ok) {
						throw new Error(data.message || 'Failed to save description.');
					}
					
					return true; // Indicate success
					
				} catch (error) {
					console.error('Description saving failed:', error);
					alert('Could not save description: ' + error.message);
					return false; // Indicate failure
				} finally {
					acceptBtn.disabled = false;
					acceptBtn.innerHTML = originalBtnText;
				}
			}
			
			itemsToProcess.forEach(item => {
				const regenerateBtn = item.querySelector('.btn-regenerate');
				const acceptBtn = item.querySelector('.btn-accept');
				
				regenerateBtn.addEventListener('click', () => {
					generateDescriptionFor(item);
				});
				
				acceptBtn.addEventListener('click', async () => {
					const success = await saveDescriptionFor(item);
					if (success) {
						const statusBadge = item.querySelector('.status-badge');
						const descriptionContainer = item.querySelector('.description-container');
						
						// Update status badge
						statusBadge.className = 'badge bg-success status-badge';
						statusBadge.textContent = 'Saved';
						
						const textarea = descriptionContainer.querySelector('textarea');
						const buttonContainer = descriptionContainer.querySelector('.mt-2.text-end');
						
						textarea.readOnly = true;
						if (buttonContainer) {
							buttonContainer.classList.add('d-none');
						}
						
						// Move to the next item
						currentItemIndex++;
						processNextItem();
					}
				});
			});
			
			// Start the process
			if (itemsToProcess.length > 0) {
				processNextItem();
			} else {
				// MODIFIED: If there are no items to process, check if it's because they are all complete.
				const totalItems = document.querySelectorAll('#characters-list li[data-type], #places-list li[data-type]').length;
				if (totalItems > 0) {
					finishContainer.classList.remove('d-none');
				} else {
					// If there are no entities at all, we can still show the finish button.
					const finishText = document.querySelector('#finish-container p');
					if (finishText) {
						finishText.textContent = 'No entities were identified. You can now finish the story creation.';
						finishText.classList.remove('text-success');
					}
					finishContainer.classList.remove('d-none');
				}
			}
		});
	</script>
@endsection
