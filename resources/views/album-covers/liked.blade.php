@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-center">
					<h3 class="mb-0">Liked Album Covers</h3>
					<div>
						<button id="generatePromptsBtn" class="btn btn-success btn-sm me-2">Generate Prompts for Selected</button>
						<a href="{{ route('album-covers.index') }}" class="btn btn-outline-secondary btn-sm">Back to Folders</a>
					</div>
				</div>
			</div>
			<div class="card-body">
				@if($likedImages->isEmpty())
					<p>You have not marked any album covers as 'liked' yet.</p>
				@else
					<form id="generate-prompts-form">
						<div class="d-flex align-items-center mb-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="selectAllCheckbox" style="transform: scale(1.5);">
								<label class="form-check-label ms-2" for="selectAllCheckbox">
									Select All on Page
								</label>
							</div>
						</div>
						<div class="row">
							@foreach($likedImages as $image)
								<div class="col-md-3 mb-4">
									<div class="card h-100 image-card">
										<div class="position-absolute top-0 start-0 m-2" style="z-index: 10;">
											<input type="checkbox" class="form-check-input image-checkbox" name="cover_ids[]" value="{{ $image->id }}" style="transform: scale(1.5);">
										</div>
										<img src="{{ $cloudfrontUrl }}/{{ $image->album_path }}" class="card-img-top" alt="Liked Album Cover">
										@if($image->mix_prompt)
											<div class="card-body">
												<p class="card-text small text-muted fst-italic">"{{ $image->mix_prompt }}"</p>
											</div>
										@endif
									</div>
								</div>
							@endforeach
						</div>
					</form>
					<div class="mt-4">
						{{ $likedImages->links('pagination::bootstrap-5') }}
					</div>
				@endif
			</div>
		</div>
	</div>
	
	<!-- Generate Prompts Modal -->
	<div class="modal fade" id="generatePromptsModal" tabindex="-1" aria-labelledby="generatePromptsModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="generatePromptsModalLabel">Generate Prompts</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p>The following prompt will be sent to the AI for each selected image. You can edit it below.</p>
					<form id="modal-prompt-form">
						<div class="mb-3">
							<label for="prompt-text" class="form-label">Prompt Text:</label>
							<textarea class="form-control" id="prompt-text" name="prompt_text" rows="6">{{ $defaultPromptText ?? '' }}</textarea>
						</div>
						<p class="text-muted">Selected images: <span id="selected-count">0</span></p>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="submit-prompts-btn">Generate</button>
				</div>
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
          border: 2px solid #198754; /* success green */
          box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
      }
	</style>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const generateBtn = document.getElementById('generatePromptsBtn');
			const modalElement = document.getElementById('generatePromptsModal');
			if (!generateBtn || !modalElement) return;
			
			const generatePromptsModal = new bootstrap.Modal(modalElement);
			const selectAllCheckbox = document.getElementById('selectAllCheckbox');
			const imageCheckboxes = document.querySelectorAll('.image-checkbox');
			const selectedCountSpan = document.getElementById('selected-count');
			const submitPromptsBtn = document.getElementById('submit-prompts-btn');
			const promptTextarea = document.getElementById('prompt-text');
			
			function updateCardSelection(checkbox) {
				const card = checkbox.closest('.image-card');
				if (checkbox.checked) {
					card.classList.add('selected');
				} else {
					card.classList.remove('selected');
				}
			}
			
			function updateSelectedCount() {
				const count = document.querySelectorAll('.image-checkbox:checked').length;
				selectedCountSpan.textContent = count;
				return count;
			}
			
			imageCheckboxes.forEach(checkbox => {
				updateCardSelection(checkbox); // Initial state
				checkbox.addEventListener('change', () => {
					updateCardSelection(checkbox);
					updateSelectedCount();
				});
			});
			
			selectAllCheckbox.addEventListener('click', function () {
				imageCheckboxes.forEach(checkbox => {
					checkbox.checked = this.checked;
					updateCardSelection(checkbox);
				});
				updateSelectedCount();
			});
			
			generateBtn.addEventListener('click', function() {
				const selectedCount = updateSelectedCount();
				if (selectedCount === 0) {
					alert('Please select at least one image to generate prompts for.');
					return;
				}
				generatePromptsModal.show();
			});
			
			submitPromptsBtn.addEventListener('click', async function() {
				this.disabled = true;
				this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`;
				
				const selectedCheckboxes = document.querySelectorAll('.image-checkbox:checked');
				const coverIds = Array.from(selectedCheckboxes).map(cb => cb.value);
				const promptText = promptTextarea.value;
				
				const formData = new FormData();
				coverIds.forEach(id => formData.append('cover_ids[]', id));
				formData.append('prompt_text', promptText);
				
				try {
					const response = await fetch('{{ route("album-covers.generate-prompts") }}', {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
							'Accept': 'application/json',
						},
						body: formData
					});
					
					const data = await response.json();
					
					if (response.ok && data.success) {
						alert(data.message || 'Prompts generated successfully!');
						generatePromptsModal.hide();
						location.reload();
					} else {
						alert('Error: ' + (data.message || 'Failed to generate prompts.'));
					}
				} catch (error) {
					console.error('Error generating prompts:', error);
					alert('An unexpected error occurred.');
				} finally {
					this.disabled = false;
					this.textContent = 'Generate';
				}
			});
		});
	</script>
@endsection
