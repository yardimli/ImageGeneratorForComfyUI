@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center">
			<h1>Create a Story with AI - Step 1: Instructions</h1>
			<span class="badge bg-primary fs-6">Step 1 of 3</span>
		</div>
		<p class="text-muted">Use the prompts below to generate your story. You can edit them to customize the entire generation process. Start by describing your story in the first prompt.</p>
		
		@include('story.partials.alerts')
		
		<div class="card">
			<div class="card-body">
				{{-- MODIFIED: Form action will be changed by JS. Initial action is for AJAX generation. --}}
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
							<div class="form-text">Select a pre-written summary to prepend to the instructions in the first prompt below.</div>
						</div>
					@endif
					
					<div class="row">
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
					
					<p class="text-muted">The prompt below is used to generate the story. The other prompts for identifying characters/places and describing them can be edited in the next steps.</p>
					
					<!-- Prompt 1: Story Content Generation -->
					<div class="mb-3">
						<label for="prompt_content_generation" class="form-label">1. Story Instructions & Content Generation Prompt</label>
						<textarea class="form-control" id="prompt_content_generation" name="prompt_content_generation" rows="10">{{ old('prompt_content_generation', str_replace('{instructions}', 'Generate a 16 page story for the given CEFR level.', $prompts['content']->system_prompt)) }}</textarea>
						<small class="form-text text-muted">{{ $prompts['content']->description }}</small>
					</div>
					
					{{-- NEW: This section will be populated by AJAX after generation --}}
					<div id="generation-result" class="d-none mt-4">
						<hr>
						<h3 class="mb-3">Review & Edit Generated Content</h3>
						<p class="text-muted">The story content has been generated below. You can edit the text in these fields. When you are satisfied, click "Save & Continue" to proceed to the next step.</p>
						
						<div class="mb-3">
							<label for="generated_title" class="form-label">Title</label>
							<input type="text" class="form-control" id="generated_title" name="title">
						</div>
						
						<div class="mb-3">
							<label for="generated_description" class="form-label">Short Description</label>
							<textarea class="form-control" id="generated_description" name="short_description" rows="3"></textarea>
						</div>
						
						<div id="generated_pages_container">
							{{-- Page textareas will be added here by JS --}}
						</div>
					</div>
					
					{{-- MODIFIED: Button text and behavior will be managed by JS --}}
					<div class="d-flex justify-content-end align-items-center gap-3 mt-3">
						<a href="{{ route('stories.index') }}" class="btn btn-secondary">Cancel</a>
						<button type="submit" class="btn btn-primary btn-lg" id="generate-button">
							Generate Story Content
						</button>
						{{-- NEW: This button is initially hidden --}}
						<button type="submit" class="btn btn-success btn-lg d-none" id="next-step-button">
							Save & Continue to Step 2
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
			const generateButton = document.getElementById('generate-button');
			const nextStepButton = document.getElementById('next-step-button');
			const resultContainer = document.getElementById('generation-result');
			const pagesContainer = document.getElementById('generated_pages_container');
			const originalButtonText = generateButton.innerHTML;
			
			form.addEventListener('submit', async function (e) {
				e.preventDefault();
				
				// If the next step button is visible, it means we are submitting the final form.
				if (!nextStepButton.classList.contains('d-none')) {
					nextStepButton.disabled = true;
					nextStepButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...`;
					form.submit(); // Regular form submission
					return;
				}
				
				generateButton.disabled = true;
				generateButton.innerHTML = `
					<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
					Generating...
				`;
				
				// Clear previous results and hide container
				resultContainer.classList.add('d-none');
				pagesContainer.innerHTML = '';
				
				try {
					const formData = new FormData(form);
					const response = await fetch(form.action, {
						method: 'POST',
						body: formData,
						headers: {
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
						}
					});
					
					const data = await response.json();
					
					if (!response.ok) {
						let errorMessage = data.message || 'An unknown error occurred.';
						if (data.errors) {
							errorMessage += '\n';
							for (const key in data.errors) {
								errorMessage += `\n- ${data.errors[key].join(', ')}`;
							}
						}
						alert(errorMessage);
						throw new Error(errorMessage);
					}
					
					// Populate the form with the results
					document.getElementById('generated_title').value = data.title;
					document.getElementById('generated_description').value = data.description;
					
					data.pages.forEach((page, index) => {
						const pageNumber = index + 1;
						const pageHtml = `
							<div class="mb-3">
								<label for="page_${pageNumber}" class="form-label">Page ${pageNumber}</label>
								<textarea class="form-control" id="page_${pageNumber}" name="pages[]" rows="5">${page.content}</textarea>
							</div>
						`;
						pagesContainer.insertAdjacentHTML('beforeend', pageHtml);
					});
					
					// Show the results and the next step button
					resultContainer.classList.remove('d-none');
					nextStepButton.classList.remove('d-none');
					
					// Change form action for the next submission
					form.action = "{{ route('stories.ai-store.content') }}";
					
					// Change the generate button to a "Regenerate" button
					generateButton.innerHTML = 'Regenerate Content';
					generateButton.classList.remove('btn-primary');
					generateButton.classList.add('btn-secondary');
					
				} catch (error) {
					console.error('Generation failed:', error);
				} finally {
					generateButton.disabled = false;
					// If it's a regenerate button, keep the text, otherwise reset it.
					if (nextStepButton.classList.contains('d-none')) {
						generateButton.innerHTML = originalButtonText;
					}
				}
			});
			
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
