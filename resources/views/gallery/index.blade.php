@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<h3 class="mb-0">Image Gallery</h3>
			</div>
			<div class="card-body">
				<div class="row">
					@foreach($images as $image)
						<div class="col-md-4 mb-4">
							<div class="card">
								<img src="{{ $image->thumbnail }}"
								     class="card-img-top cursor-pointer"
								     onclick="openImageModal('{{ $image->filename }}')"
								     alt="Generated Image">
								<div class="card-body">
									<div class="mb-2">
										<small class="text-muted">{{ $image->created_at->format('Y-m-d H:i') }}</small>
									</div>
									<div class="mb-2">
                                    <textarea class="form-control notes-input"
                                              placeholder="Add notes..."
                                              data-prompt-id="{{ $image->id }}">{{ $image->notes }}</textarea>
									</div>
									<button class="btn btn-primary btn-sm update-notes-btn mb-2"
									        data-prompt-id="{{ $image->id }}">
										Update Notes
									</button>
									
									@if($image->upscale_status === 0)
										<button class="btn btn-success btn-sm upscale-btn mb-2"
										        data-prompt-id="{{ $image->id }}"
										        data-filename="{{ $image->filename }}">
											Upscale Image
										</button>
									@elseif($image->upscale_status === 1)
										<div class="text-warning">Upscale in progress...</div>
									@elseif($image->upscale_status === 2)
										<a href="/storage/upscaled/{{ $image->upscale_url }}"
										   class="btn btn-info btn-sm mb-2"
										   target="_blank">
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
					{{ $images->links('pagination::bootstrap-5') }}
				</div>
				
				<div class="mt-4">
					<a href="{{ route('home') }}" class="btn btn-secondary">Back to Home</a>
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

@endsection

@section('styles')
	<link rel="stylesheet" href="{{ asset('css/style.css') }}">
@endsection

@section('scripts')
	<script src="{{ asset('js/bootstrap.min.js') }}"></script>
	<script src="{{ asset('js/image-results-script.js') }}"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Add event listeners for update notes buttons
			document.querySelectorAll('.update-notes-btn').forEach(button => {
				button.addEventListener('click', async function() {
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
				button.addEventListener('click', async function() {
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
							body: JSON.stringify({ filename })
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
		});
	</script>
@endsection
