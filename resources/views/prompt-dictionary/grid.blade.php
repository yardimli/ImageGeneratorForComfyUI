@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4" style="padding-bottom: 100px;">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h1>Prompt Dictionary</h1>
				<p class="text-muted">A visual grid of your reusable prompt components.</p>
			</div>
			<div>
				<button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#generateEntriesModal">Auto-Generate Entries</button>
				<a href="{{ route('prompt-dictionary.edit') }}" class="btn btn-primary">Add New Entry</a>
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
		
		{{-- START MODIFICATION: Added category filter form --}}
		<div class="mb-4">
			<form action="{{ route('prompt-dictionary.index') }}" method="GET" id="filter-form" class="row g-3 align-items-center">
				<div class="col-auto">
					<label for="category-filter" class="col-form-label">Filter by Category:</label>
				</div>
				<div class="col-auto">
					<select name="category" id="category-filter" class="form-select">
						<option value="">All Categories</option>
						@foreach($wordCategories as $cat)
							<option value="{{ $cat }}" {{ $category == $cat ? 'selected' : '' }}>{{ $cat }}</option>
						@endforeach
					</select>
				</div>
			</form>
		</div>
		{{-- END MODIFICATION --}}
		
		@if($entries->isEmpty())
			<div class="text-center p-5 border rounded">
				<h4>Your dictionary is empty.</h4>
				<p>Add an entry manually or use the AI generator to get started.</p>
			</div>
		@else
			<div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-4">
				@foreach($entries as $entry)
					<div class="col">
						<a href="{{ route('prompt-dictionary.edit', ['entry_id' => $entry->id]) }}" class="card h-100 text-decoration-none entry-grid-card">
							<img src="{{ $entry->image_path ?: 'https://via.placeholder.com/200?text=No+Image' }}" class="card-img-top" alt="{{ $entry->name }}">
							<div class="card-body">
								<p class="card-title text-center fw-bold mb-1">{{ $entry->name }}</p>
								{{-- START MODIFICATION: Display word category if it exists --}}
								@if($entry->word_category)
									<p class="text-center mb-0"><span class="badge bg-secondary">{{ $entry->word_category }}</span></p>
								@endif
								{{-- END MODIFICATION --}}
							</div>
						</a>
					</div>
				@endforeach
			</div>
		@endif
	</div>
	
	{{-- Auto-Generate Entries Modal --}}
	<div class="modal fade" id="generateEntriesModal" tabindex="-1" aria-labelledby="generateEntriesModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="generateEntriesModalLabel">Auto-Generate Dictionary Entries with AI</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-md-5">
							<div class="mb-3">
								<label for="generate-entries-prompt" class="form-label">Instructions for AI</label>
								<textarea class="form-control" id="generate-entries-prompt" rows="8">Create a punk music dictionary with descriptions that are suitable for use in image generation. Each description should only include descriptive words an image generator would understand, focusing on visual style, clothing, and mood.</textarea>
							</div>
							{{-- START MODIFICATION: Added category input --}}
							<div class="mb-3">
								<label for="generate-entries-category" class="form-label">Category (Optional)</label>
								<input class="form-control" list="generate-word-categories-list" id="generate-entries-category" placeholder="Assign a category to all generated entries">
								<datalist id="generate-word-categories-list">
									@foreach($wordCategories as $category)
										<option value="{{ $category }}">
									@endforeach
								</datalist>
							</div>
							{{-- END MODIFICATION --}}
							<div class="mb-3">
								<label for="generate-entries-count" class="form-label">Number of Entries</label>
								<select id="generate-entries-count" class="form-select">
									@for ($i = 1; $i <= 20; $i++)
										<option value="{{ $i }}" {{ $i == 10 ? 'selected' : '' }}>{{ $i }}</option>
									@endfor
								</select>
							</div>
							<div class="mb-3">
								<label for="generate-entries-model" class="form-label">AI Model</label>
								<select class="form-select" id="generate-entries-model">
									@if(empty($models))
										<option value="" disabled>Could not load models.</option>
									@else
										@foreach($models as $model)
											<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
										@endforeach
									@endif
								</select>
							</div>
							<div class="mb-3 d-none">
								<label for="generate-entries-full-prompt" class="form-label">Full Prompt Sent to AI</label>
								<textarea class="form-control" id="generate-entries-full-prompt" rows="10"></textarea>
							</div>
							{{-- START MODIFICATION: Changed button text --}}
							<button type="button" class="btn btn-primary" id="generate-entries-create-btn">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								Generate Preview
							</button>
							{{-- END MODIFICATION --}}
						</div>
						<div class="col-md-7">
							<h6>Generated Preview</h6>
							<div id="generate-entries-preview-area" class="border p-3 rounded" style="max-height: 500px; overflow-y: auto;">
								{{-- START MODIFICATION: Updated placeholder text --}}
								<p class="text-muted">Click "Generate Preview" to create new entries and see a preview here.</p>
								{{-- END MODIFICATION --}}
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					{{-- START MODIFICATION: Changed button text --}}
					<button type="button" class="btn btn-success d-none" id="add-generated-entries-btn">Save Entries & Refresh</button>
					{{-- END MODIFICATION --}}
				</div>
			</div>
		</div>
	</div>
@endsection

@section('styles')
	<style>
      .entry-grid-card img {
          aspect-ratio: 1 / 1;
          object-fit: cover;
      }
      .entry-grid-card {
          transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
      }
      .entry-grid-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
      }
	</style>
@endsection

@section('scripts')
	{{-- START MODIFICATION: Changed script to the new grid-specific JS file. --}}
	<script src="{{ asset('js/prompt-dictionary-grid.js') }}"></script>
	{{-- END MODIFICATION --}}
@endsection
