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
					Are you sure you want to delete this queued prompt? This cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Delete All Confirmation Modal -->
	<div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title">Confirm Delete All</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete all queued prompts? This cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmDeleteAllBtn">Delete All</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Reload queue count every 5 seconds
			setInterval(updateQueueCount, 5000);
			
			const deleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
			const deleteAllModal = new bootstrap.Modal(document.getElementById('confirmDeleteAllModal'));
			
			let promptIdToDelete = null;
			
			// Handle delete button clicks
			document.querySelectorAll('.delete-prompt-btn').forEach(button => {
				button.addEventListener('click', function() {
					promptIdToDelete = this.dataset.promptId;
					deleteModal.show();
				});
			});
			
			// Handle delete all button click
			const deleteAllBtn = document.getElementById('deleteAllBtn');
			if (deleteAllBtn) {
				deleteAllBtn.addEventListener('click', function() {
					deleteAllModal.show();
				});
			}
			
			// Handle confirm delete
			document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
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
						updateQueueCount();
					} else {
						alert('Error: ' + (data.message || 'Failed to delete prompt'));
					}
				} catch (error) {
					console.error('Error deleting prompt:', error);
					alert('Error deleting prompt');
				}
			});
			
			// Handle confirm delete all
			document.getElementById('confirmDeleteAllBtn').addEventListener('click', async function() {
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
		});
	</script>
@endsection
