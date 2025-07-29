@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
		{{-- START MODIFICATION: Added error alert for when AI models fail to load --}}
		@if(session('error'))
			<div class="alert alert-danger">
				{{ session('error') }}
			</div>
		@endif
		{{-- END MODIFICATION --}}
		
		{{-- START MODIFICATION: Added inline style to create padding at the bottom, preventing the fixed save bar from overlapping content. --}}
		<form action="{{ route('stories.update', $story) }}" method="POST" style="padding-bottom: 100px;">
			{{-- END MODIFICATION --}}
			@csrf
			@method('PUT')
			
			<div class="card mb-4">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h3>Editing Story: {{ $story->title }}</h3>
					<a href="{{ route('stories.index') }}" class="btn btn-sm btn-secondary">Back to List</a>
				</div>
				<div class="card-body">
					<div class="mb-3">
						<label for="title" class="form-label">Title</label>
						<input type="text" class="form-control" id="title" name="title" value="{{ old('title', $story->title) }}" required>
					</div>
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
			
			{{-- START MODIFICATION: Replaced the old save button div with a new structure for the fixed bar. --}}
			<div class="fixed-save-bar">
				<div class="container">
					<div class="text-end">
						<button type="submit" class="btn btn-success btn-lg">Save Story</button>
					</div>
				</div>
			</div>
			{{-- END MODIFICATION --}}
		</form>
	</div>
	
	{{-- Include Modals --}}
	@include('story.partials.cropper-modal')
	@include('story.partials.history-modal')
	
	{{-- START MODIFICATION: Add AI Prompt Generator Modal --}}
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
	
	{{-- START NEW MODIFICATION: Add "Draw with AI" Modal --}}
	<div class="modal fade" id="drawWithAiModal" tabindex="-1" aria-labelledby="drawWithAiModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="drawWithAiModalLabel">Draw with AI</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<form id="draw-with-ai-form">
						<input type="hidden" id="draw-story-page-id" value="">
						<div class="mb-3">
							<label class="form-label fw-bold">Image Prompt:</label>
							<p class="form-control-plaintext p-2 bg-body-secondary rounded" id="draw-image-prompt-text"></p>
						</div>
						<div class="row mb-3">
							<div class="col-md-6">
								<label for="draw-model" class="form-label">AI Model</label>
								<select class="form-select" id="draw-model">
									@foreach($imageModels as $model)
										<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-md-6">
								<label for="draw-aspect-ratio" class="form-label">Aspect Ratio</label>
								<select class="form-select" id="draw-aspect-ratio">
									<optgroup label="1MP">
										<option value="1:1-1024" selected>1:1 (1024 x 1024)</option>
										<option value="3:2-1024">3:2 (1216 x 832) Landscape</option>
										<option value="4:3-1024">4:3 (1152 x 896) Landscape</option>
										<option value="16:9-1024">16:9 (1344 x 768) Landscape</option>
										<option value="21:9-1024">21:9 (1536 x 640) Landscape</option>
										<option value="2:3-1024">2:3 (832 x 1216) Portrait</option>
										<option value="3:4-1024">3:4 (896 x 1152) Portrait</option>
										<option value="9:16-1024">9:16 (768 x 1344) Portrait</option>
										<option value="9:21-1024">9:21 (640 x 1536) Portrait</option>
									</optgroup>
									<optgroup label="2MP">
										<option value="1:1-1408">1:1 (1408 x 1408)</option>
										<option value="3:2-1408">3:2 (1728 x 1152) Landscape</option>
										<option value="4:3-1408">4:3 (1664 x 1216) Landscape</option>
										<option value="16:9-1408">16:9 (1920 x 1088) Landscape</option>
										<option value="21:9-1408">21:9 (2176 x 960) Landscape</option>
										<option value="2:3-1408">2:3 (1152 x 1728) Portrait</option>
										<option value="3:4-1408">3:4 (1216 x 1664) Portrait</option>
										<option value="9:16-1408">9:16 (1088 x 1920) Portrait</option>
										<option value="9:21-1408">9:21 (960 x 2176) Portrait</option>
									</optgroup>
								</select>
							</div>
						</div>
						<div class="row mb-3">
							<div class="col-md-4">
								<label for="draw-width" class="form-label">Width</label>
								<input type="number" class="form-control" id="draw-width" value="1024">
							</div>
							<div class="col-md-4">
								<label for="draw-height" class="form-label">Height</label>
								<input type="number" class="form-control" id="draw-height" value="1024">
							</div>
							<div class="col-md-4 d-flex align-items-center">
								<div class="form-check mt-3">
									<input type="checkbox" class="form-check-input" id="draw-upload-to-s3" value="1" checked>
									<label class="form-check-label" for="draw-upload-to-s3">Upload to S3</label>
								</div>
							</div>
						</div>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="generate-image-btn">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						Generate Image Only
					</button>
				</div>
			</div>
		</div>
	</div>
	{{-- END NEW MODIFICATION --}}
	
	{{-- START MODIFICATION: Add Image Detail Modal for viewing and upscaling --}}
	<div class="modal fade" id="imageDetailModal" tabindex="-1" aria-labelledby="imageDetailModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="imageDetailModalLabel">Image Detail</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body text-center">
					<img id="modalDetailImage" src="" style="max-width: 100%; height: auto;" alt="Story page image">
				</div>
				<div class="modal-footer justify-content-between">
					<div id="upscale-status-container" class="text-muted">
						{{-- Status text will go here --}}
					</div>
					<div>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						<span id="upscale-button-container">
                        {{-- Upscale button will be injected here by JS --}}
                    </span>
					</div>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
	
	{{-- Template for new pages --}}
	<template id="page-template">
		@include('story.partials.page-card', ['page' => null, 'index' => '__INDEX__', 'story' => $story])
	</template>
@endsection

@section('styles')
	<link rel="stylesheet" href="{{asset('vendor/cropperjs/1.6.1/cropper.min.css')}}"/>
	<style>
      .history-image-card { cursor: pointer; border: 2px solid transparent; }
      .history-image-card.selected { border-color: var(--bs-primary); }
      .history-image-card img { width: 100%; height: 150px; object-fit: cover; }
      .page-card .form-check-group { max-height: 150px; overflow-y: auto; }

      /* START MODIFICATION: Added styles for the fixed save bar. */
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
      /* END MODIFICATION */
	</style>
@endsection

@section('scripts')
	<script src="{{asset('vendor/cropperjs/1.6.1/cropper.min.js')}}"></script>
	<script src="{{ asset('js/story-editor.js') }}"></script>
@endsection
