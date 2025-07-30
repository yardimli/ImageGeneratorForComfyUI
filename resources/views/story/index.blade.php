@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h1>All Stories</h1>
			
			@auth
				<div class="d-flex gap-2">
					<a href="{{ route('stories.create-ai') }}" class="btn btn-info">Create Story with AI</a>
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
									{{-- START MODIFICATION: Display the story level, image count, and cost. --}}
									<small>
										By: {{ $story->user->name ?? 'Unknown Author' }} |
										Level: {{ $story->level ?? 'N/A' }} |
										@php
											$imageCount = $story->image_count;
											$cost = $imageCount * 0.07;
										@endphp
										Images: {{ $imageCount }} (${{ number_format($cost, 2) }}) |
										Last updated: {{ $story->updated_at->format('M d, Y') }}
									</small>
									{{-- END MODIFICATION --}}
								</div>
								{{-- START MODIFICATION: Show edit/delete buttons to any authenticated user. --}}
								@auth
									<div class="d-flex gap-2">
										<a href="{{ route('stories.edit', $story) }}" class="btn btn-sm btn-outline-primary">Edit</a>
										<form action="{{ route('stories.destroy', $story) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this story and all its content?');">
											@csrf
											@method('DELETE')
											<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
										</form>
									</div>
								@endauth
								{{-- END MODIFICATION --}}
							</div>
						@endforeach
					</div>
					
					<div class="mt-4">
						{{ $stories->links() }}
					</div>
				@endif
			</div>
		</div>
	</div>
@endsection
