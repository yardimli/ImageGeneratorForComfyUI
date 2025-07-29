@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4" style="padding-bottom: 100px;">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h1>Places for "{{ $story->title }}"</h1>
				<a href="{{ route('stories.edit', $story) }}">← Back to Story</a>
			</div>
			<button type="button" id="add-place-btn" class="btn btn-primary">Add New Place</button>
		</div>
		
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
		
		<form action="{{ route('stories.places.update', $story) }}" method="POST">
			@csrf
			<div id="places-container">
				@foreach($story->places as $index => $place)
					<div class="card mb-3 place-card" data-id="{{ $place->id }}">
						<div class="card-header d-flex justify-content-between align-items-center">
							Place
							<button type="button" class="btn-close remove-place-btn"></button>
						</div>
						<div class="card-body">
							<input type="hidden" name="places[{{ $index }}][id]" value="{{ $place->id }}">
							<div class="row">
								<div class="col-md-8">
									<div class="mb-3">
										<label class="form-label">Name</label>
										<input type="text" name="places[{{ $index }}][name]" class="form-control" value="{{ $place->name }}" required>
									</div>
									<div class="mb-3">
										<label class="form-label">Description</label>
										<textarea name="places[{{ $index }}][description]" class="form-control asset-description" rows="5">{{ $place->description }}</textarea>
									</div>
									{{-- START MODIFICATION: Add Image Prompt textarea and AI buttons --}}
									<div class="mb-3">
										<label class="form-label">Image Prompt</label>
										<textarea name="places[{{ $index }}][image_prompt]" class="form-control image-prompt-textarea" rows="3" data-initial-value="{{ e($place->image_prompt ?? '') }}">{{ $place->image_prompt ?? '' }}</textarea>
										<button type="button" class="btn btn-sm btn-outline-info mt-2 generate-prompt-btn" data-bs-toggle="modal" data-bs-target="#generatePromptModal">
											<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic me-1" viewBox="0 0 16 16"><path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 3.5a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8 4.707 7.293 4Zm-.646 10.646a.5.5 0 0 0 .708 0L8 13.914l-1.06-1.06a.5.5 0 0 0-.854.353v.534a.5.5 0 0 0 .146.354l.646.646ZM.5 10.828a.5.5 0 0 0 1 0V9.157a.5.5 0 0 0-1 0v1.671Zm1.829-4.5A.5.5 0 0 0 2 6.586l.707.707a.5.5 0 0 0 .707 0L4 6.586a.5.5 0 0 0 0-.707L2.707 4.586a.5.5 0 0 0-.707 0ZM10.828.5a.5.5 0 0 0 0 1h1.671a.5.5 0 0 0 0-1h-1.671Z"/><path d="M3.5 13.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Zm9.025-5.99a1.5 1.5 0 0 0-1.025.433L6.932 12.47a1.5 1.5 0 0 0-1.025.433L3.025 15.8a.5.5 0 0 0 .854.353L5.5 14.5l.646.646a.5.5 0 0 0 .708 0L8.5 13.5l.646.646a.5.5 0 0 0 .708 0L11.5 12.5l.646.646a.5.5 0 0 0 .708 0L14.5 11.5l.646.646a.5.5 0 0 0 .854-.353l-2.882-2.882a1.5 1.5 0 0 0-1.025-.433Z"/></svg>
											Fill with AI
										</button>
										<button type="button" class="btn btn-sm btn-outline-success mt-2 draw-with-ai-btn" data-asset-id="{{ $place->id ?? '' }}">
											<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-palette-fill me-1" viewBox="0 0 16 16"><path d="M12.433 10.07C14.133 10.585 16 11.15 16 8a8 8 0 1 0-15.93 1.156c.224-.434.458-.85.713-1.243a4.999 4.999 0 0 1 4.213-2.333c.348-.07.705-.12 1.07-.12.41 0 .816.064 1.2.19.495.16 1.02.443 1.547.854.541.427 1.116.954 1.6 1.587zM2 8a6 6 0 1 1 11.25 3.262C11.333 10.51 9.482 9.622 8 9.622c-1.927 0-3.936.992-5.25 2.054A6.001 6.001 0 0 1 2 8z"/><path d="M8 5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm4-3a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM4.5 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM15 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
											Draw with AI
										</button>
									</div>
									{{-- END MODIFICATION --}}
								</div>
								<div class="col-md-4">
									<label class="form-label">Image</label>
									{{-- START MODIFICATION: Add spinner overlay and data attributes for modal --}}
									<div class="image-upload-container mb-2 position-relative" style="min-height: 150px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
										<img src="{{ $place->image_path ?: 'https://via.placeholder.com/200' }}"
										     class="asset-image-preview"
										     style="max-width: 100%; max-height: 200px; object-fit: contain; {{ $place->image_path ? 'cursor: pointer;' : '' }}"
										     @if($place->image_path && isset($place->prompt_data))
											     data-bs-toggle="modal"
										     data-bs-target="#imageDetailModal"
										     data-image-url="{{ $place->image_path }}"
										     data-prompt-id="{{ $place->prompt_data->id }}"
										     data-upscale-status="{{ $place->prompt_data->upscale_status }}"
										     data-upscale-url="{{ $place->prompt_data->upscale_url ? asset('storage/upscaled/' . $place->prompt_data->upscale_url) : '' }}"
											@endif
										>
										<div class="spinner-overlay d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center" style="background-color: rgba(var(--bs-body-color-rgb), 0.5);">
											<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>
											<div class="mt-2 text-light">Generating...</div>
										</div>
									</div>
									{{-- END MODIFICATION --}}
									<input type="hidden" name="places[{{ $index }}][image_path]" class="image-path-input" value="{{ $place->image_path }}">
									<button type="button" class="btn btn-sm btn-primary select-image-btn">Upload/Select Image</button>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
			
			<div class="fixed-save-bar">
				<div class="container">
					<div class="text-end">
						<button type="submit" class="btn btn-success btn-lg">Save All Changes</button>
					</div>
				</div>
			</div>
		</form>
	</div>
	
	{{-- START MODIFICATION: Include all necessary modals --}}
	@include('story.partials.cropper-modal')
	@include('story.partials.history-modal')
	@include('story.partials.modals.generate-prompt-modal', ['models' => $models])
	@include('story.partials.modals.draw-with-ai-modal', ['imageModels' => $imageModels])
	@include('story.partials.modals.image-detail-modal')
	{{-- END MODIFICATION --}}
	
	{{-- Template for new places --}}
	<template id="place-template">
		<div class="card mb-3 place-card" data-id="">
			<div class="card-header d-flex justify-content-between align-items-center">
				New Place
				<button type="button" class="btn-close remove-place-btn"></button>
			</div>
			<div class="card-body">
				<input type="hidden" name="places[__INDEX__][id]" value="">
				<div class="row">
					<div class="col-md-8">
						<div class="mb-3">
							<label class="form-label">Name</label>
							<input type="text" name="places[__INDEX__][name]" class="form-control" value="" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea name="places[__INDEX__][description]" class="form-control asset-description" rows="5"></textarea>
						</div>
						{{-- START MODIFICATION: Add Image Prompt textarea and AI buttons to template --}}
						<div class="mb-3">
							<label class="form-label">Image Prompt</label>
							<textarea name="places[__INDEX__][image_prompt]" class="form-control image-prompt-textarea" rows="3" data-initial-value=""></textarea>
							<button type="button" class="btn btn-sm btn-outline-info mt-2 generate-prompt-btn" data-bs-toggle="modal" data-bs-target="#generatePromptModal">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic me-1" viewBox="0 0 16 16"><path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 3.5a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8 4.707 7.293 4Zm-.646 10.646a.5.5 0 0 0 .708 0L8 13.914l-1.06-1.06a.5.5 0 0 0-.854.353v.534a.5.5 0 0 0 .146.354l.646.646ZM.5 10.828a.5.5 0 0 0 1 0V9.157a.5.5 0 0 0-1 0v1.671Zm1.829-4.5A.5.5 0 0 0 2 6.586l.707.707a.5.5 0 0 0 .707 0L4 6.586a.5.5 0 0 0 0-.707L2.707 4.586a.5.5 0 0 0-.707 0ZM10.828.5a.5.5 0 0 0 0 1h1.671a.5.5 0 0 0 0-1h-1.671Z"/><path d="M3.5 13.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Zm9.025-5.99a1.5 1.5 0 0 0-1.025.433L6.932 12.47a1.5 1.5 0 0 0-1.025.433L3.025 15.8a.5.5 0 0 0 .854.353L5.5 14.5l.646.646a.5.5 0 0 0 .708 0L8.5 13.5l.646.646a.5.5 0 0 0 .708 0L11.5 12.5l.646.646a.5.5 0 0 0 .708 0L14.5 11.5l.646.646a.5.5 0 0 0 .854-.353l-2.882-2.882a1.5 1.5 0 0 0-1.025-.433Z"/></svg>
								Fill with AI
							</button>
							<button type="button" class="btn btn-sm btn-outline-success mt-2 draw-with-ai-btn" data-asset-id="">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-palette-fill me-1" viewBox="0 0 16 16"><path d="M12.433 10.07C14.133 10.585 16 11.15 16 8a8 8 0 1 0-15.93 1.156c.224-.434.458-.85.713-1.243a4.999 4.999 0 0 1 4.213-2.333c.348-.07.705-.12 1.07-.12.41 0 .816.064 1.2.19.495.16 1.02.443 1.547.854.541.427 1.116.954 1.6 1.587zM2 8a6 6 0 1 1 11.25 3.262C11.333 10.51 9.482 9.622 8 9.622c-1.927 0-3.936.992-5.25 2.054A6.001 6.001 0 0 1 2 8z"/><path d="M8 5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm4-3a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM4.5 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM15 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
								Draw with AI
							</button>
						</div>
						{{-- END MODIFICATION --}}
					</div>
					<div class="col-md-4">
						<label class="form-label">Image</label>
						{{-- START MODIFICATION: Add spinner overlay to template --}}
						<div class="image-upload-container mb-2 position-relative" style="min-height: 150px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
							<img src="https://via.placeholder.com/200" class="asset-image-preview" style="max-width: 100%; max-height: 200px; object-fit: contain;">
							<div class="spinner-overlay d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center" style="background-color: rgba(var(--bs-body-color-rgb), 0.5);">
								<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>
								<div class="mt-2 text-light">Generating...</div>
							</div>
						</div>
						{{-- END MODIFICATION --}}
						<input type="hidden" name="places[__INDEX__][image_path]" class="image-path-input" value="">
						<button type="button" class="btn btn-sm btn-primary select-image-btn">Upload/Select Image</button>
					</div>
				</div>
			</div>
		</div>
	</template>
@endsection

@section('styles')
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
	<style>
      .history-image-card { cursor: pointer; border: 2px solid transparent; }
      .history-image-card.selected { border-color: var(--bs-primary); }
      .history-image-card img { width: 100%; height: 150px; object-fit: cover; }
      .fixed-save-bar {
          position: fixed;
          bottom: 0;
          left: 0;
          width: 100%;
          padding: 1rem;
          background-color: var(--bs-body-bg);
          border-top: 1px solid var(--bs-border-color);
          box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
          z-index: 1030;
      }
	</style>
@endsection

@section('scripts')
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	<script src="{{ asset('js/story-asset-manager.js') }}"></script>
@endsection
