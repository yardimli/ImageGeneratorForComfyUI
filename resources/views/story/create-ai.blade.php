@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<h1>Create a Story with AI</h1>
		{{-- MODIFIED: Help text updated for new layout --}}
		<p class="text-muted">Use the prompts below to generate your story. You can edit them to customize the generation process. Start by describing your story in the first prompt.</p>
		
		<div id="error-container" class="alert alert-danger d-none"></div>
		
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
				{{-- MODIFIED: Form action points to the new first step --}}
				<form action="{{ route('stories.ai-generate.content') }}" method="POST" id="ai-story-form">
					@csrf
					
					@if(!empty($summaries))
						<div class="mb-3">
							<label for="summary_file" class="form-label">Story Summary (Optional)</label>
							<select class="form-select" id="summary_file">
								<option value="">-- Prepend a summary --</option>
								@foreach($summaries as $summary)
									<option value="{{ $summary['filename'] }}">{{ $summary['name'] }}</option>
								@endforeach
							</select>
							{{-- MODIFIED: Help text updated --}}
							<div class="form-text">Select a pre-written summary to prepend to the instructions in the first prompt below.</div>
						</div>
					@endif
					
					{{-- REMOVED: The main 'instructions' textarea has been removed. The first prompt textarea below now serves as the main input. --}}
					
					<div class="row">
						{{-- REMOVED: Number of Pages dropdown is no longer needed --}}
						{{-- MODIFIED: Changed from col-md-4 to col-md-6 for better layout --}}
						<div class="col-md-6 mb-3">
							<label for="level" class="form-label">English Proficiency Level (CEFR)</label>
							<select class="form-select @error('level') is-invalid @enderror" id="level" name="level" required>
								<option value="" disabled {{ old('level') ? '' : 'selected' }}>Select a level...</option>
								
								<optgroup label="A - Basic User">
									<option value="A1" {{ old('level') == 'A1' ? 'selected' : '' }}>
										A1 - Beginner: Can understand and use familiar everyday expressions.
									</option>
									<option value="A2" {{ old('level') == 'A2' ? 'selected' : '' }}>
										A2 - Elementary: Can understand sentences and frequently used expressions on familiar topics.
									</option>
								</optgroup>
								
								<optgroup label="B - Independent User">
									<option value="B1" {{ old('level') == 'B1' ? 'selected' : '' }}>
										B1 - Intermediate: Can understand the main points of clear text on familiar matters.
									</option>
									<option value="B2" {{ old('level') == 'B2' ? 'selected' : '' }}>
										B2 - Upper-Intermediate: Can understand the main ideas of complex text on both concrete and abstract topics.
									</option>
								</optgroup>
								
								<optgroup label="C - Proficient User">
									<option value="C1" {{ old('level') == 'C1' ? 'selected' : '' }}>
										C1 - Advanced: Can understand a wide range of demanding, longer texts, and recognize implicit meaning.
									</option>
									<option value="C2" {{ old('level') == 'C2' ? 'selected' : '' }}>
										C2 - Mastery: Can understand with ease virtually everything heard or read. Can express self fluently and precisely.
									</option>
								</optgroup>
							</select>
							@error('level')
							<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>
						{{-- MODIFIED: Changed from col-md-4 to col-md-6 for better layout --}}
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
					
					{{-- MODIFIED: Accordion removed, prompts are now always visible. --}}
					<p class="text-muted">The prompts below are used in sequence to generate the story. You can edit them to customize the generation process.</p>
					
					<!-- Prompt 1: Story Content Generation -->
					<div class="mb-3">
						{{-- MODIFIED: Label changed to reflect its new role. --}}
						<label for="prompt_content_generation" class="form-label">1. Story Instructions & Content Generation Prompt</label>
						{{-- MODIFIED: Textarea now contains a default instruction for the user to edit, replacing the {instructions} placeholder. --}}
						<textarea class="form-control" id="prompt_content_generation" name="prompt_content_generation" rows="10">{{ str_replace('{instructions}', 'Generate a 16 page story for the given CEFR level.', $prompts['content']->system_prompt) }}</textarea>
						<small class="form-text text-muted">{{ $prompts['content']->description }}</small>
					</div>
					
					<!-- Prompt 2: Entity Identification -->
					<div class="mb-3">
						<label for="prompt_entity_generation" class="form-label">{{ $prompts['entities']->label }}</label>
						<textarea class="form-control" id="prompt_entity_generation" name="prompt_entity_generation" rows="10">{{ $prompts['entities']->system_prompt }}</textarea>
						<small class="form-text text-muted">{{ $prompts['entities']->description }}</small>
					</div>
					
					<!-- Prompt 3: Character Description -->
					<div class="mb-3">
						<label for="prompt_character_description" class="form-label">{{ $prompts['character']->label }}</label>
						<textarea class="form-control" id="prompt_character_description" name="prompt_character_description" rows="10">{{ $prompts['character']->system_prompt }}</textarea>
						<small class="form-text text-muted">{{ $prompts['character']->description }}</small>
					</div>
					
					<!-- Prompt 4: Place Description -->
					<div class="mb-3">
						<label for="prompt_place_description" class="form-label">{{ $prompts['place']->label }}</label>
						<textarea class="form-control" id="prompt_place_description" name="prompt_place_description" rows="10">{{ $prompts['place']->system_prompt }}</textarea>
						<small class="form-text text-muted">{{ $prompts['place']->description }}</small>
					</div>
					
					<div class="d-flex justify-content-end align-items-center gap-3">
						<a href="{{ route('stories.index') }}" class="btn btn-secondary">Cancel</a>
						<button type="submit" class="btn btn-primary btn-lg" id="generate-btn">
							<span id="btn-text">Generate Story</span>
							<span id="btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						</button>
					</div>
				</form>
				
				<div id="progress-container" class="mt-4 d-none">
					<p id="progress-text" class="text-center mb-2 fw-bold"></p>
					<div class="progress" style="height: 25px;">
						<div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
					</div>
				</div>
			
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
			const progressContainer = document.getElementById('progress-container');
			const progressBar = document.getElementById('progress-bar');
			const progressText = document.getElementById('progress-text');
			const errorContainer = document.getElementById('error-container');
			
			// --- Form submission handler for AI generation ---
			if (form) {
				form.addEventListener('submit', async function (e) {
					e.preventDefault();
					
					// --- UI updates for starting generation ---
					generateBtn.disabled = true;
					btnText.textContent = 'Generating...';
					btnSpinner.classList.remove('d-none');
					errorContainer.classList.add('d-none');
					progressContainer.classList.remove('d-none');
					updateProgress(0, 'Initializing...');
					
					try {
						// --- STEP 1: Generate Story Content ---
						updateProgress(5, 'Generating story content...');
						const contentFormData = new FormData(form);
						const contentResponse = await fetch("{{ route('stories.ai-generate.content') }}", {
							method: 'POST',
							body: contentFormData,
							headers: { 'Accept': 'application/json' },
						});
						
						const contentData = await contentResponse.json();
						if (!contentResponse.ok) {
							throw new Error(contentData.message || 'Failed to generate story content.');
						}
						const { story_id } = contentData;
						
						// --- STEP 2: Generate Story Entities (Characters & Places) ---
						updateProgress(15, 'Identifying characters and places...');
						const entityFormData = new FormData();
						entityFormData.append('story_id', story_id);
						entityFormData.append('_token', form.querySelector('input[name="_token"]').value);
						
						const entityResponse = await fetch("{{ route('stories.ai-generate.entities') }}", {
							method: 'POST',
							body: entityFormData,
							headers: { 'Accept': 'application/json' },
						});
						
						const entityData = await entityResponse.json();
						if (!entityResponse.ok) {
							throw new Error(entityData.message || 'Failed to identify characters and places.');
						}
						
						// --- STEP 3: Sequentially generate descriptions with retries ---
						const { characters_to_process, places_to_process } = entityData;
						const itemsToProcess = [
							...characters_to_process.map(name => ({ type: 'character', name })),
							...places_to_process.map(name => ({ type: 'place', name })),
						];
						
						if (itemsToProcess.length === 0) {
							updateProgress(100, 'Generation complete!');
							window.location.href = `/stories/${story_id}/edit`;
							return;
						}
						
						const totalItems = itemsToProcess.length;
						const progressStart = 20; // Progress for this step starts at 20%
						const progressRange = 80; // This step takes up the remaining 80% of the bar
						const progressStep = totalItems > 0 ? progressRange / totalItems : 0;
						const maxRetries = 3;
						
						for (let i = 0; i < totalItems; i++) {
							const item = itemsToProcess[i];
							let success = false;
							
							// --- Retry loop for each item ---
							for (let attempt = 1; attempt <= maxRetries; attempt++) {
								const currentProgress = progressStart + (i * progressStep);
								let progressMessage = `Generating description for ${item.type}: ${item.name} (${i + 1}/${totalItems})`;
								if (attempt > 1) {
									progressMessage += ` - Attempt ${attempt}/${maxRetries}`;
								}
								updateProgress(currentProgress, progressMessage);
								
								try {
									const descFormData = new FormData();
									descFormData.append('story_id', story_id);
									descFormData.append('type', item.type);
									descFormData.append('name', item.name);
									descFormData.append('_token', form.querySelector('input[name="_token"]').value);
									
									const descResponse = await fetch("{{ route('stories.ai-generate.description') }}", {
										method: 'POST',
										body: descFormData,
										headers: { 'Accept': 'application/json' },
									});
									
									if (descResponse.ok) {
										success = true;
										break; // Successful, exit the retry loop
									}
									
									const errorData = await descResponse.json();
									console.error(`Attempt ${attempt} failed for ${item.type} '${item.name}':`, errorData.message || `Server returned status ${descResponse.status}`);
									
								} catch (networkError) {
									console.error(`Attempt ${attempt} failed for ${item.type} '${item.name}' with a network error:`, networkError);
								}
							}
							
							if (!success) {
								console.warn(`Skipping ${item.type} '${item.name}' after ${maxRetries} failed attempts.`);
							}
						}
						
						// --- Finalize and redirect ---
						updateProgress(100, 'Generation complete! Redirecting...');
						window.location.href = `/stories/${story_id}/edit`;
						
					} catch (error) {
						console.error('Story generation failed:', error);
						showError(error.message);
						resetFormState();
					}
				});
			}
			
			function updateProgress(percent, text) {
				const p = Math.round(percent);
				progressBar.style.width = p + '%';
				progressBar.setAttribute('aria-valuenow', p);
				progressBar.textContent = p + '%';
				progressText.textContent = text;
			}
			
			function showError(message) {
				errorContainer.textContent = `An error occurred: ${message}`;
				errorContainer.classList.remove('d-none');
			}
			
			function resetFormState() {
				generateBtn.disabled = false;
				btnText.textContent = 'Generate Story';
				btnSpinner.classList.add('d-none');
				progressContainer.classList.add('d-none');
			}
			
			// --- Helper scripts for form usability ---
			const modelSelect = document.getElementById('model');
			const modelStorageKey = 'storyCreateAi_model';
			
			const savedModel = localStorage.getItem(modelStorageKey);
			if (savedModel && modelSelect && !modelSelect.value) {
				modelSelect.value = savedModel;
			}
			
			if (modelSelect) {
				modelSelect.addEventListener('change', function () {
					localStorage.setItem(modelStorageKey, this.value);
				});
			}
			
			// MODIFIED: Target the first prompt textarea instead of the removed 'instructions' textarea.
			const mainPromptTextarea = document.getElementById('prompt_content_generation');
			
			const summarySelect = document.getElementById('summary_file');
			if (summarySelect && mainPromptTextarea) {
				const summaries = @json($summaries ?? []);
				const summaryMap = new Map(summaries.map(s => [s.filename, s.content]));
				
				summarySelect.addEventListener('change', function () {
					const selectedFilename = this.value;
					if (selectedFilename && summaryMap.has(selectedFilename)) {
						const summaryContent = summaryMap.get(selectedFilename);
						const currentInstructions = mainPromptTextarea.value;
						
						// MODIFIED: Prepend summary to the main prompt textarea.
						mainPromptTextarea.value = summaryContent + "\n\n---\n\n" + currentInstructions;
						this.value = ''; // Reset dropdown
					}
				});
			}
			
			const levelSelect = document.getElementById('level');
			if (levelSelect && mainPromptTextarea) {
				levelSelect.addEventListener('change', function () {
					const selectedLevel = this.value;
					const selectedOption = this.options[this.selectedIndex];
					if (selectedLevel) {
						// MODIFIED: Add detailed instructions based on the selected CEFR level.
						const levelInstructions = {
							'A1': 'Each page should have 1-2 simple sentences. Use very basic vocabulary.',
							'A2': 'Each page should have 2-3 sentences. Use common vocabulary and basic sentence structures.',
							'B1': 'Each page should have 3-5 sentences. Use a mix of simple and compound sentences and intermediate vocabulary.',
							'B2': 'Each page should have 4-6 sentences. Include complex sentences and a wider range of vocabulary. Introduce some idiomatic expressions.',
							'C1': 'Each page should be a full paragraph with complex sentence structures and nuanced vocabulary. The tone can be more sophisticated.',
							'C2': 'Each page should be a well-developed paragraph, using sophisticated language and literary devices where appropriate. Assume a near-native level of understanding.'
						};
						
						const levelDescription = `CEFR Level: ${selectedLevel} - ${selectedOption.text.trim()}`;
						const specificInstruction = levelInstructions[selectedLevel] || '';
						const textToPrepend = `${levelDescription}\n${specificInstruction}\n\n`;
						
						const existingValue = mainPromptTextarea.value;
						// Regex to find and replace a previously added CEFR instruction block.
						// This handles both the old format (one line) and the new format (two lines).
						const levelRegex = /^CEFR Level: (A1|A2|B1|B2|C1|C2) - .+\n(?:.+\n)?\n/m;
						
						if (levelRegex.test(existingValue)) {
							mainPromptTextarea.value = existingValue.replace(levelRegex, textToPrepend);
						} else {
							mainPromptTextarea.value = textToPrepend + existingValue;
						}
						
						mainPromptTextarea.scrollTop = 0; // Scroll to top to show the newly added text
					}
				});
			}
			
		});
	</script>
@endsection
