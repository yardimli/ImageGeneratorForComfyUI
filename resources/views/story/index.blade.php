@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h1>All Stories</h1>
			
			@auth
				<div class="d-flex gap-2">
					<a href="{{ route('stories.create-ai.step1') }}" class="btn btn-info">Create Story with AI</a>
					<a href="{{ route('stories.create') }}" class="btn btn-primary">Create New Story</a>
				</div>
			@endauth
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
		
		<div class="card">
			<div class="card-body">
				@if($stories->isEmpty())
					<p class="text-center">No stories have been created yet.</p>
				@else
					<div class="list-group">
						@foreach($stories as $story)
							<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
								<div>
									<a href="{{ route('stories.show', $story) }}" class="text-decoration-none">
										<h5 class="mb-1">{{ $story->title }}</h5>
									</a>
									<p class="mb-1">{{ $story->short_description }}</p>
									<small>
										By: {{ $story->user->name ?? 'Unknown Author' }} |
										Level: {{ $story->level ?? 'N/A' }} |
										@php
											// MODIFICATION START: Calculate total image count from eager-loaded counts.
											$imageCount = $story->page_prompts_count + $story->character_prompts_count + $story->place_prompts_count;
											$cost = $story->image_cost;
											// MODIFICATION END
										@endphp
										Images: {{ $imageCount }} (${{ number_format($cost, 2) }}) |
										Last updated: {{ $story->updated_at->format('M d, Y') }}
									</small>
								</div>
								@auth
									<div class="d-flex gap-2 flex-wrap justify-content-end" style="min-width: 400px;">
										<a href="{{ route('stories.pdf.setup', $story) }}" class="btn btn-sm btn-outline-primary">PDF</a>
										<a href="{{ route('stories.show', $story) }}" class="btn btn-sm btn-outline-primary">Read</a>
										{{-- START MODIFICATION: Add "View as Text" button --}}
										<a href="{{ route('stories.text-view', $story) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Text</a>
										{{-- END MODIFICATION --}}
										<a href="{{ route('stories.edit', $story) }}" class="btn btn-sm btn-outline-primary">Edit</a>
										<a href="{{ route('stories.quiz', $story) }}" class="btn btn-sm btn-outline-secondary">Quiz</a>
										<form action="{{ route('stories.clone', $story) }}" method="POST" onsubmit="return confirm('Are you sure you want to clone this story?');">
											@csrf
											<button type="submit" class="btn btn-sm btn-outline-info">Clone</button>
										</form>
										<form action="{{ route('stories.destroy', $story) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this story and all its content?');">
											@csrf
											@method('DELETE')
											<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
										</form>
									</div>
								@endauth
							</div>
						@endforeach
					</div>
					
					<div class="mt-4">
						{{ $stories->links('pagination::bootstrap-5') }}
					</div>
				@endif
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script src="{{ asset('js/queue.js') }}"></script>
@endsection
