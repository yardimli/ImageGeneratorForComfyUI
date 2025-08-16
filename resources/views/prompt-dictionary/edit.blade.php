@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4" style="padding-bottom: 100px;">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				{{-- START MODIFICATION --}}
				<h1>{{ $entry->exists ? 'Edit' : 'Create' }} Dictionary Entry</h1>
				<p class="text-muted">
					Fill out the details for your dictionary entry. <a href="{{ route('prompt-dictionary.index') }}">Return to Grid View</a>.
				</p>
				{{-- END MODIFICATION --}}
			</div>
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
		
		<form action="{{ route('prompt-dictionary.update') }}" method="POST">
			@csrf
			{{-- START MODIFICATION: Removed loop and container div, form now handles a single entry --}}
			<div id="dictionary-entries-container"> {{-- This ID is kept for JS event delegation --}}
				<div class="card mb-3 entry-card" data-id="{{ $entry->id }}">
					<div class="card-body">
						<input type="hidden" name="id" value="{{ $entry->id }}">
						<div class="row">
							<div class="col-md-8">
								<div class="mb-3">
									<label class="form-label">Name</label>
									<input type="text" name="name" class="form-control" value="{{ old('name', $entry->name) }}" required>
								</div>
								<div class="mb-3">
									<label class="form-label">Description</label>
									<textarea name="description" class="form-control asset-description" rows="5">{{ old('description', $entry->description) }}</textarea>
									<button type="button" class="btn btn-sm btn-outline-secondary mt-2 rewrite-asset-description-btn" data-bs-toggle="modal" data-bs-target="#rewriteAssetDescriptionModal">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square me-1" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
										Rewrite
									</button>
								</div>
								<div class="mb-3">
									<label class="form-label">Image Prompt</label>
									<textarea name="image_prompt" class="form-control image-prompt-textarea" rows="3" data-initial-value='@json($entry->image_prompt ?? "")'>{{ old('image_prompt', $entry->image_prompt) }}</textarea>
									<button type="button" class="btn btn-sm btn-outline-info mt-2 generate-prompt-btn" data-bs-toggle="modal" data-bs-target="#generatePromptModal">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic me-1" viewBox="0 0 16 16"><path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 3.5a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8 4.707 7.293 4Zm-.646 10.646a.5.5 0 0 0 .708 0L8 13.914l-1.06-1.06a.5.5 0 0 0-.854.353v.534a.5.5 0 0 0 .146.354l.646.646ZM.5 10.828a.5.5 0 0 0 1 0V9.157a.5.5 0 0 0-1 0v1.671Zm1.829-4.5A.5.5 0 0 0 2 6.586l.707.707a.5.5 0 0 0 .707 0L4 6.586a.5.5 0 0 0 0-.707L2.707 4.586a.5.5 0 0 0-.707 0ZM10.828.5a.5.5 0 0 0 0 1h1.671a.5.5 0 0 0 0-1h-1.671Z"/><path d="M3.5 13.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Zm9.025-5.99a1.5 1.5 0 0 0-1.025.433L6.932 12.47a1.5 1.5 0 0 0-1.025.433L3.025 15.8a.5.5 0 0 0 .854.353L5.5 14.5l.646.646a.5.5 0 0 0 .708 0L8.5 13.5l.646.646a.5.5 0 0 0 .708 0L11.5 12.5l.646.646a.5.5 0 0 0 .708 0L14.5 11.5l.646.646a.5.5 0 0 0 .854-.353l-2.882-2.882a1.5 1.5 0 0 0-1.025-.433Z"/></svg>
										Fill with AI
									</button>
									<button type="button" class="btn btn-sm btn-outline-success mt-2 draw-with-ai-btn" data-asset-id="{{ $entry->id ?? '' }}">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-palette-fill me-1" viewBox="0 0 16 16"><path d="M12.433 10.07C14.133 10.585 16 11.15 16 8a8 8 0 1 0-15.93 1.156c.224-.434.458-.85.713-1.243a4.999 4.999 0 0 1 4.213-2.333c.348-.07.705-.12 1.07-.12.41 0 .816.064 1.2.19.495.16 1.02.443 1.547.854.541.427 1.116.954 1.6 1.587zM2 8a6 6 0 1 1 11.25 3.262C11.333 10.51 9.482 9.622 8 9.622c-1.927 0-3.936.992-5.25 2.054A6.001 6.001 0 0 1 2 8z"/><path d="M8 5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm4-3a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM4.5 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM15 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
										Draw with AI
									</button>
								</div>
							</div>
							<div class="col-md-4">
								<label class="form-label">Image
									@if(isset($entry->prompt_data) && $entry->prompt_data->upscale_status === 1)
										<span class="badge bg-warning ms-2" title="Image is being upscaled">Upscaling...</span>
									@endif
								</label>
								<div class="image-upload-container mb-2 position-relative" style="min-height: 150px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
									<img src="{{ $entry->image_path ?: 'https://via.placeholder.com/200' }}"
									     class="asset-image-preview"
									     style="max-width: 100%; object-fit: contain; {{ $entry->image_path ? 'cursor: pointer;' : '' }}"
									     @if($entry->image_path && isset($entry->prompt_data))
										     data-bs-toggle="modal"
									     data-bs-target="#imageDetailModal"
									     data-image-url="{{ $entry->image_path }}"
									     data-prompt-id="{{ $entry->prompt_data->id }}"
									     data-upscale-status="{{ $entry->prompt_data->upscale_status }}"
									     data-upscale-url="{{ $entry->prompt_data->upscale_url ? asset('storage/upscaled/' . $entry->prompt_data->upscale_url) : '' }}"
										@endif
									>
									<div class="spinner-overlay d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center" style="background-color: rgba(var(--bs-body-color-rgb), 0.5);">
										<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>
										<div class="mt-2 text-light">Generating...</div>
									</div>
								</div>
								<input type="hidden" name="image_path" class="image-path-input" value="{{ old('image_path', $entry->image_path) }}">
								<button type="button" class="btn btn-sm btn-primary select-image-btn">Upload/Select Image</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			{{-- END MODIFICATION --}}
			
			<div class="fixed-save-bar">
				<div class="container">
					<div class="text-end">
						<button type="submit" class="btn btn-success btn-lg">Save Changes</button> {{-- MODIFIED --}}
					</div>
				</div>
			</div>
		</form>
	</div>
	
	{{-- Modals --}}
	@include('story.partials.cropper-modal')
	@include('story.partials.history-modal')
	@include('story.partials.modals.generate-prompt-modal', ['models' => $models])
	@include('story.partials.modals.draw-with-ai-modal', ['imageModels' => $imageModels])
	@include('story.partials.modals.image-detail-modal')
	
	{{-- Rewrite Description Modal (specific for this page) --}}
	<div class="modal fade" id="rewriteAssetDescriptionModal" tabindex="-1" aria-labelledby="rewriteAssetDescriptionModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rewriteAssetDescriptionModalLabel">Rewrite Description with AI</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label for="rewrite-asset-style" class="form-label">Rewrite Style</label>
						<select id="rewrite-asset-style" class="form-select">
							{{-- Options will be populated by JS --}}
						</select>
					</div>
					<div class="mb-3">
						<label for="rewrite-asset-model" class="form-label">AI Model</label>
						<select class="form-select" id="rewrite-asset-model">
							@if(empty($models))
								<option value="" disabled>Could not load models.</option>
							@else
								@foreach($models as $model)
									<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
								@endforeach
							@endif
						</select>
					</div>
					<div class="mb-3">
						<label for="rewrite-asset-full-prompt" class="form-label">Full Prompt Sent to AI (Live Preview, Editable)</label>
						<textarea class="form-control" id="rewrite-asset-full-prompt" rows="10" style="font-family: monospace; font-size: 0.8rem;"></textarea>
					</div>
					<button type="button" class="btn btn-primary" id="rewrite-asset-btn">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						Rewrite with AI
					</button>
					<div id="rewrite-asset-result-area" class="d-none mt-4">
						<div class="mb-3">
							<label for="rewritten-asset-text" class="form-label">Rewritten Description (you can edit this)</label>
							<textarea class="form-control" id="rewritten-asset-text" rows="5"></textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-success d-none" id="replace-asset-text-btn">Replace Description</button>
				</div>
			</div>
		</div>
	</div>
	
	{{-- START MODIFICATION: Removed entry template --}}
	{{-- END MODIFICATION --}}
@endsection

@section('styles')
	<link rel="stylesheet" href="{{asset('vendor/cropperjs/1.6.1/cropper.min.css')}}"/>
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
	<script src="{{ asset('js/queue.js') }}"></script>
	<script src="{{asset('vendor/cropperjs/1.6.1/cropper.min.js')}}"></script>
	<script src="{{ asset('js/prompt-dictionary-manager.js') }}"></script>
@endsection
