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
											<div class="card-body">
												<p class="card-text small text-muted fst-italic" id="prompt-text-{{ $image->id }}">"{{ $image->mix_prompt ?? 'No Prompt'}}"</p>
												<!-- Edit Button Added Here -->
												<button type="button" class="btn btn-outline-secondary btn-sm edit-prompt-btn"
												        data-bs-toggle="modal" data-bs-target="#editPromptModal"
												        data-cover-id="{{ $image->id }}"
												        data-prompt="{{ $image->mix_prompt }}">
													Edit
												</button>
											</div>
										<div class="card-footer text-center">
											<label class="form-label fw-bold">Kontext</label>
											<div class="btn-group btn-group-sm kontext-controls" role="group" data-cover-id="{{ $image->id }}">
												<button type="button" class="btn btn-primary kontext-btn" data-model="dev" @if(!$image->mix_prompt) disabled title="No mix prompt available" @endif>dev</button>
												<button type="button" class="btn btn-secondary kontext-btn" data-model="pro" @if(!$image->mix_prompt) disabled title="No mix prompt available" @endif>pro</button>
												<button type="button" class="btn btn-success kontext-btn" data-model="max" @if(!$image->mix_prompt) disabled title="No mix prompt available" @endif>max</button>
											</div>
											<div class="kontext-status mt-2 small" id="kontext-status-{{ $image->id }}"></div>
											<div class="kontext-result mt-2" id="kontext-result-{{ $image->id }}">
												@if($image->kontext_path)
													<a href="{{ Storage::url($image->kontext_path) }}" target="_blank" title="View full size">
														<img src="{{ Storage::url($image->kontext_path) }}" class="img-fluid rounded mt-2" alt="Kontext Result">
													</a>
												@endif
											</div>
										</div>
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
	
	<!-- Edit Prompt Modal (New) -->
	<div class="modal fade" id="editPromptModal" tabindex="-1" aria-labelledby="editPromptModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="editPromptModalLabel">Edit Mix Prompt</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="edit-prompt-form">
					<div class="modal-body">
						<input type="hidden" id="edit-cover-id" name="cover_id">
						<div class="mb-3">
							<label for="edit-prompt-text" class="form-label">Prompt Text:</label>
							<textarea class="form-control" id="edit-prompt-text" name="prompt_text" rows="6"></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary" id="save-prompt-btn">Save Changes</button>
					</div>
				</form>
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
          border: 2px solid #198754;
          /* success green */
          box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
      }
	</style>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// --- Existing Script for Generate Prompts & Kontext ---
			// (The script from the previous step goes here, unchanged)
			const generateBtn = document.getElementById('generatePromptsBtn');
			const modalElement = document.getElementById('generatePromptsModal');
			if (generateBtn && modalElement) {
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
					updateCardSelection(checkbox);
					checkbox.addEventListener('change', () => {
						updateCardSelection(checkbox);
						updateSelectedCount();
					});
				});
				
				selectAllCheckbox.addEventListener('click', function() {
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
			}
			
			document.body.addEventListener('click', function(event) {
				if (event.target.classList.contains('kontext-btn')) {
					handleKontextClick(event.target);
				}
			});
			
			const pollingIntervals = {};
			
			async function handleKontextClick(button) {
				const model = button.dataset.model;
				const controls = button.closest('.kontext-controls');
				const coverId = controls.dataset.coverId;
				const statusDiv = document.getElementById(`kontext-status-${coverId}`);
				const resultDiv = document.getElementById(`kontext-result-${coverId}`);
				controls.querySelectorAll('.kontext-btn').forEach(btn => btn.disabled = true);
				statusDiv.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...`;
				resultDiv.innerHTML = '';
				try {
					const response = await fetch('{{ route("album-covers.kontext.generate") }}', {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
							'Accept': 'application/json',
							'Content-Type': 'application/json',
						},
						body: JSON.stringify({
							cover_id: coverId,
							model_type: model,
						})
					});
					const data = await response.json();
					if (!response.ok || !data.success) {
						throw new Error(data.message || 'Failed to start generation.');
					}
					statusDiv.textContent = 'Job queued. Waiting for result...';
					pollStatus(data.request_id, model, coverId);
				} catch (error) {
					console.error('Error starting Kontext generation:', error);
					statusDiv.innerHTML = `<span class="text-danger">Error: ${error.message}</span>`;
					controls.querySelectorAll('.kontext-btn').forEach(btn => {
						if (btn.title !== "No mix prompt available") btn.disabled = false;
					});
				}
			}
			
			function pollStatus(requestId, model, coverId) {
				const controls = document.querySelector(`.kontext-controls[data-cover-id="${coverId}"]`);
				const statusDiv = document.getElementById(`kontext-status-${coverId}`);
				const resultDiv = document.getElementById(`kontext-result-${coverId}`);
				if (pollingIntervals[coverId]) {
					clearInterval(pollingIntervals[coverId]);
				}
				pollingIntervals[coverId] = setInterval(async () => {
					try {
						const response = await fetch('{{ route("album-covers.kontext.status") }}', {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json',
								'Content-Type': 'application/json',
							},
							body: JSON.stringify({
								request_id: requestId,
								model_type: model,
								cover_id: coverId
							})
						});
						const data = await response.json();
						if (!response.ok) {
							throw new Error(data.message || 'Failed to check status.');
						}
						if (data.status === 'completed') {
							clearInterval(pollingIntervals[coverId]);
							delete pollingIntervals[coverId];
							statusDiv.innerHTML = `<span class="text-success">Completed!</span>`;
							resultDiv.innerHTML = ` <a href="${data.image_url}" target="_blank" title="View full size"> <img src="${data.image_url}" class="img-fluid rounded mt-2" alt="Kontext Result"> </a>`;
							controls.querySelectorAll('.kontext-btn').forEach(btn => {
								if (btn.title !== "No mix prompt available") btn.disabled = false;
							});
						} else if (data.status === 'processing') {
							statusDiv.textContent = 'Processing...';
						} else if (data.status === 'error') {
							throw new Error(data.message || 'An error occurred during processing.');
						}
					} catch (error) {
						clearInterval(pollingIntervals[coverId]);
						delete pollingIntervals[coverId];
						console.error('Error polling status:', error);
						statusDiv.innerHTML = `<span class="text-danger">Error: ${error.message}</span>`;
						controls.querySelectorAll('.kontext-btn').forEach(btn => {
							if (btn.title !== "No mix prompt available") btn.disabled = false;
						});
					}
				}, 3000);
			}
			
			// --- New Script for Edit Prompt Modal ---
			const editPromptModalEl = document.getElementById('editPromptModal');
			const editPromptForm = document.getElementById('edit-prompt-form');
			const editPromptModal = new bootstrap.Modal(editPromptModalEl);
			
			// Use event delegation to handle clicks on any "Edit" button
			document.body.addEventListener('click', function(event) {
				if (event.target.classList.contains('edit-prompt-btn')) {
					const button = event.target;
					const coverId = button.dataset.coverId;
					const promptText = button.dataset.prompt;
					
					// Populate the modal
					editPromptModalEl.querySelector('#edit-cover-id').value = coverId;
					editPromptModalEl.querySelector('#edit-prompt-text').value = promptText;
				}
			});
			
			// Handle the form submission
			editPromptForm.addEventListener('submit', async function(e) {
				e.preventDefault();
				const saveBtn = this.querySelector('#save-prompt-btn');
				const originalBtnText = saveBtn.textContent;
				saveBtn.disabled = true;
				saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...`;
				
				const coverId = this.querySelector('#edit-cover-id').value;
				const newPromptText = this.querySelector('#edit-prompt-text').value;
				
				// Use a template for the URL and replace the ID
				let urlTemplate = '{{ route("album-covers.update-prompt", ["cover" => ":id"]) }}';
				let url = urlTemplate.replace(':id', coverId);
				
				try {
					const response = await fetch(url, {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
							'Accept': 'application/json',
							'Content-Type': 'application/json',
						},
						body: JSON.stringify({
							prompt_text: newPromptText
						})
					});
					
					const data = await response.json();
					
					if (response.ok && data.success) {
						// Update the prompt text on the page
						document.getElementById(`prompt-text-${coverId}`).textContent = `"${newPromptText}"`;
						// Update the data attribute on the button for the next edit
						document.querySelector(`.edit-prompt-btn[data-cover-id="${coverId}"]`).dataset.prompt = newPromptText;
						
						editPromptModal.hide();
					} else {
						alert('Error: ' + (data.message || 'Failed to update prompt.'));
					}
					
				} catch (error) {
					console.error('Error updating prompt:', error);
					alert('An unexpected error occurred.');
				} finally {
					saveBtn.disabled = false;
					saveBtn.textContent = originalBtnText;
				}
			});
		});
	</script>
@endsection
