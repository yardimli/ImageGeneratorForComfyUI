@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-center">
					<h3 class="mb-0">
						@if(isset($folder))
							Album Covers: {{ basename($folder) }}
						@else
							Album Covers
						@endif
					</h3>
					<div>
						<a href="{{ route('album-covers.liked') }}" class="btn btn-info btn-sm me-2">Show All Good Covers</a>
						@if(isset($folder))
							<a href="{{ route('album-covers.index') }}" class="btn btn-outline-secondary btn-sm">Back to Folders</a>
						@endif
					</div>
				</div>
			</div>
			<div class="card-body">
				@if(isset($directories))
					<h4 class="border-bottom pb-2 mb-3">Please select a folder</h4>
					<div class="row">
						@forelse($directories as $dir)
							<div class="col-md-3 mb-4">
								<div class="card h-100">
									<div class="card-body text-center d-flex flex-column justify-content-center">
										<h5 class="card-title">
											{{ basename($dir) }}
											<span class="badge bg-info ms-2">{{ $likedCounts[basename($dir)] ?? 0 }}</span>
										</h5>
										<a href="{{ route('album-covers.index', ['folder' => $dir]) }}" class="btn btn-primary mt-2 stretched-link">Open Folder</a>
									</div>
								</div>
							</div>
						@empty
							<div class="col">
								<p>No folders found in 'album-covers' directory in the S3 bucket.</p>
							</div>
						@endforelse
					</div>
				@elseif(isset($paginator))
					<form id="liked-images-form" action="{{ route('album-covers.update-liked') }}" method="POST">
						@csrf
						<input type="hidden" name="folder" value="{{ $folder }}">
						<div class="d-flex justify-content-between align-items-center mb-3">
							<button id="selectAllBtn" type="button" class="btn btn-sm btn-secondary">Select All</button>
							<button id="updateAndNextBtnTop" type="submit" class="btn btn-primary">Update & Go to Next Page</button>
						</div>
						<div class="row">
							@forelse($paginator as $imagePath)
								<div class="col-md-3 mb-4">
									<div class="card image-card">
										<div class="position-absolute top-0 start-0 m-2">
											<input type="checkbox" class="form-check-input image-checkbox" name="liked_images[]" value="{{ $imagePath }}" {{ in_array($imagePath, $likedImages ?? []) ? 'checked' : '' }} style="transform: scale(1.5);">
										</div>
										<img src="{{ $cloudfrontUrl }}/{{ $imagePath }}" class="card-img-top" alt="Album Cover">
										<input type="hidden" name="all_images_on_page[]" value="{{ $imagePath }}">
									</div>
								</div>
							@empty
								<div class="col">
									<p>No images found in this folder.</p>
								</div>
							@endforelse
						</div>
						<div class="d-flex justify-content-end align-items-center mt-3">
							<button id="updateAndNextBtnBottom" type="submit" class="btn btn-primary">Update & Go to Next Page</button>
						</div>
					</form>
					<div class="mt-4">
						{{ $paginator->links('pagination::bootstrap-5') }}
					</div>
				@endif
			</div>
		</div>
	</div>
@endsection

@section('styles')
	<style>
      .card-img-top {
          aspect-ratio: 1 / 1;
          object-fit: cover;
      }
      .image-card {
          transition: border-color 0.2s, box-shadow 0.2s;
      }
      .image-card.selected {
          border: 2px solid #0d6efd;
          box-shadow: 0 0 10px rgba(13, 110, 253, 0.5);
      }
	</style>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.getElementById('liked-images-form');
			if (!form) return;
			
			const selectAllBtn = document.getElementById('selectAllBtn');
			const checkboxes = document.querySelectorAll('.image-checkbox');
			const submitButtons = document.querySelectorAll('#updateAndNextBtnTop, #updateAndNextBtnBottom');
			
			function updateCardSelection(checkbox) {
				const card = checkbox.closest('.image-card');
				if (checkbox.checked) {
					card.classList.add('selected');
				} else {
					card.classList.remove('selected');
				}
			}
			
			checkboxes.forEach(checkbox => {
				updateCardSelection(checkbox); // Initial state
				checkbox.addEventListener('change', () => updateCardSelection(checkbox));
			});
			
			selectAllBtn.addEventListener('click', function () {
				const allSelected = Array.from(checkboxes).every(cb => cb.checked);
				checkboxes.forEach(checkbox => {
					checkbox.checked = !allSelected;
					updateCardSelection(checkbox);
				});
			});
			
			form.addEventListener('submit', async function (e) {
				e.preventDefault();
				submitButtons.forEach(btn => {
					btn.disabled = true;
					btn.textContent = 'Updating...';
				});
				
				const formData = new FormData(form);
				
				try {
					const response = await fetch(form.action, {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
							'Accept': 'application/json',
						},
						body: formData
					});
					const data = await response.json();
					
					if (data.success) {
						const nextLink = document.querySelector('.pagination .page-item.active + .page-item:not(.disabled) .page-link');
						if (nextLink) {
							window.location.href = nextLink.href;
						} else {
							alert('Liked status updated. You are on the last page.');
							location.reload();
						}
					} else {
						alert('Error: ' + (data.message || 'Failed to update liked images.'));
						submitButtons.forEach(btn => {
							btn.disabled = false;
							btn.textContent = 'Update & Go to Next Page';
						});
					}
				} catch (error) {
					console.error('Error submitting form:', error);
					alert('An unexpected error occurred.');
					submitButtons.forEach(btn => {
						btn.disabled = false;
						btn.textContent = 'Update & Go to Next Page';
					});
				}
			});
		});
	</script>
@endsection
