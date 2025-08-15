@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
			<div>
				<h1>Story Dictionary</h1>
				<h5 class="text-muted">{{ $story->title }}</h5>
			</div>
			<a href="{{ route('stories.index') }}" class="btn btn-secondary">Back to All Stories</a>
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
		
		{{-- AI Generation Section --}}
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0">Generate with AI</h5>
			</div>
			<div class="card-body">
				<div class="mb-3">
					<label for="ai-prompt" class="form-label">AI Prompt</label>
					<textarea class="form-control" id="ai-prompt" rows="10">{{ $promptText }}</textarea>
				</div>
				<div class="row align-items-end">
					<div class="col-md-6 mb-3">
						<label for="ai-model" class="form-label">AI Model</label>
						<select class="form-select" id="ai-model">
							@if(empty($models))
								<option value="" disabled>Could not load models.</option>
							@else
								@foreach($models as $model)
									<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
								@endforeach
							@endif
						</select>
					</div>
					<div class="col-md-6 mb-3 text-md-end">
						<button type="button" class="btn btn-primary" id="generate-btn">
							<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							Generate Entries
						</button>
					</div>
				</div>
			</div>
		</div>
		
		{{-- Dictionary Form Section --}}
		<form action="{{ route('stories.dictionary.update', $story) }}" method="POST">
			@csrf
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Dictionary Entries</h5>
					<button type="button" class="btn btn-sm btn-success" id="add-row-btn">Add Entry</button>
				</div>
				<div class="card-body">
					<div id="dictionary-entries-container">
						@forelse($story->dictionary as $index => $entry)
							@include('story.partials.dictionary-entry-row', ['index' => $index, 'entry' => $entry])
						@empty
							<p id="no-entries-message" class="text-muted">No dictionary entries yet. Use the AI generator above or add entries manually.</p>
						@endforelse
					</div>
				</div>
				<div class="card-footer text-end">
					<button type="submit" class="btn btn-primary">Save Dictionary</button>
				</div>
			</div>
		</form>
	</div>
	
	{{-- Template for new dictionary rows --}}
	<template id="dictionary-entry-template">
		@include('story.partials.dictionary-entry-row', ['index' => '__INDEX__', 'entry' => null])
	</template>
@endsection

@section('scripts')
	<script src="{{ asset('js/story-dictionary.js') }}"></script>
@endsection
