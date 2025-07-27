@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h1>My Stories</h1>
			<a href="{{ route('stories.create') }}" class="btn btn-primary">Create New Story</a>
		</div>
		
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
		<div class="card">
			<div class="card-body">
				@if($stories->isEmpty())
					<p class="text-center">You haven't created any stories yet.</p>
				@else
					<div class="list-group">
						@foreach($stories as $story)
							<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
								<div>
									<h5 class="mb-1">{{ $story->title }}</h5>
									<p class="mb-1">{{ $story->short_description }}</p>
									<small>Last updated: {{ $story->updated_at->format('M d, Y') }}</small>
								</div>
								<div class="d-flex gap-2">
									<a href="{{ route('stories.edit', $story) }}" class="btn btn-sm btn-outline-primary">Edit</a>
									<form action="{{ route('stories.destroy', $story) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this story and all its content?');">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
									</form>
								</div>
							</div>
						@endforeach
					</div>
				@endif
			</div>
		</div>
	</div>
@endsection
