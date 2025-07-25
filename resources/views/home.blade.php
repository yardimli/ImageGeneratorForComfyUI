@extends('layouts.app')

@section('content')
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-10">
				<div class="card">
					<div class="card-header">{{ __('Dashboard') }}</div>
					
					<div class="card-body">
						@if (session('status'))
							<div class="alert alert-success" role="alert">
								{{ session('status') }}
							</div>
						@endif
						
						{{ __('You are logged in!') }}
						
						<div class="mt-3">
							<a href="{{ route('prompts.index') }}" class="btn btn-primary me-2">Prompts</a>
							<a href="{{ route('image-mix.index') }}" class="btn btn-secondary me-2">Image Mix Tool</a>
							<a href="{{ route('gallery.index', ['date' => $date ?? '', 'sort' => $sort ?? 'updated_at', 'types' => $selectedTypes ?? ['all']]) }}" class="btn btn-primary">Gallery</a>
						</div>
					</div>
				</div>
				
				<!-- Image Statistics -->
				<div class="card mt-4">
					<div class="card-header">
						<h5 class="mb-0">Image Statistics</h5>
					</div>
					
					<div class="card-body">
						<div class="row">
							<div class="col-md-4">
								<h6 class="border-bottom pb-2 mb-3">By Model</h6>
								<ul class="list-group">
									@foreach(['schnell', 'dev', 'minimax', 'minimax-expand', 'imagen3', 'aura-flow', 'ideogram-v2a', 'luma-photon', 'recraft-20b'] as $model)
										<li class="list-group-item d-flex justify-content-between align-items-center">
											{{ ucfirst(str_replace('-', ' ', $model)) }}
											<span class="badge bg-primary rounded-pill">{{ $modelStats[$model] ?? 0 }}</span>
										</li>
									@endforeach
								</ul>
							</div>
							
							<div class="col-md-4">
								<h6 class="border-bottom pb-2 mb-3">By Generation Type</h6>
								<ul class="list-group">
									@foreach(['prompt', 'mix', 'mix-one'] as $type)
										<li class="list-group-item d-flex justify-content-between align-items-center">
											{{ ucfirst(str_replace('-', ' ', $type)) }}
											<span class="badge bg-secondary rounded-pill">{{ $generationTypeStats[$type] ?? 0 }}</span>
										</li>
									@endforeach
								</ul>
								
								<h6 class="border-bottom pb-2 mb-3 mt-4">Total</h6>
								<div class="card bg-body-secondary">
									<div class="card-body text-center">
										<h3>{{ $totalImages }}</h3>
										<p class="mb-0">Total Images</p>
									</div>
								</div>
							</div>
							
							<div class="col-md-4">
								<h6 class="border-bottom pb-2 mb-3">Special Categories</h6>
								
								<div class="card mb-3 bg-success text-white">
									<div class="card-body text-center">
										<h3>{{ $upscaledImages }}</h3>
										<p class="mb-0">Upscaled Images</p>
									</div>
								</div>
								
								<div class="card bg-info text-white">
									<div class="card-body text-center">
										<h3>{{ $imagesWithNotes }}</h3>
										<p class="mb-0">Images with Notes</p>
									</div>
								</div>
							</div>
						</div>
						
						<div class="mt-4">
							<a href="{{ route('gallery.index') }}" class="btn btn-primary">
								View Gallery
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
