@extends('layouts.app')

@section('content')
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-10">
				@if (session('status'))
					<div class="alert alert-success" role="alert">
						{{ session('status') }}
					</div>
				@endif
				
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
									{{-- MODIFICATION START: Replaced hardcoded model list with a dynamic loop. --}}
									@foreach($supportedModels as $model)
										<li class="list-group-item d-flex justify-content-between align-items-center">
											{{ ucfirst(str_replace(['-', '_', '/'], ' ', $model)) }}
											<span class="badge bg-primary rounded-pill">{{ $modelStats[$model] ?? 0 }}</span>
										</li>
									@endforeach
									{{-- MODIFICATION END --}}
								</ul>
							</div>
							
							<div class="col-md-4">
								<h6 class="border-bottom pb-2 mb-3">By Generation Type</h6>
								<ul class="list-group">
									@foreach(['prompt', 'mix', 'mix-one', 'kontext-basic', 'kontext-lora'] as $type)
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
