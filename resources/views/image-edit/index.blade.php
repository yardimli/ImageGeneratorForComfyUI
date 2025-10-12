@extends('layouts.app')

@section('styles')
	{{-- Copied from story-editor.blade.php for history modal styling --}}
	<link rel="stylesheet" href="{{asset('vendor/cropperjs/1.6.1/cropper.min.css')}}"/>
	<style>
      .history-image-card { cursor: pointer; border: 2px solid transparent; }
      .history-image-card.selected { border-color: var(--bs-primary); }
      .history-image-card img { width: 100%; height: 150px; object-fit: cover; }
	</style>
@endsection

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<h1>Image Edit</h1>
			</div>
			<div class="card-body">
				{{-- START MODIFICATION --}}
				<div class="alert alert-info small">
					This feature uses an image editing model to generate a new image based on your prompt and the input images provided below.
				</div>
				
				<div class="mb-3">
					<label for="model" class="form-label">AI Model</label>
					<select class="form-select" id="model">
						<option value="fal-ai/gemini-25-flash-image/edit" selected>Gemini 2.5 Flash Image Edit ($0.04)</option>
						<option value="fal-ai/dreamomni2/edit">DreamOmni2 Edit ($0.05)</option>
						<option value="fal-ai/qwen-image-edit-plus">Qwen Image Edit Plus ($0.03)</option>
						<option value="fal-ai/bytedance/seedream/v4/edit">SeeDream v4 Edit ($0.03)</option>
					</select>
				</div>
				{{-- END MODIFICATION --}}
				
				<div class="mb-3">
					<label class="form-label fw-bold">Input Images (click to remove):</label>
					<div id="input-images-container" class="d-flex flex-wrap gap-2 border p-2 rounded" style="min-height: 110px;">
						{{-- Thumbnails will be injected here by JS --}}
					</div>
					<button type="button" class="btn btn-primary mt-2" id="add-image-btn">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
							<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
							<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
						</svg>
						Add Image
					</button>
				</div>
				
				<div class="mb-3">
					<label for="prompt" class="form-label fw-bold">Prompt</label>
					<textarea id="prompt" class="form-control" rows="4" placeholder="Describe the image you want to create..."></textarea>
				</div>
				
				<div class="row mb-3">
					<div class="col-md-6">
						<label for="aspect-ratio" class="form-label">Aspect Ratio</label>
						<select class="form-select" id="aspect-ratio">
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
					<div class="col-md-3">
						<label for="width" class="form-label">Width</label>
						<input type="number" class="form-control" id="width" value="1024">
					</div>
					<div class="col-md-3">
						<label for="height" class="form-label">Height</label>
						<input type="number" class="form-control" id="height" value="1024">
					</div>
				</div>
				
				<div class="form-check mb-3">
					<input type="checkbox" class="form-check-input" id="upload-to-s3" value="1" checked>
					<label class="form-check-label" for="upload-to-s3">Upload to S3</label>
				</div>
				
				<button type="button" class="btn btn-success btn-lg" id="generate-btn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Generate Image
				</button>
			</div>
		</div>
		
		<div class="card mt-4">
			<div class="card-header">
				<h3>Result</h3>
			</div>
			<div class="card-body text-center" id="result-container" style="min-height: 200px;">
				<div id="spinner-container" class="d-none flex-column justify-content-center align-items-center h-100">
					<div class="spinner-border" role="status" style="width: 3rem; height: 3rem;">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-2">Generating image, this may take a few minutes...</p>
				</div>
				<img id="result-image" src="" class="img-fluid rounded" alt="Generated image">
			</div>
		</div>
	</div>
	
	{{-- Include Modals --}}
	@include('story.partials.cropper-modal')
	@include('story.partials.history-modal')
	
	{{-- Template for input image thumbnails --}}
	<template id="input-image-thumbnail-template">
		<div class="position-relative input-image-thumbnail" style="cursor: pointer;" title="Click to remove">
			<img src="" class="rounded" style="width: 90px; height: 90px; object-fit: cover;" alt="Input image thumbnail">
			<input type="hidden" class="image-path-input" value="">
			<button type="button" class="btn-close btn-sm position-absolute top-0 end-0 bg-light rounded-circle" aria-label="Remove" style="transform: translate(25%, -25%);"></button>
		</div>
	</template>
@endsection

@section('scripts')
	<script src="{{ asset('js/queue.js') }}"></script>
	<script src="{{asset('vendor/cropperjs/1.6.1/cropper.min.js')}}"></script>
	<script src="{{ asset('js/image-edit.js') }}"></script>
@endsection
