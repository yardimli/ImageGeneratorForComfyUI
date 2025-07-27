@extends('layouts.bootstrap-app')

@section('styles')
	{{-- START NEW FILE --}}
	<style>
      .card-body p {
          /* To respect newlines in descriptions and story text */
          white-space: pre-wrap;
      }
	</style>
	{{-- END NEW FILE --}}
@endsection

@section('content')
	{{-- START NEW FILE --}}
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h1 class="mb-1">{{ $story->title }}</h1>
				<p class="text-muted mb-0">By {{ $story->user->name }} | Last updated: {{ $story->updated_at->format('F j, Y') }}</p>
			</div>
			@if(auth()->check() && auth()->id() === $story->user_id)
				<a href="{{ route('stories.edit', $story) }}" class="btn btn-primary">Edit Story</a>
			@endif
		</div>
		
		<div class="card mb-4">
			<div class="card-body">
				<h5 class="card-title">Description</h5>
				<p class="card-text">{{ $story->short_description ?: 'No description provided.' }}</p>
			</div>
		</div>
		
		@if($story->characters->isNotEmpty())
			<div class="card mb-4">
				<div class="card-header">
					<h3>Characters</h3>
				</div>
				<div class="card-body">
					<div class="row">
						@foreach($story->characters as $character)
							<div class="col-md-6 mb-3">
								<div class="d-flex">
									@if($character->image_path)
										<img src="{{ $character->image_path }}" alt="{{ $character->name }}" class="me-3 rounded" style="width: 100px; height: 100px; object-fit: cover;">
									@endif
									<div>
										<h5>{{ $character->name }}</h5>
										<p>{{ $character->description }}</p>
									</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>
		@endif
		
		@if($story->places->isNotEmpty())
			<div class="card mb-4">
				<div class="card-header">
					<h3>Places</h3>
				</div>
				<div class="card-body">
					<div class="row">
						@foreach($story->places as $place)
							<div class="col-md-6 mb-3">
								<div class="d-flex">
									@if($place->image_path)
										<img src="{{ $place->image_path }}" alt="{{ $place->name }}" class="me-3 rounded" style="width: 100px; height: 100px; object-fit: cover;">
									@endif
									<div>
										<h5>{{ $place->name }}</h5>
										<p>{{ $place->description }}</p>
									</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>
		@endif
		
		<h3 class="mb-3">Story Pages</h3>
		@forelse($story->pages as $page)
			<div class="card mb-3">
				<div class="card-header">
					Page {{ $page->page_number }}
				</div>
				<div class="card-body">
					<div class="row align-items-start">
						<div class="col-md-8">
							<p>{{ $page->story_text }}</p>
						</div>
						<div class="col-md-4">
							@if($page->image_path)
								<img src="{{ $page->image_path }}" class="img-fluid rounded" alt="Image for page {{ $page->page_number }}">
							@endif
						</div>
					</div>
				</div>
			</div>
		@empty
			<div class="card">
				<div class="card-body">
					<p class="text-center text-muted">This story has no pages yet.</p>
				</div>
			</div>
		@endforelse
	</div>
	{{-- END NEW FILE --}}
@endsection
