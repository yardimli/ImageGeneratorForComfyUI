@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
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
						<a href="{{ route('stories.places', 'story') }}" class="btn btn-info">Manage Places</a>
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
	
	{{-- Template for new pages --}}
	<template id="page-template">
		@include('story.partials.page-card', ['page' => null, 'index' => '__INDEX__', 'story' => $story])
	</template>
@endsection

@section('styles')
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
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
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	<script src="{{ asset('js/story-editor.js') }}"></script>
@endsection
