@extends('layouts.bootstrap-app')

@section('content')
	<div class="queue-status">
		<span class="me-2">Queue:</span>
		<span id="queueCount" class="badge bg-primary">0</span>
	</div>
	
	<div class="container py-4">
		<div class="card mb-4">
			<div class="card-header">
				{{-- New page title --}}
				<h3 class="mb-0">Kontext Lora Tool</h3>
			</div>
			<div class="card-body">
				{{-- Updated form ID and action route --}}
				<form id="kontextLoraForm" method="POST" action="{{ route('kontext-lora.store') }}">
					@csrf
					
					{{-- Top row settings --}}
					<div class="row align-items-end">
						{{-- START MODIFICATION: Replace text input with a dropdown for Lora selection. --}}
						<div class="col-md-3">
							<label for="loraNameSelect" class="form-label">Lora</label>
							<select id="loraNameSelect" class="form-select" name="lora_name" required>
								<option value="" selected disabled>Select a Lora...</option>
								@if(!empty($loras))
									@foreach($loras as $lora)
										<option value="{{ $lora['model'] }}"
										        data-trigger="{{ htmlspecialchars($lora['trigger']) }}"
										        data-notes="{{ htmlspecialchars($lora['notes']) }}">
											{{ $lora['trigger'] }}
										</option>
									@endforeach
								@endif
							</select>
						</div>
						{{-- END MODIFICATION --}}
						<div class="col-md-2">
							<label class="form-label">Strength Model</label>
							<input type="number" class="form-control" name="strength_model" value="1.0" step="0.1" min="0">
						</div>
						<div class="col-md-2">
							<label class="form-label">Guidance</label>
							<input type="number" class="form-control" name="guidance" value="2.5" step="0.1" min="0">
						</div>
						<div class="col-md-2">
							<label class="form-label">Render Count</label>
							<input type="number" class="form-control" name="render_each_prompt_times" value="1" min="1">
						</div>
						<div class="col-md-2">
							<label class="form-check-label" for="uploadToS3">To S3</label>
							<div class="form-check mt-2">
								<input type="hidden" name="upload_to_s3" value="0">
								<input type="checkbox" class="form-check-input" name="upload_to_s3" id="uploadToS3" value="1" checked>
							</div>
						</div>
					</div>
					
					<div class="row mt-0">
						<div id="loraInfo" class="col-12 form-text text-muted mt-2" style="min-height: 40px;">
							Select a Lora to see its trigger and notes.
						</div>
					</div>
					
					
					<div class="row">
						{{-- Image Column --}}
						<div class="col-md-6">
							<div class="card">
								<div class="card-header">
									<h5>Input Image</h5>
								</div>
								<div class="card-body">
									<div id="imageContainer" class="mb-3" style="min-height: 200px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
										<p class="text-muted">Select an image</p>
									</div>
									{{-- START MODIFICATION: Remove upload/history buttons, leaving only the Image Editor link. --}}
									<a href="{{ route('image-editor.index', ['return_url' => url()->current()]) }}" class="btn btn-warning" target="_blank">Image Editor</a>
									{{-- END MODIFICATION --}}
								</div>
							</div>
						</div>
						
						{{-- Prompt Column --}}
						<div class="col-md-6">
							<div class="card">
								<div class="card-header">
									<h5>Prompt</h5>
								</div>
								<div class="card-body">
									<textarea name="prompt" id="promptTextarea" class="form-control" rows="10" placeholder="Enter your prompt here..."></textarea>
								</div>
							</div>
						</div>
					</div>
					
					{{-- Hidden input to store image URL --}}
					<input type="hidden" name="image_url" id="imageUrlInput" value="">
					
					<div class="text-end mt-3">
						<button type="submit" class="btn btn-primary">Generate</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	
	{{-- START MODIFICATION: Remove all modals except the 'prompt queued' success modal. --}}
	<!-- Prompt Queued Modal -->
	<div class="modal fade" id="promptQueuedModal" tabindex="-1" aria-labelledby="promptQueuedModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="promptQueuedModalLabel">Success</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Your job has been queued for generation!
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
@endsection

@section('styles')
	{{-- START MODIFICATION: Remove Cropper.js styles and other unused styles. --}}
	{{-- END MODIFICATION --}}
@endsection

@section('scripts')
	<script src="{{ asset('js/queue.js') }}"></script>
	{{-- START MODIFICATION: Remove Cropper.js script as it's no longer used on this page. --}}
	{{-- END MODIFICATION --}}
	<script src="{{ asset('js/kontext-lora-script.js') }}"></script>
@endsection
