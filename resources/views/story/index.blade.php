@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			{{-- START MODIFICATION: Update title for public view --}}
			<h1>All Stories</h1>
			{{-- END MODIFICATION --}}
			
			{{-- START MODIFICATION: Only show create buttons to authenticated users --}}
			@auth
				<div class="d-flex gap-2">
					<a href="{{ route('stories.create-ai') }}" class="btn btn-info">Create Story with AI</a>
					<a href="{{ route('stories.create') }}" class="btn btn-primary">Create New Story</a>
				</div>
			@endauth
			{{-- END MODIFICATION --}}
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
					{{-- START MODIFICATION: Update text for public view --}}
					<p class="text-center">No stories have been created yet.</p>
					{{-- END MODIFICATION --}}
				@else
					<div class="list-group">
						@foreach($stories as $story)
							{{-- START MODIFICATION: Update list item for public view with author and conditional actions --}}
							<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
								<div>
									<a href="{{ route('stories.show', $story) }}" class="text-decoration-none">
										<h5 class="mb-1">{{ $story->title }}</h5>
									</a>
									<p class="mb-1">{{ $story->short_description }}</p>
									<small>By: {{ $story->user->name ?? 'Unknown Author' }} | Last updated: {{ $story->updated_at->format('M d, Y') }}</small>
								</div>
								@if(auth()->check() && auth()->id() === $story->user_id)
									<div class="d-flex gap-2">
										<a href="{{ route('stories.edit', $story) }}" class="btn btn-sm btn-outline-primary">Edit</a>
										<form action="{{ route('stories.destroy', $story) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this story and all its content?');">
											@csrf
											@method('DELETE')
											<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
										</form>
									</div>
								@endif
							</div>
							{{-- END MODIFICATION --}}
						@endforeach
					</div>
					
					{{-- START MODIFICATION: Add pagination links --}}
					<div class="mt-4">
						{{ $stories->links() }}
					</div>
					{{-- END MODIFICATION --}}
				@endif
			</div>
		</div>
	</div>
@endsection
