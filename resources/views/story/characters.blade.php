@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h1>Characters for "{{ $story->title }}"</h1>
				<a href="{{ route('stories.edit', $story) }}">← Back to Story</a>
			</div>
			<button type="button" id="add-character-btn" class="btn btn-primary">Add New Character</button>
		</div>
		
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
		<form action="{{ route('stories.characters.update', $story) }}" method="POST">
			@csrf
			<div id="characters-container">
				@foreach($story->characters as $index => $character)
					<div class="card mb-3 character-card" data-id="{{ $character->id }}">
						<div class="card-header d-flex justify-content-between align-items-center">
							Character
							<button type="button" class="btn-close remove-character-btn"></button>
						</div>
						<div class="card-body">
							<input type="hidden" name="characters[{{ $index }}][id]" value="{{ $character->id }}">
							<div class="row">
								<div class="col-md-8">
									<div class="mb-3">
										<label class="form-label">Name</label>
										<input type="text" name="characters[{{ $index }}][name]" class="form-control" value="{{ $character->name }}" required>
									</div>
									<div class="mb-3">
										<label class="form-label">Description</label>
										<textarea name="characters[{{ $index }}][description]" class="form-control" rows="5">{{ $character->description }}</textarea>
									</div>
								</div>
								<div class="col-md-4">
									<label class="form-label">Image</label>
									<div class="image-upload-container mb-2" style="min-height: 150px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
										<img src="{{ $character->image_path ?: 'https://via.placeholder.com/200' }}" class="character-image-preview" style="max-width: 100%; max-height: 200px; object-fit: contain;">
									</div>
									<input type="hidden" name="characters[{{ $index }}][image_path]" class="image-path-input" value="{{ $character->image_path }}">
									<button type="button" class="btn btn-sm btn-primary select-image-btn">Upload/Select Image</button>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
			
			<div class="text-end mt-4">
				<button type="submit" class="btn btn-success">Save All Changes</button>
			</div>
		</form>
	</div>
	
	{{-- Include Modals --}}
	@include('story.partials.cropper-modal')
	@include('story.partials.history-modal')
	
	{{-- Template for new characters --}}
	<template id="character-template">
		<div class="card mb-3 character-card" data-id="">
			<div class="card-header d-flex justify-content-between align-items-center">
				New Character
				<button type="button" class="btn-close remove-character-btn"></button>
			</div>
			<div class="card-body">
				<input type="hidden" name="characters[__INDEX__][id]" value="">
				<div class="row">
					<div class="col-md-8">
						<div class="mb-3">
							<label class="form-label">Name</label>
							<input type="text" name="characters[__INDEX__][name]" class="form-control" value="" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea name="characters[__INDEX__][description]" class="form-control" rows="5"></textarea>
						</div>
					</div>
					<div class="col-md-4">
						<label class="form-label">Image</label>
						<div class="image-upload-container mb-2" style="min-height: 150px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
							<img src="https://via.placeholder.com/200" class="character-image-preview" style="max-width: 100%; max-height: 200px; object-fit: contain;">
						</div>
						<input type="hidden" name="characters[__INDEX__][image_path]" class="image-path-input" value="">
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
	</style>
@endsection

@section('scripts')
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	<script src="{{ asset('js/story-asset-manager.js') }}"></script>
@endsection
