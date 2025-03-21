@extends('layouts.app')

@section('content')
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-8">
				<div class="card">
					<div class="card-header">{{ __('Dashboard') }}</div>
					
					<div class="card-body">
						@auth
							{{-- The user is authenticated (logged in) --}}
							<p>Welcome to your dashboard!</p>
							{{-- You can add more authenticated user content here --}}
							<div class="mt-3">
								<a href="{{ route('prompts.index') }}" class="btn btn-primary me-2">Prompts</a>
								<a href="{{ route('image-mix.index') }}" class="btn btn-secondary me-2">Image Mix Tool</a>
								<a href="{{ route('gallery.index', ['date' => $date, 'sort' => $sort ?? 'updated_at', 'types' => $selectedTypes ?? ['dev']]) }}" class="btn btn-primary me-2">Gallery</a>
							</div>
						@else
							{{-- The user is not authenticated (not logged in) --}}
							<p>Please login to view the dashboard.</p>
						@endauth
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
