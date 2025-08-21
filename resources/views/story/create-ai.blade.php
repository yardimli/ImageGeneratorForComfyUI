@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<h1>Create a Story with AI</h1>
		<p class="text-muted">Provide instructions for the story, choose the number of pages and an AI model, and let the magic happen.</p>
		
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
				<form action="{{ route('stories.store-ai') }}" method="POST" id="ai-story-form">
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
							<div class="form-text">Select a pre-written summary to prepend to your instructions below.</div>
						</div>
					@endif
					
					<div class="mb-3">
						<label for="instructions" class="form-label">Story Instructions</label>
						<textarea class="form-control @error('instructions') is-invalid @enderror" id="instructions" name="instructions" rows="6" placeholder="e.g., A story about a brave knight who befriends a shy dragon to save a kingdom from an evil sorcerer. Include a wise old owl as a character." required>{{ old('instructions') }}</textarea>
						<div class="form-text">Describe the plot, characters, and setting you want in your story. Be as descriptive as you like.</div>
						@error('instructions')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					
					<div class="row">
						<div class="col-md-4 mb-3">
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
						<div class="col-md-4 mb-3">
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
						<div class="col-md-4 mb-3">
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
				
				{{-- New progress bar element --}}
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
						// --- STEP 1: Create the core story ---
						updateProgress(5, 'Generating story core...');
						const coreFormData = new FormData(form);
						const coreResponse = await fetch("{{ route('stories.store-ai') }}", {
							method: 'POST',
							body: coreFormData,
							headers: { 'Accept': 'application/json' },
						});
						
						const coreData = await coreResponse.json();
						if (!coreResponse.ok) {
							throw new Error(coreData.message || 'Failed to create the story structure.');
						}
						
						// --- STEP 2: Sequentially generate descriptions ---
						const { story_id, characters_to_process, places_to_process } = coreData;
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
						const progressStep = 95 / totalItems; // Remaining 95% of progress
						
						for (let i = 0; i < totalItems; i++) {
							const item = itemsToProcess[i];
							const currentProgress = 5 + ((i) * progressStep);
							updateProgress(currentProgress, `Generating description for ${item.type}: ${item.name} (${i + 1}/${totalItems})`);
							
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
							
							if (!descResponse.ok) {
								const errorData = await descResponse.json();
								throw new Error(`Failed to generate description for ${item.type} '${item.name}'. ${errorData.message || ''}`);
							}
						}
						
						// --- STEP 3: Finalize and redirect ---
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
			
			const instructionsTextarea = document.getElementById('instructions');
			
			const summarySelect = document.getElementById('summary_file');
			if (summarySelect && instructionsTextarea) {
				const summaries = @json($summaries ?? []);
				const summaryMap = new Map(summaries.map(s => [s.filename, s.content]));
				
				summarySelect.addEventListener('change', function () {
					const selectedFilename = this.value;
					if (selectedFilename && summaryMap.has(selectedFilename)) {
						const summaryContent = summaryMap.get(selectedFilename);
						const currentInstructions = instructionsTextarea.value;
						
						instructionsTextarea.value = summaryContent + "\n\n---\n\n" + currentInstructions;
						this.value = '';
					}
				});
			}
			
			const levelSelect = document.getElementById('level');
			if (levelSelect && instructionsTextarea) {
				levelSelect.addEventListener('change', function () {
					const selectedLevel = this.value;
					const selectedOption = this.options[this.selectedIndex];
					if (selectedLevel) {
						const textToAppend = (instructionsTextarea.value.length > 0 ? '\n\n' : '') + `CEFR Level: ${selectedLevel} - ${selectedOption.text.trim()}`;
						instructionsTextarea.value += textToAppend;
						instructionsTextarea.scrollTop = instructionsTextarea.scrollHeight;
					}
				});
			}
			
		});
	</script>
@endsection
