@extends('layouts.app')

@section('content')
	<div class="container py-4">
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
		@if(session('error'))
			<div class="alert alert-danger">
				{{ session('error') }}
			</div>
		@endif
		
		<form action="{{ route('stories.update', $story) }}" method="POST" style="padding-bottom: 100px;">
			@csrf
			@method('PUT')
			
			<div class="card mb-4">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h3>Editing Story: {{ $story->title }}</h3>
					<a href="{{ route('stories.index') }}" class="btn btn-sm btn-secondary">Back to List</a>
				</div>
				<div class="card-body">
					{{-- START MODIFICATION: Restructure top fields to include Level. --}}
					<div class="row">
						<div class="col-md-8">
							<div class="mb-3">
								<label for="title" class="form-label">Title</label>
								<input type="text" class="form-control" id="title" name="title" value="{{ old('title', $story->title) }}" required>
							</div>
						</div>
						<div class="col-md-4">
							<div class="mb-3">
								<label for="level" class="form-label">English Proficiency Level (CEFR)</label>
								<select class="form-select @error('level') is-invalid @enderror" id="level" name="level" required>
									<option value="" disabled {{ old('level', $story->level) ? '' : 'selected' }}>Select a level...</option>
									
									<optgroup label="A - Basic User">
										<option value="A1" {{ old('level', $story->level) == 'A1' ? 'selected' : '' }}>
											A1 - Beginner: Can understand and use familiar everyday expressions.
										</option>
										<option value="A2" {{ old('level', $story->level) == 'A2' ? 'selected' : '' }}>
											A2 - Elementary: Can understand sentences and frequently used expressions on familiar topics.
										</option>
									</optgroup>
									
									<optgroup label="B - Independent User">
										<option value="B1" {{ old('level', $story->level) == 'B1' ? 'selected' : '' }}>
											B1 - Intermediate: Can understand the main points of clear text on familiar matters.
										</option>
										<option value="B2" {{ old('level', $story->level) == 'B2' ? 'selected' : '' }}>
											B2 - Upper-Intermediate: Can understand the main ideas of complex text on both concrete and abstract topics.
										</option>
									</optgroup>
									
									<optgroup label="C - Proficient User">
										<option value="C1" {{ old('level', $story->level) == 'C1' ? 'selected' : '' }}>
											C1 - Advanced: Can understand a wide range of demanding, longer texts, and recognize implicit meaning.
										</option>
										<option value="C2" {{ old('level', $story->level) == 'C2' ? 'selected' : '' }}>
											C2 - Mastery: Can understand with ease virtually everything heard or read. Can express self fluently and precisely.
										</option>
									</optgroup>
								</select>
								@error('level')
								<div class="invalid-feedback">{{ $message }}</div>
								@enderror
							</div>
						</div>
					</div>
					{{-- END MODIFICATION --}}
					<div class="mb-3">
						<label for="short_description" class="form-label">Short Description</label>
						<textarea class="form-control" id="short_description" name="short_description" rows="3">{{ old('short_description', $story->short_description) }}</textarea>
					</div>
					<div class="d-flex gap-2">
						<a href="{{ route('stories.characters', $story) }}" class="btn btn-info">Manage Characters</a>
						<a href="{{ route('stories.places',  $story) }}" class="btn btn-info">Manage Places</a>
					</div>
				</div>
			</div>
			
			<div class="d-flex justify-content-between align-items-center mb-3">
				<h4>Pages</h4>
				<button type="button" id="add-page-btn" class="btn btn-primary">Add New Page</button>
			</div>
			
			<div id="pages-container">
				@foreach($story->pages as $index => $page)
					@include('story.partials.page-card', ['page' => $page, 'index' => $index, 'story' => $story])
				@endforeach
			</div>
			
			<div class="fixed-save-bar">
				<div class="container">
					<div class="text-end">
						<button type="submit" class="btn btn-success btn-lg">Save Story</button>
					</div>
				</div>
			</div>
		</form>
	</div>
	
	{{-- Include Modals --}}
	@include('story.partials.cropper-modal')
	@include('story.partials.history-modal')
	
	{{-- START MODIFICATION: Add AI Prompt Generator Modal with new textarea --}}
	<div class="modal fade" id="generatePromptModal" tabindex="-1" aria-labelledby="generatePromptModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="generatePromptModalLabel">Generate Image Prompt with AI</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div id="prompt-generator-form">
						<div class="mb-3">
							<label for="prompt-instructions" class="form-label">Additional Instructions (Optional)</label>
							<textarea class="form-control" id="prompt-instructions" rows="3" placeholder="e.g., focus on the character's sad expression, make the lighting dramatic"></textarea>
						</div>
						<div class="mb-3">
							<label for="prompt-model" class="form-label">AI Model</label>
							<select class="form-select" id="prompt-model">
								@if(empty($models))
									<option value="" disabled>Could not load models.</option>
								@else
									@foreach($models as $model)
										<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
									@endforeach
								@endif
							</select>
						</div>
						{{-- START NEW MODIFICATION: Add a textarea for the full, editable prompt. --}}
						<div class="mb-3">
							<label for="full-prompt-text" class="form-label">Full Prompt Sent to AI (Live Preview, Editable)</label>
							<textarea class="form-control" id="full-prompt-text" rows="12" style="font-family: monospace; font-size: 0.8rem;"></textarea>
						</div>
						{{-- END NEW MODIFICATION --}}
						<button type="button" class="btn btn-primary" id="write-prompt-btn">
							<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							Write with AI
						</button>
					</div>
					<div id="prompt-result-area" class="d-none mt-4">
						<div class="mb-3">
							<label for="generated-prompt-text" class="form-label">Generated Prompt (you can edit this)</label>
							<textarea class="form-control" id="generated-prompt-text" rows="5"></textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-success d-none" id="update-prompt-btn">Update Prompt</button>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
	
	{{-- START MODIFICATION: Include the new rewrite text modal. --}}
	@include('story.partials.modals.rewrite-text-modal', ['models' => $models])
	{{-- END MODIFICATION --}}
	
	@include('story.partials.modals.draw-with-ai-modal', ['imageModels' => $imageModels])
	
	@include('story.partials.modals.image-detail-modal')
	
	{{-- START MODIFICATION: Add the new dictionary modal. --}}
	@include('story.partials.modals.dictionary-modal', ['models' => $models])
	{{-- END MODIFICATION --}}
	
	{{-- START MODIFICATION: Include the new Draw with AI v2 modal --}}
	@include('story.partials.modals.draw-with-ai-v2-modal')
	{{-- END MODIFICATION --}}
	
	{{-- Template for new pages --}}
	<template id="page-template">
		@include('story.partials.page-card', ['page' => null, 'index' => '__INDEX__', 'story' => $story])
	</template>
	
	{{-- START MODIFICATION: Add template for new dictionary rows. --}}
	<template id="dictionary-entry-template">
		@include('story.partials.dictionary-entry-row-page', ['index' => '__INDEX__', 'd_index' => '__D_INDEX__', 'entry' => null])
	</template>
	{{-- END MODIFICATION --}}
@endsection

@section('styles')
	<link rel="stylesheet" href="{{asset('vendor/cropperjs/1.6.1/cropper.min.css')}}"/>
	<style>
      .history-image-card { cursor: pointer; border: 2px solid transparent; }
      .history-image-card.selected { border-color: var(--bs-primary); }
      .history-image-card img { width: 100%; height: 150px; object-fit: cover; }
      .page-card .form-check-group { max-height: 150px; overflow-y: auto; }

      .fixed-save-bar {
          position: fixed;
          bottom: 0;
          left: 0;
          width: 100%;
          padding: 1rem;
          background-color: var(--bs-body-bg); /* Respects light/dark theme */
          border-top: 1px solid var(--bs-border-color);
          box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
          z-index: 1030; /* Ensures it's above most content */
      }
	</style>
@endsection

@section('scripts')
	<script src="{{ asset('js/queue.js') }}"></script>
	<script src="{{asset('vendor/cropperjs/1.6.1/cropper.min.js')}}"></script>
	{{-- START MODIFICATION: Pass prompt templates and story level to JS --}}
	<script>
		window.promptTemplates = @json($promptTemplates ?? []);
		window.storyLevel = @json($story->level ?? 'B1');
	</script>
	{{-- END MODIFICATION --}}
	<script src="{{ asset('js/story-editor.js') }}"></script>
@endsection
