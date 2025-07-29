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
					{{-- START MODIFICATION: Added an "Edit Story" button for authenticated users. --}}
					@auth
						<a href="{{ route('stories.edit', $story) }}" class="btn btn-primary">Edit Story</a>
						<a href="{{ route('stories.pdf.setup', $story) }}" class="btn btn-info">Download as PDF</a>
					@endauth
					{{-- END MODIFICATION --}}
					<a href="{{ route('stories.index') }}" class="btn btn-secondary">Back to All Stories</a>
				</div>
			</div>
			<div class="card-body">
				<p class="lead">{{ $story->short_description }}</p>
				{{-- START MODIFICATION: Display story level. --}}
				<p class="text-muted">By: {{ $story->user->name ?? 'Unknown Author' }} | Level: {{ $story->level ?? 'N/A' }}</p>
				{{-- END MODIFICATION --}}
				
				{{-- START MODIFICATION: Add collapsible section for AI generation details. --}}
				@if($story->initial_prompt)
					<div class="card bg-body-tertiary mb-4">
						<div class="card-header">
							<h5 class="mb-0">
								<a class="text-decoration-none" data-bs-toggle="collapse" href="#ai-details" role="button" aria-expanded="false" aria-controls="ai-details">
									AI Generation Details
								</a>
							</h5>
						</div>
						<div class="collapse" id="ai-details">
							<div class="card-body">
								<div class="mb-3">
									<strong>Model Used:</strong>
									<p class="font-monospace mb-0">{{ $story->model }}</p>
								</div>
								<div>
									<strong>Initial Prompt:</strong>
									<p class="font-monospace" style="white-space: pre-wrap;">{{ $story->initial_prompt }}</p>
								</div>
							</div>
						</div>
					</div>
				@endif
				{{-- END MODIFICATION --}}
				
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
