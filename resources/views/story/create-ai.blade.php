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
					
					{{-- START MODIFICATION: Add a dropdown for story summaries. --}}
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
					{{-- END MODIFICATION --}}
					
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
			
			// START MODIFICATION: Handle prepending summary text to the instructions textarea.
			const summarySelect = document.getElementById('summary_file');
			const instructionsTextarea = document.getElementById('instructions');
			
			if (summarySelect && instructionsTextarea) {
				// Safely embed the summaries data from PHP into a JavaScript variable.
				const summaries = @json($summaries ?? []);
				// Create a Map for efficient lookup of summary content by filename.
				const summaryMap = new Map(summaries.map(s => [s.filename, s.content]));
				
				summarySelect.addEventListener('change', function () {
					const selectedFilename = this.value;
					if (selectedFilename && summaryMap.has(selectedFilename)) {
						const summaryContent = summaryMap.get(selectedFilename);
						const currentInstructions = instructionsTextarea.value;
						
						// Prepend the summary content, separated by a line, to the existing instructions.
						instructionsTextarea.value = summaryContent + "\n\n---\n\n" + currentInstructions;
						
						// Reset the dropdown. This allows the user to select the same summary again
						// if they edit the textarea and want to re-prepend the text.
						this.value = '';
					}
				});
			}
			// END MODIFICATION
		});
	</script>
@endsection
