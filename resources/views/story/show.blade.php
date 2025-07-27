@extends('layouts.bootstrap-app')

@section('styles')
	<style>
      .page-text {
          font-size: 1.1rem;
          line-height: 1.6;
      }
      .story-page-row:nth-child(even) .page-text-col {
          order: 1;
      }
      .story-page-row:nth-child(even) .page-image-col {
          order: 2;
      }
      .story-page-row:nth-child(odd) .page-text-col {
          order: 2;
      }
      .story-page-row:nth-child(odd) .page-image-col {
          order: 1;
      }
      @media (max-width: 767px) {
          .story-page-row > div {
              order: 0 !important;
          }
      }
	</style>
@endsection

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
				<h1 class="mb-0 me-3">{{ $story->title }}</h1>
				<div class="d-flex gap-2 mt-2 mt-md-0">
					{{-- START NEW MODIFICATION: Add PDF download button --}}
					@auth
						<a href="{{ route('stories.pdf.setup', $story) }}" class="btn btn-info">Download as PDF</a>
					@endauth
					{{-- END NEW MODIFICATION --}}
					<a href="{{ route('stories.index') }}" class="btn btn-secondary">Back to All Stories</a>
				</div>
			</div>
			<div class="card-body">
				<p class="lead">{{ $story->short_description }}</p>
				<p class="text-muted">By: {{ $story->user->name ?? 'Unknown Author' }}</p>
				<hr>
				
				{{-- Story Pages --}}
				@forelse($story->pages as $page)
					<div class="row mb-5 align-items-center story-page-row">
						<div class="col-md-6 page-image-col">
							@if($page->image_path)
								<img src="{{ asset($page->image_path) }}" class="img-fluid rounded shadow-sm w-100" alt="Page image" style="aspect-ratio: 1/1; object-fit: cover;">
							@else
								<div class="d-flex align-items-center justify-content-center bg-body-secondary rounded shadow-sm w-100" style="aspect-ratio: 1/1;">
									<span class="text-muted">No Image</span>
								</div>
							@endif
						</div>
						<div class="col-md-6 page-text-col">
							<div class="p-3">
								<p class="page-text">{{ $page->story_text }}</p>
								<p class="text-muted small mt-4">Page {{ $page->page_number }}</p>
							</div>
						</div>
					</div>
				@empty
					<p class="text-center text-muted">This story has no pages yet.</p>
				@endforelse
			</div>
		</div>
	</div>
@endsection
