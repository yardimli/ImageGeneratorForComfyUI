@extends('layouts.bootstrap-app')

@section('content')
	<div class="queue-status">
		<span class="me-2">Queue:</span>
		<span id="queueCount" class="badge bg-primary">0</span>
	</div>
	
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-center">
					<h3 class="mb-0">Image Gallery</h3>
					<div>
						<div class="btn-group me-2">
							<a href="{{ route('gallery.index', ['sort' => 'updated_at', 'type' => $type ?? 'all', 'group' => $groupByDay ?? true]) }}"
							   class="btn btn-sm {{ ($sort ?? 'updated_at') == 'updated_at' ? 'btn-primary' : 'btn-outline-primary' }}">Sort by Last Updated</a>
							<a href="{{ route('gallery.index', ['sort' => 'created_at', 'type' => $type ?? 'all', 'group' => $groupByDay ?? true]) }}"
							   class="btn btn-sm {{ ($sort ?? '') == 'created_at' ? 'btn-primary' : 'btn-outline-primary' }}">Sort by Creation Date</a>
						</div>
						
						<div class="btn-group me-2">
							<a href="{{ route('gallery.index', ['sort' => $sort ?? 'updated_at', 'type' => 'all', 'group' => $groupByDay ?? true]) }}"
							   class="btn btn-sm {{ ($type ?? 'all') == 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All Types</a>
							<a href="{{ route('gallery.index', ['sort' => $sort ?? 'updated_at', 'type' => 'mix', 'group' => $groupByDay ?? true]) }}"
							   class="btn btn-sm {{ ($type ?? '') == 'mix' ? 'btn-primary' : 'btn-outline-primary' }}">Mix Only</a>
							<a href="{{ route('gallery.index', ['sort' => $sort ?? 'updated_at', 'type' => 'other', 'group' => $groupByDay ?? true]) }}"
							   class="btn btn-sm {{ ($type ?? '') == 'other' ? 'btn-primary' : 'btn-outline-primary' }}">Other Types</a>
						</div>
					</div>
				</div>
				
				<div class="mt-2">
					<button id="selectAllBtn" class="btn btn-sm btn-secondary me-2">Select All</button>
					<button id="bulkDeleteBtn" class="btn btn-sm btn-danger" disabled>Delete Selected</button>
				</div>
				
				@if(isset($filterActive) && $filterActive)
					<div class="alert alert-info mb-0 p-2 mt-2">
						{{ $filterDescription }}
						<a href="{{ route('gallery.index') }}" class="ms-2 btn btn-sm btn-outline-primary">Clear Filter</a>
					</div>
				@endif
				
				@if(isset($date) && $date)
					<div class="alert alert-info mb-0 p-2 mt-2">
						Viewing images from: {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
						<a href="{{ route('gallery.index', ['sort' => $sort ?? 'updated_at', 'type' => $type ?? 'all', 'group' => true]) }}"
						   class="ms-2 btn btn-sm btn-outline-primary">Back to Groups</a>
					</div>
				@endif
			</div>
			
			<div class="card-body">
				@if(isset($groupedImages) && $groupByDay)
					@foreach($groupedImages as $date => $dayImages)
						<div class="mb-4">
							<h4 class="border-bottom pb-2">
								<a href="{{ route('gallery.index', ['date' => $date, 'sort' => $sort ?? 'updated_at', 'type' => $type ?? 'all']) }}"
								   class="text-decoration-none">
									{{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
								</a>
								<span class="badge bg-secondary">{{ $dayImages->totalCount ?? $dayImages->count() }} images</span>
								@if($dayImages->count() > 8)
									<a href="{{ route('gallery.index', ['date' => $date, 'sort' => $sort ?? 'updated_at', 'type' => $type ?? 'all']) }}"
									   class="btn btn-sm btn-outline-primary ms-2">View All</a>
								@endif
							</h4>
							
							<div class="row">
								@foreach($dayImages as $image)
									<div class="col-md-3 mb-4">
										<div class="card">
											<div class="position-absolute top-0 start-0 m-2">
												<input type="checkbox" class="form-check-input image-checkbox" data-prompt-id="{{ $image->id }}" style="transform: scale(1.3);">
											</div>
											<img src="{{ $image->thumbnail }}" class="card-img-top cursor-pointer" onclick="openImageModal('{{ $image->filename }}')" alt="Generated Image">
											<div class="card-body">
												<div class="mb-3">
													<small class="text-muted">({{ $image->generation_type }}: {{ $image->model }})</small>
													<div class="prompt-text" style="font-size: 0.9em; max-height: 100px; overflow-y: auto;">
														{{ $image->generated_prompt }}
													</div>
												</div>
												<div class="mb-2">
													<small class="text-muted">{{ $image->created_at->format('Y-m-d H:i') }}</small>
												</div>
												<div class="mb-2">
													<textarea class="form-control notes-input" placeholder="Add notes..." data-prompt-id="{{ $image->id }}">{{ $image->notes }}</textarea>
												</div>
												<button class="btn btn-primary btn-sm update-notes-btn mb-2" data-prompt-id="{{ $image->id }}">
													Update
												</button>
												<button class="btn btn-danger btn-sm delete-image-btn mb-2" data-prompt-id="{{ $image->id }}">
													Delete
												</button>
												@if($image->generation_type === 'mix')
													<button class="btn btn-info btn-sm view-source-btn mb-2" data-input-image1="{{ $image->input_image_1 }}" data-input-image2="{{ $image->input_image_2 }}" data-strength1="{{ $image->input_image_1_strength }}" data-strength2="{{ $image->input_image_2_strength }}">
														Source
													</button>
												@endif
												@if($image->upscale_status === 0)
													<button class="btn btn-success btn-sm upscale-btn mb-2" data-prompt-id="{{ $image->id }}" data-filename="{{ $image->filename }}">
														Upscale
													</button>
												@elseif($image->upscale_status === 1)
													<div class="text-warning">Upscale in progress...</div>
												@elseif($image->upscale_status === 2)
													<a href="/storage/upscaled/{{ $image->upscale_url }}" class="btn btn-info btn-sm mb-2" target="_blank">
														View Upscaled
													</a>
												@endif
												<div id="upscale-status-{{ $image->id }}" class="mt-2"></div>
											</div>
										</div>
									</div>
								@endforeach
							</div>
						</div>
					@endforeach
					
					<div class="mt-4">
						{{ $days->links('pagination::bootstrap-5') }}
					</div>
				@else
					<div class="row">
						@foreach($images as $image)
							<div class="col-md-3 mb-4">
								<div class="card">
									<div class="position-absolute top-0 start-0 m-2">
										<input type="checkbox" class="form-check-input image-checkbox" data-prompt-id="{{ $image->id }}" style="transform: scale(1.3);">
									</div>
									<img src="{{ $image->thumbnail }}" class="card-img-top cursor-pointer" onclick="openImageModal('{{ $image->filename }}')" alt="Generated Image">
									<div class="card-body">
										<div class="mb-3">
											<small class="text-muted">({{ $image->generation_type }}: {{ $image->model }})</small>
											<div class="prompt-text" style="font-size: 0.9em; max-height: 100px; overflow-y: auto;">
												{{ $image->generated_prompt }}
											</div>
										</div>
										<div class="mb-2">
											<small class="text-muted">{{ $image->created_at->format('Y-m-d H:i') }}</small>
										</div>
										<div class="mb-2">
											<textarea class="form-control notes-input" placeholder="Add notes..." data-prompt-id="{{ $image->id }}">{{ $image->notes }}</textarea>
										</div>
										<button class="btn btn-primary btn-sm update-notes-btn mb-2" data-prompt-id="{{ $image->id }}">
											Update
										</button>
										<button class="btn btn-danger btn-sm delete-image-btn mb-2" data-prompt-id="{{ $image->id }}">
											Delete
										</button>
										@if($image->generation_type === 'mix')
											<button class="btn btn-info btn-sm view-source-btn mb-2" data-input-image1="{{ $image->input_image_1 }}" data-input-image2="{{ $image->input_image_2 }}" data-strength1="{{ $image->input_image_1_strength }}" data-strength2="{{ $image->input_image_2_strength }}">
												Source
											</button>
										@endif
										@if($image->upscale_status === 0)
											<button class="btn btn-success btn-sm upscale-btn mb-2" data-prompt-id="{{ $image->id }}" data-filename="{{ $image->filename }}">
												Upscale
											</button>
										@elseif($image->upscale_status === 1)
											<div class="text-warning">Upscale in progress...</div>
										@elseif($image->upscale_status === 2)
											<a href="/storage/upscaled/{{ $image->upscale_url }}" class="btn btn-info btn-sm mb-2" target="_blank">
												View Upscaled
											</a>
										@endif
										<div id="upscale-status-{{ $image->id }}" class="mt-2"></div>
									</div>
								</div>
							</div>
						@endforeach
					</div>
					
					<div class="mt-4">
						{{ $images->appends(['sort' => $sort ?? 'updated_at', 'type' => $type ?? 'all', 'date' => $date ?? null])->links('pagination::bootstrap-5') }}
					</div>
				@endif
				
				<div class="mt-4">
					<a href="{{ route('home') }}" class="btn btn-secondary">Back to Home</a>
					<a href="{{ route('image-mix.index') }}" class="btn btn-secondary">Image Mix Tool</a>
					<a href="{{ route('prompts.index') }}" class="btn btn-secondary">Back to Prompts</a>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Image Modal -->
	<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title" id="imageModalLabel">Full Size Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body text-center">
					<img id="modalImage" src="" style="max-width: 100%; height: auto;" alt="Full size image">
				</div>
			</div>
		</div>
	</div>
	
	<!-- Confirmation Modal -->
	<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title">Confirm Delete</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete <span id="deleteCount">0</span> selected images? This cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmBulkDeleteBtn">Delete</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Source Images Modal -->
	<div class="modal fade" id="sourceImagesModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title">Source Images</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<!-- Left source image -->
						<div class="col-md-6 text-center">
							<h6>Image 1</h6>
							<img id="sourceImage1" src="" style="max-width: 100%; height: auto;" alt="Source Image 1">
							<div class="mt-2">
								<span>Strength: <span id="sourceStrength1"></span></span>
							</div>
						</div>
						<!-- Right source image -->
						<div class="col-md-6 text-center">
							<h6>Image 2</h6>
							<img id="sourceImage2" src="" style="max-width: 100%; height: auto;" alt="Source Image 2">
							<div class="mt-2">
								<span>Strength: <span id="sourceStrength2"></span></span>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('styles')
	<style>
      .card-img-top {
          /*height: 200px; !* Fixed height for gallery thumbnails *!*/
          /*object-fit: cover;*/
      }

      .card-header h4 {
          cursor: pointer;
      }

      .card-header h4:hover {
          color: #0d6efd;
      }
	</style>
@endsection

@section('scripts')
	<script src="{{ asset('js/image-results-script.js') }}"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const deleteConfirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
			const selectAllBtn = document.getElementById('selectAllBtn');
			const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
			const checkboxes = document.querySelectorAll('.image-checkbox');
			const confirmBulkDeleteBtn = document.getElementById('confirmBulkDeleteBtn');
			const sourceImagesModal = new bootstrap.Modal(document.getElementById('sourceImagesModal'));
			
			document.querySelectorAll('.view-source-btn').forEach(button => {
				button.addEventListener('click', function() {
					const image1 = this.dataset.inputImage1;
					const image2 = this.dataset.inputImage2;
					const strength1 = this.dataset.strength1;
					const strength2 = this.dataset.strength2;
					
					// Set the source images and strengths
					document.getElementById('sourceImage1').src = image1;
					document.getElementById('sourceImage2').src = image2;
					document.getElementById('sourceStrength1').textContent = strength1;
					document.getElementById('sourceStrength2').textContent = strength2;
					
					// Show the modal
					sourceImagesModal.show();
				});
			});
			
			// Toggle select all
			selectAllBtn.addEventListener('click', function() {
				const allSelected = Array.from(checkboxes).every(cb => cb.checked);
				checkboxes.forEach(checkbox => {
					checkbox.checked = !allSelected;
					toggleCardSelection(checkbox);
				});
				updateBulkDeleteButton();
			});
			
			// Handle checkbox changes
			checkboxes.forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					toggleCardSelection(this);
					updateBulkDeleteButton();
				});
			});
			
			// Toggle card selection visual
			function toggleCardSelection(checkbox) {
				const card = checkbox.closest('.card');
				if (checkbox.checked) {
					card.classList.add('selected');
				} else {
					card.classList.remove('selected');
				}
			}
			
			// Update bulk delete button state
			function updateBulkDeleteButton() {
				const selectedCount = document.querySelectorAll('.image-checkbox:checked').length;
				bulkDeleteBtn.disabled = selectedCount === 0;
				document.getElementById('deleteCount').textContent = selectedCount;
			}
			
			// Show confirmation modal for bulk delete
			bulkDeleteBtn.addEventListener('click', function() {
				deleteConfirmModal.show();
			});
			
			// Handle bulk delete confirmation
			confirmBulkDeleteBtn.addEventListener('click', async function() {
				const selectedPromptIds = Array.from(document.querySelectorAll('.image-checkbox:checked'))
					.map(checkbox => checkbox.dataset.promptId);
				
				if (selectedPromptIds.length === 0) return;
				
				try {
					const response = await fetch('/prompts/bulk-delete', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
						},
						body: JSON.stringify({prompt_ids: selectedPromptIds})
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Remove deleted images from the UI
						selectedPromptIds.forEach(id => {
							const checkbox = document.querySelector(`.image-checkbox[data-prompt-id="${id}"]`);
							if (checkbox) {
								checkbox.closest('.col-md-3').remove();
							}
						});
						deleteConfirmModal.hide();
						alert(`Successfully deleted ${selectedPromptIds.length} images`);
					} else {
						alert('Error: ' + (data.message || 'Failed to delete images'));
					}
				} catch (error) {
					console.error('Error during bulk delete:', error);
					alert('Error deleting images');
				}
			});
			
			// Add event listeners for update notes buttons
			document.querySelectorAll('.update-notes-btn').forEach(button => {
				button.addEventListener('click', async function () {
					const promptId = this.dataset.promptId;
					const notesInput = document.querySelector(`.notes-input[data-prompt-id="${promptId}"]`);
					const notes = notesInput.value;
					
					try {
						const response = await fetch(`/images/${promptId}/update-notes`, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
							},
							body: JSON.stringify({notes})
						});
						
						const data = await response.json();
						
						if (data.message) {
							// Show success message
							alert('Notes updated successfully');
						}
					} catch (error) {
						console.error('Error updating notes:', error);
						alert('Error updating notes');
					}
				});
			});
			
			document.querySelectorAll('.upscale-btn').forEach(button => {
				button.addEventListener('click', async function () {
					const promptId = this.dataset.promptId;
					const filename = this.dataset.filename;
					const statusDiv = document.getElementById(`upscale-status-${promptId}`);
					
					// Remove the upscale button
					this.remove();
					statusDiv.innerHTML = 'Upscaling in progress...';
					
					try {
						const response = await fetch(`/images/${promptId}/upscale`, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
							},
							body: JSON.stringify({filename})
						});
						
						const data = await response.json();
						
						if (data.prediction_id) {
							// Start checking status
							const checkStatus = async () => {
								const statusResponse = await fetch(`/images/${promptId}/upscale-status/${data.prediction_id}`);
								const statusData = await statusResponse.json();
								
								if (statusData.message === 'Upscale in progress.') {
									statusDiv.innerHTML = 'Still processing...';
									setTimeout(checkStatus, 5000); // Check again in 5 seconds
								} else if (statusData.upscale_result) {
									statusDiv.innerHTML = `
                                    <a href="${statusData.upscale_result}" class="btn btn-info btn-sm" target="_blank">
                                        View Upscaled Image
                                    </a>`;
									location.reload(); // Refresh the page to show updated status
								} else {
									statusDiv.innerHTML = 'Upscale failed: ' + (statusData.error || 'Unknown error');
								}
							};
							
							setTimeout(checkStatus, 5000); // Start checking after 5 seconds
						}
					} catch (error) {
						console.error('Error starting upscale:', error);
						statusDiv.innerHTML = 'Error starting upscale process';
					}
				});
			});
			
			document.querySelectorAll('.delete-image-btn').forEach(button => {
				button.addEventListener('click', async function () {
					if (!confirm('Are you sure you want to delete this image?')) return;
					
					const promptId = this.dataset.promptId;
					
					try {
						const response = await fetch(`/prompts/${promptId}`, {
							method: 'DELETE',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
							}
						});
						
						const data = await response.json();
						
						if (data.success) {
							// Remove the image card from the UI
							this.closest('.col-md-3').remove();
							alert('Image deleted successfully');
						} else {
							alert('Error: ' + (data.message || 'Failed to delete image'));
						}
					} catch (error) {
						console.error('Error deleting image:', error);
						alert('Error deleting image');
					}
				});
			});
		});
	</script>
@endsection
