@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-center">
					<h3 class="mb-0">Liked Covers</h3>
					<div class="d-flex align-items-center">
						<!-- Sorting Dropdown -->
						<form action="{{ route('album-covers.liked') }}" method="GET" id="sort-form" class="me-2">
							<select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
								<option value="updated_at" {{ ($sort ?? 'updated_at') == 'updated_at' ? 'selected' : '' }}>Sort by
									Updated
								</option>
								<option value="created_at" {{ ($sort ?? 'updated_at') == 'created_at' ? 'selected' : '' }}>Sort by
									Added
								</option>
							</select>
						</form>
						
						<!-- START MODIFICATION: Add a button to toggle viewing only generated cards -->
						<button type="button" id="toggleGeneratedBtn" class="btn btn-outline-info btn-sm me-2">Show Generated Only
						</button>
						<!-- END MODIFICATION -->
						
						<!-- Original Buttons -->
						<button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal"
						        data-bs-target="#uploadCoverModal">
							Upload Cover
						</button>
						<button id="generatePromptsBtn" class="btn btn-success btn-sm me-2">Generate Prompts for Selected</button>
						<a href="{{ route('album-covers.index') }}" class="btn btn-outline-secondary btn-sm">Back to Folders</a>
					</div>
				</div>
			</div>
			<div class="card-body">
				@if($likedImages->isEmpty())
					<p>You have not marked any covers as 'liked' yet.</p>
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
								{{-- START MODIFICATION: Add a data-attribute to the column for filtering logic and a class to the card for styling --}}
								<div class="col-md-3 mb-4" data-has-generated="{{ $image->kontext_path ? 'true' : 'false' }}">
									<div class="card h-100 image-card original-cover-card">
										<div class="card-body p-0">
											<div class="position-absolute top-0 start-0 m-2" style="z-index: 10;">
												<input type="checkbox" class="form-check-input image-checkbox" name="cover_ids[]"
												       value="{{ $image->id }}" style="transform: scale(1.5);">
											</div>
											@php
												$imageUrl = $image->image_source === 's3' ? ($cloudfrontUrl . '/' . $image->album_path) : Storage::url($image->album_path);
											@endphp
											<a target="_blank" href="{{ $imageUrl }}"><img src="{{ $imageUrl }}" class="card-img-top"
											                                               alt="Liked Album Cover"></a>
											<p class="card-text small text-muted fst-italic" id="prompt-text-{{ $image->id }}">
												"{{ $image->mix_prompt ?? 'No Prompt'}}"</p>
											<button type="button" class="btn btn-outline-secondary btn-sm edit-prompt-btn"
											        data-bs-toggle="modal" data-bs-target="#editPromptModal" data-cover-id="{{ $image->id }}"
											        data-prompt="{{ $image->mix_prompt }}">
												Edit
											</button>
										</div>
										<div class="card-footer text-center">
											<label class="form-label fw-bold">Kontext</label>
											<div class="btn-group btn-group-sm kontext-controls" role="group"
											     data-cover-id="{{ $image->id }}">
												<button type="button" class="btn btn-primary kontext-btn" data-model="dev"
												        @if(!$image->mix_prompt) disabled title="No mix prompt available" @endif>dev
												</button>
												<button type="button" class="btn btn-secondary kontext-btn" data-model="pro"
												        @if(!$image->mix_prompt) disabled title="No mix prompt available" @endif>pro
												</button>
												<button type="button" class="btn btn-success kontext-btn" data-model="max"
												        @if(!$image->mix_prompt) disabled title="No mix prompt available" @endif>max
												</button>
											</div>
											<div class="kontext-status mt-2 small" id="kontext-status-{{ $image->id }}"></div>
											<div class="kontext-result mt-2" id="kontext-result-{{ $image->id }}">
												@if($image->kontext_path)
													<a href="{{ Storage::url($image->kontext_path) }}" target="_blank" title="View full size">
														<img src="{{ Storage::url($image->kontext_path) }}" class="img-fluid rounded mt-2"
														     alt="Kontext Result">
													</a>
												@endif
											</div>
											
											<div class="mt-2">
												<textarea class="form-control form-control-sm notes-textarea" placeholder="Add notes..."
												          data-cover-id="{{ $image->id }}" rows="2">{{ $image->notes }}</textarea>
												<button type="button" class="btn btn-outline-primary btn-sm mt-1 update-notes-btn"
												        data-cover-id="{{ $image->id }}">Save Notes
												</button>
											</div>
											
											
											<!-- Upscale Section -->
											<div class="mt-3 border-top pt-2">
												<label class="form-label fw-bold">Upscale</label>
												<div id="upscale-controls-{{ $image->id }}">
													@if($image->kontext_path)
														{{-- This logic only runs if a Kontext image exists --}}
														@if(is_null($image->upscale_status) || $image->upscale_status == 0)
															<button type="button" class="btn btn-success btn-sm upscale-btn"
															        data-cover-id="{{ $image->id }}">Upscale Image
															</button>
														@elseif($image->upscale_status == 1)
															<div class="text-warning">Upscaling in progress...</div>
															<script>
																document.addEventListener('DOMContentLoaded', function () {
																	if (typeof pollUpscaleStatus === 'function') {
																		pollUpscaleStatus('{{ $image->id }}', '{{ $image->upscale_prediction_id }}');
																	}
																});
															</script>
														@elseif($image->upscale_status == 2)
															<a href="{{ Storage::url($image->upscaled_path) }}" class="btn btn-info btn-sm"
															   target="_blank">View/Download Upscaled</a>
															<button type="button" class="btn btn-warning btn-sm upscale-btn ms-1"
															        data-cover-id="{{ $image->id }}">Redo Upscale
															</button>
														@elseif($image->upscale_status == 3)
															<div class="text-danger">Upscale failed.</div>
															<button type="button" class="btn btn-success btn-sm upscale-btn"
															        data-cover-id="{{ $image->id }}">Retry Upscale
															</button>
														@endif
													@else
														{{-- If no Kontext image, show a disabled button --}}
														<button type="button" class="btn btn-success btn-sm" disabled
														        title="Generate a Kontext image first to enable upscaling">Upscale Image
														</button>
													@endif
												</div>
												<div class="upscale-status mt-2 small" id="upscale-status-{{ $image->id }}"></div>
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
	
	<!-- Upload Cover Modal -->
	<div class="modal fade" id="uploadCoverModal" tabindex="-1" aria-labelledby="uploadCoverModalLabel"
	     aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="uploadCoverModalLabel">Upload New Album Cover</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="upload-cover-form" action="{{ route('album-covers.upload') }}" method="POST"
				      enctype="multipart/form-data">
					@csrf
					<div class="modal-body">
						<div class="mb-3">
							<label for="cover_file_input" class="form-label">Select image file</label>
							<input class="form-control" type="file" id="cover_file_input" name="cover_file" required accept="image/*">
						</div>
						<div id="upload-status" class="mt-3"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						<button type="submit" class="btn btn-primary" id="submit-upload-btn">Upload</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	
	<!-- Generate Prompts Modal -->
	<div class="modal fade" id="generatePromptsModal" tabindex="-1" aria-labelledby="generatePromptsModalLabel"
	     aria-hidden="true">
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
							<textarea class="form-control" id="prompt-text" name="prompt_text"
							          rows="6">{{ $defaultPromptText ?? '' }}</textarea>
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
          border: 2px solid #198754; /* success green */
          box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
      }

      /* START MODIFICATION: Add background for original cover cards that adapts to theme */
      .original-cover-card {
          background-color: var(--bs-tertiary-bg);
      }

      /* END MODIFICATION */
	</style>
@endsection

@section('scripts')
	<script>
		// --- Upscale Polling Function (defined globally to be accessible by inline scripts) ---
		const upscalePollingIntervals = {};
		
		function pollUpscaleStatus(coverId, predictionId) {
			const controlsDiv = document.getElementById(`upscale-controls-${coverId}`);
			const statusDiv = document.getElementById(`upscale-status-${coverId}`);
			
			if (upscalePollingIntervals[coverId]) {
				clearInterval(upscalePollingIntervals[coverId]);
			}
			
			upscalePollingIntervals[coverId] = setInterval(async () => {
				let urlTemplate = '{{ route("album-covers.upscale.status", ["cover" => ":id", "prediction_id" => ":pid"]) }}';
				let url = urlTemplate.replace(':id', coverId).replace(':pid', predictionId);
				try {
					const response = await fetch(url, {
						method: 'GET',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
							'Accept': 'application/json',
						}
					});
					const data = await response.json();
					if (!response.ok) {
						throw new Error(data.message || 'Failed to check status.');
					}
					
					if (data.status === 'completed') {
						clearInterval(upscalePollingIntervals[coverId]);
						delete upscalePollingIntervals[coverId];
						controlsDiv.innerHTML = `<a href="${data.image_url}" class="btn btn-info btn-sm" target="_blank">View/Download Upscaled</a><button type="button" class="btn btn-warning btn-sm upscale-btn ms-1" data-cover-id="${coverId}">Redo Upscale</button>`;
						statusDiv.innerHTML = `<span class="text-success">Completed!</span>`;
					} else if (data.status === 'processing') {
						statusDiv.textContent = 'Still processing...';
					} else if (data.status === 'error') {
						throw new Error(data.message || 'An error occurred during processing.');
					}
				} catch (error) {
					clearInterval(upscalePollingIntervals[coverId]);
					delete upscalePollingIntervals[coverId];
					console.error('Error polling upscale status:', error);
					controlsDiv.innerHTML = `<div class="text-danger">Upscale failed.</div><button type="button" class="btn btn-success btn-sm upscale-btn mt-1" data-cover-id="${coverId}">Retry Upscale</button>`;
					statusDiv.innerHTML = `<span class="text-danger">Error: ${error.message}</span>`;
				}
			}, 5000); // Poll every 5 seconds
		}
		
		
		document.addEventListener('DOMContentLoaded', function () {
			// --- START MODIFICATION: Script to toggle card body visibility ---
			const toggleBtn = document.getElementById('toggleGeneratedBtn');
			if (toggleBtn) {
				let showGeneratedOnly = false; // Initial state: everything is expanded.
				
				toggleBtn.addEventListener('click', function () {
					showGeneratedOnly = !showGeneratedOnly; // Toggle state.
					
					// Find all columns containing a card.
					const allCardColumns = document.querySelectorAll('.col-md-3[data-has-generated]');
					
					allCardColumns.forEach(column => {
						// Find the card body within this column's card.
						const cardBody = column.querySelector('.card-body');
						if (!cardBody) {
							return; // Skip if the structure is not as expected.
						}
						
						if (showGeneratedOnly) {
							// When "Show Generated Only" mode is active...
							if (column.dataset.hasGenerated === 'false') {
								// ...hide the body of cards that have NOT been generated.
								// This keeps the footer controls visible but de-clutters the view.
								cardBody.style.display = 'none';
							} else {
								// For cards that HAVE been generated, ensure their body is visible
								// for comparison with the generated result in the footer.
								cardBody.style.display = '';
							}
						} else {
							// When toggled back to "Show All", ensure all card bodies are visible again.
							cardBody.style.display = '';
						}
					});
					
					// Update button text and style to reflect the current state.
					if (showGeneratedOnly) {
						this.textContent = 'Show All Covers';
						this.classList.remove('btn-outline-info');
						this.classList.add('btn-info');
					} else {
						this.textContent = 'Show Generated Only';
						this.classList.remove('btn-info');
						this.classList.add('btn-outline-info');
					}
				});
			}
			// --- END MODIFICATION ---
			
			// --- Existing Script for Generate Prompts & Kontext ---
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
				
				selectAllCheckbox.addEventListener('click', function () {
					imageCheckboxes.forEach(checkbox => {
						checkbox.checked = this.checked;
						updateCardSelection(checkbox);
					});
					updateSelectedCount();
				});
				
				generateBtn.addEventListener('click', function () {
					const selectedCount = updateSelectedCount();
					if (selectedCount === 0) {
						alert('Please select at least one image to generate prompts for.');
						return;
					}
					generatePromptsModal.show();
				});
				
				submitPromptsBtn.addEventListener('click', async function () {
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
			
			document.body.addEventListener('click', function (event) {
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
							resultDiv.innerHTML = `
                        <a href="${data.image_url}" target="_blank" title="View full size">
                            <img src="${data.image_url}" class="img-fluid rounded mt-2" alt="Kontext Result">
                        </a>`;
							controls.querySelectorAll('.kontext-btn').forEach(btn => {
								if (btn.title !== "No mix prompt available") btn.disabled = false;
							});
							
							// Enable the upscale button now that a Kontext image exists
							const upscaleControlsDiv = document.getElementById(`upscale-controls-${coverId}`);
							if (upscaleControlsDiv) {
								upscaleControlsDiv.innerHTML = `<button type="button" class="btn btn-success btn-sm upscale-btn" data-cover-id="${coverId}">Upscale Image</button>`;
							}
							
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
			
			document.body.addEventListener('click', function (event) {
				if (event.target.classList.contains('edit-prompt-btn')) {
					const button = event.target;
					const coverId = button.dataset.coverId;
					const promptText = button.dataset.prompt;
					editPromptModalEl.querySelector('#edit-cover-id').value = coverId;
					editPromptModalEl.querySelector('#edit-prompt-text').value = promptText;
				}
			});
			
			editPromptForm.addEventListener('submit', async function (e) {
				e.preventDefault();
				const saveBtn = this.querySelector('#save-prompt-btn');
				const originalBtnText = saveBtn.textContent;
				saveBtn.disabled = true;
				saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...`;
				
				const coverId = this.querySelector('#edit-cover-id').value;
				const newPromptText = this.querySelector('#edit-prompt-text').value;
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
						body: JSON.stringify({prompt_text: newPromptText})
					});
					const data = await response.json();
					if (response.ok && data.success) {
						document.getElementById(`prompt-text-${coverId}`).textContent = `"${newPromptText}"`;
						document.querySelector(`.edit-prompt-btn[data-cover-id="${coverId}"]`).dataset.prompt = newPromptText;
						
						// If a prompt now exists, enable the Kontext buttons
						if (newPromptText.trim()) {
							const kontextControls = document.querySelector(`.kontext-controls[data-cover-id="${coverId}"]`);
							if (kontextControls) {
								kontextControls.querySelectorAll('.kontext-btn').forEach(btn => {
									btn.disabled = false;
									btn.removeAttribute('title');
								});
							}
						}
						
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
			
			// --- New Script for Notes ---
			document.body.addEventListener('click', async function (event) {
				if (event.target.classList.contains('update-notes-btn')) {
					const button = event.target;
					const coverId = button.dataset.coverId;
					const textarea = document.querySelector(`.notes-textarea[data-cover-id="${coverId}"]`);
					const notes = textarea.value;
					const originalBtnText = 'Save Notes';
					button.disabled = true;
					button.textContent = 'Saving...';
					
					let urlTemplate = '{{ route("album-covers.update-notes", ["cover" => ":id"]) }}';
					let url = urlTemplate.replace(':id', coverId);
					
					try {
						const response = await fetch(url, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json',
								'Content-Type': 'application/json',
							},
							body: JSON.stringify({notes: notes})
						});
						const data = await response.json();
						if (response.ok && data.success) {
							button.textContent = 'Saved!';
							setTimeout(() => {
								button.textContent = originalBtnText;
								button.disabled = false;
							}, 2000);
						} else {
							alert('Error: ' + (data.message || 'Failed to save notes.'));
							button.disabled = false;
							button.textContent = originalBtnText;
						}
					} catch (error) {
						console.error('Error updating notes:', error);
						alert('An unexpected error occurred.');
						button.disabled = false;
						button.textContent = originalBtnText;
					}
				}
			});
			
			// --- New Script for Upscaling ---
			document.body.addEventListener('click', async function (event) {
				if (event.target.classList.contains('upscale-btn')) {
					const button = event.target;
					const coverId = button.dataset.coverId;
					const controlsDiv = document.getElementById(`upscale-controls-${coverId}`);
					const statusDiv = document.getElementById(`upscale-status-${coverId}`);
					
					button.disabled = true;
					controlsDiv.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Starting upscale...`;
					statusDiv.innerHTML = '';
					
					let urlTemplate = '{{ route("album-covers.upscale", ["cover" => ":id"]) }}';
					let url = urlTemplate.replace(':id', coverId);
					
					try {
						const response = await fetch(url, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json',
							}
						});
						const data = await response.json();
						if (!response.ok || !data.success) {
							throw new Error(data.message || 'Failed to start upscale process.');
						}
						controlsDiv.innerHTML = `<div class="text-warning">Upscaling in progress...</div>`;
						pollUpscaleStatus(coverId, data.prediction_id);
					} catch (error) {
						console.error('Error starting upscale:', error);
						controlsDiv.innerHTML = `<div class="text-danger">Error: ${error.message}</div><button type="button" class="btn btn-success btn-sm upscale-btn mt-1" data-cover-id="${coverId}">Retry Upscale</button>`;
					}
				}
			});
			
			const uploadForm = document.getElementById('upload-cover-form');
			if (uploadForm) {
				uploadForm.addEventListener('submit', async function (e) {
					e.preventDefault();
					const submitBtn = document.getElementById('submit-upload-btn');
					const statusDiv = document.getElementById('upload-status');
					const originalBtnText = submitBtn.innerHTML;
					submitBtn.disabled = true;
					submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...`;
					statusDiv.innerHTML = '';
					
					const formData = new FormData(uploadForm);
					
					try {
						const response = await fetch(uploadForm.action, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json',
							},
							body: formData
						});
						const data = await response.json();
						if (response.ok && data.success) {
							statusDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
							setTimeout(() => {
								location.reload();
							}, 1500);
						} else {
							throw new Error(data.message || 'Upload failed.');
						}
					} catch (error) {
						console.error('Upload error:', error);
						statusDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
						submitBtn.disabled = false;
						submitBtn.innerHTML = originalBtnText;
					}
				});
			}
		});
	</script>
@endsection
