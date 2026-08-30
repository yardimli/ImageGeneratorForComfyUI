@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-center">
					<h3 class="mb-0">Queued Prompts</h3>
					@if($queuedPrompts->count() > 0)
						<button id="deleteAllBtn" class="btn btn-danger">Delete All Queued Items</button>
					@endif
				</div>
			</div>
			<div class="card-body">
				@if($queuedPrompts->count() > 0)
					<div class="table-responsive">
						<table class="table table-striped">
							<thead>
							<tr>
								<th>Created</th>
								<th>Model</th>
								<th>Prompt</th>
								<th>Size</th>
								<th>Actions</th>
							</tr>
							</thead>
							<tbody>
							@foreach($queuedPrompts as $prompt)
								<tr>
									<td>{{ $prompt->created_at->format('Y-m-d H:i') }}</td>
									<td>{{ $prompt->model }}</td>
									<td>{{ Str::limit($prompt->generated_prompt, 100) }}</td>
									<td>{{ $prompt->width }}x{{ $prompt->height }}</td>
									<td>
										<button class="btn btn-sm btn-danger delete-prompt-btn"
										        data-prompt-id="{{ $prompt->id }}">
											Delete
										</button>
									</td>
								</tr>
							@endforeach
							</tbody>
						</table>
					</div>
				@else
					<div class="alert alert-info">
						There are no prompts currently in the queue.
					</div>
				@endif
			</div>
		</div>
		
		{{-- Failed Prompts Section --}}
		@if($failedPrompts->count() > 0)
			<div class="card">
				<div class="card-header">
					<div class="d-flex justify-content-between align-items-center">
						<h3 class="mb-0">Failed Generations</h3>
						<button id="deleteAllFailedBtn" class="btn btn-danger">Remove All Failed</button>
					</div>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-striped">
							<thead>
							<tr>
								<th style="min-width: 200px">Failed At</th>
								<th>Model</th>
								<th>Prompt</th>
								<th>Size</th>
								<th style="min-width: 220px">Actions</th>
							</tr>
							</thead>
							<tbody id="failed-prompts-tbody">
							@foreach($failedPrompts as $prompt)
								<tr data-prompt-id="{{ $prompt->id }}">
									<td>{{ $prompt->updated_at->format('Y-m-d H:i') }}</td> {{-- Use updated_at for failure time --}}
									<td>{{ $prompt->model }}</td>
									<td>{{ Str::limit($prompt->generated_prompt, 100) }}</td>
									<td>{{ $prompt->width }}x{{ $prompt->height }}</td>
									<td>
										<button class="btn btn-sm btn-warning requeue-prompt-btn me-2" data-prompt-id="{{ $prompt->id }}">
											Requeue
										</button>
										<button class="btn btn-sm btn-danger delete-prompt-btn" data-action-type="failed"
										        data-prompt-id="{{ $prompt->id }}">
											Delete
										</button>
									</td>
								</tr>
							@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		@endif
	</div>
	
	<!-- Confirmation Modal -->
	<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Confirm Delete</h5>
					<button type="button" class="btn-close" data-ui-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete this queued prompt? This cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-ui-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Delete All Confirmation Modal -->
	<div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Confirm Delete All</h5>
					<button type="button" class="btn-close" data-ui-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete all queued prompts? This cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-ui-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmDeleteAllBtn">Delete All</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="confirmDeleteAllFailedModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Remove All Failed Generations</h5>
					<button type="button" class="btn-close" data-ui-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to permanently remove all your failed generation records from the database? This cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-ui-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmDeleteAllFailedBtn">Remove All Failed</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const deleteModal = new DreamModal(document.getElementById('confirmDeleteModal'));
			const deleteAllModal = new DreamModal(document.getElementById('confirmDeleteAllModal'));
			const deleteAllFailedModal = new DreamModal(document.getElementById('confirmDeleteAllFailedModal'));
			
			let promptIdToDelete = null;
			
			// Handle delete button clicks
			document.querySelectorAll('.delete-prompt-btn').forEach(button => {
				button.addEventListener('click', function () {
					promptIdToDelete = this.dataset.promptId;
					deleteModal.show();
				});
			});
			
			// Handle delete all button click
			const deleteAllBtn = document.getElementById('deleteAllBtn');
			if (deleteAllBtn) {
				deleteAllBtn.addEventListener('click', function () {
					deleteAllModal.show();
				});
			}

			document.getElementById('deleteAllFailedBtn')?.addEventListener('click', function () {
				deleteAllFailedModal.show();
			});
			
			// Handle confirm delete
			document.getElementById('confirmDeleteBtn').addEventListener('click', async function () {
				if (!promptIdToDelete) return;
				
				try {
					const response = await fetch(`/queue/${promptIdToDelete}`, {
						method: 'DELETE',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
						}
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Remove row from table
						const row = document.querySelector(`.delete-prompt-btn[data-prompt-id="${promptIdToDelete}"]`).closest('tr');
						row.remove();
						
						// If table is now empty, show empty message
						if (document.querySelectorAll('tbody tr').length === 0) {
							document.querySelector('.card-body').innerHTML =
								'<div class="alert alert-info">There are no prompts currently in the queue.</div>';
							document.getElementById('deleteAllBtn').remove();
						}
						
						deleteModal.hide();
					} else {
						alert('Error: ' + (data.message || 'Failed to delete prompt'));
					}
				} catch (error) {
					console.error('Error deleting prompt:', error);
					alert('Error deleting prompt');
				}
			});

			document.getElementById('confirmDeleteAllFailedBtn').addEventListener('click', async function () {
				this.disabled = true;
				this.textContent = 'Removing…';

				try {
					const response = await fetch(@json(route('prompts.queue.failed.delete-all')), {
						method: 'DELETE',
						headers: {
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
						}
					});
					const data = await response.json();

					if (!response.ok || !data.success) {
						throw new Error(data.message || 'Failed to remove failed generations.');
					}

					window.location.reload();
				} catch (error) {
					console.error('Error deleting failed generations:', error);
					alert(error.message);
					this.disabled = false;
					this.textContent = 'Remove All Failed';
				}
			});
			
			// Handle confirm delete all
			document.getElementById('confirmDeleteAllBtn').addEventListener('click', async function () {
				try {
					const response = await fetch('/queue/delete-all', {
						method: 'DELETE',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
						}
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Reload page to show empty queue
						window.location.reload();
					} else {
						alert('Error: ' + (data.message || 'Failed to delete all prompts'));
					}
				} catch (error) {
					console.error('Error deleting all prompts:', error);
					alert('Error deleting all prompts');
				}
			});
			
			// --- Requeue Failed Prompt Handling ---
			document.querySelectorAll('.requeue-prompt-btn').forEach(button => {
				button.addEventListener('click', async function() {
					const promptId = this.dataset.promptId;
					this.disabled = true; // Disable button during request
					this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Requeuing...';
					
					try {
						const response = await fetch(`/queue/requeue/${promptId}`, {
							method: 'POST', // Use POST for the requeue action
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json'
							}
						});
						const data = await response.json();
						
						if (response.ok && data.success) {
							// Remove the row from the failed table
							const row = this.closest('tr');
							row?.remove();
							
							// If failed table is now empty, remove the card
							if (document.querySelectorAll('#failed-prompts-tbody tr').length === 0) {
								const failedCard = document.querySelector('#failed-prompts-tbody').closest('.card');
								failedCard?.remove();
							}
							
						} else {
							console.error('Error requeueing prompt:', data.message || 'Unknown error');
							alert('Error: ' + (data.message || 'Failed to requeue prompt'));
							// Restore button text if failed
							this.innerHTML = 'Requeue';
							this.disabled = false;
						}
					} catch (error) {
						console.error('Network or parsing error requeueing prompt:', error);
						alert('Error requeueing prompt. Check console for details.');
						// Restore button text if failed
						this.innerHTML = 'Requeue';
						this.disabled = false;
					}
					// No finally block needed here as button is removed on success
				});
			});
		});
	</script>
@endsection
