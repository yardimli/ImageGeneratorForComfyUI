@extends('layouts.bootstrap-app')

@section('content')
	<div class="queue-status">
		<span class="me-2">Queue:</span>
		<span id="queueCount" class="badge bg-primary">0</span>
	</div>
	
	<div class="container py-4">
		<div class="card mb-4">
			<div class="card-header">
				<h3 class="mb-0">Kontext Basic Tool</h3>
			</div>
			<div class="card-body">
				<form id="kontextBasicForm" method="POST" action="{{ route('kontext-basic.store') }}">
					@csrf
					
					{{-- Top row settings --}}
					<div class="row mb-4">
						<div class="col-md-3">
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
									<button type="button" class="btn btn-primary" id="uploadBtn">Upload</button>
									<button type="button" class="btn btn-secondary" id="uploadHistoryBtn">Upload History</button>
									<button type="button" class="btn btn-info" id="renderHistoryBtn">Render History</button>
									{{-- START MODIFICATION: Add Image Editor button --}}
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
	
	{{-- Modals from image-mix, adapted for re-use --}}
	
	<!-- Image Upload Modal -->
	<div class="modal fade" id="uploadImageModal" tabindex="-1" aria-labelledby="uploadImageModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="uploadImageModalLabel">Upload Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<form id="uploadImageForm" enctype="multipart/form-data">
						@csrf
						<div class="mb-3">
							<label for="imageUpload" class="form-label">Select Image</label>
							<input class="form-control" type="file" id="imageUpload" name="image" accept="image/*">
						</div>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					{{-- MODIFICATION: Changed button text to reflect new action --}}
					<button type="button" class="btn btn-primary" id="confirmUploadBtn">Select</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Upload History Modal -->
	<div class="modal fade" id="uploadHistoryModal" tabindex="-1" aria-labelledby="uploadHistoryModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="uploadHistoryModalLabel">Upload History</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row mb-3 align-items-center">
						<div class="col-md-3">
							<label for="historySort" class="form-label form-label-sm">Sort by</label>
							<select id="historySort" class="form-select form-select-sm">
								<option value="newest" selected>Newest First</option>
								<option value="oldest">Oldest First</option>
								<option value="count_desc">Usage Count (High to Low)</option>
							</select>
						</div>
						<div class="col-md-2">
							<label for="historyPerPage" class="form-label form-label-sm">Per Page</label>
							<select id="historyPerPage" class="form-select form-select-sm">
								<option value="12" selected>12</option>
								<option value="24">24</option>
								<option value="48">48</option>
								<option value="96">96</option>
							</select>
						</div>
						<div class="col-md-7 text-end">
							<button type="button" class="btn btn-primary btn-sm" id="addSelectedHistoryImageBtn">Add Selected Image</button>
						</div>
					</div>
					<div class="row" id="historyImagesContainer"></div>
					<div class="row mt-3">
						<div class="col-12">
							<nav><ul class="pagination justify-content-center" id="historyPagination"></ul></nav>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Render History Modal (New) -->
	<div class="modal fade" id="renderHistoryModal" tabindex="-1" aria-labelledby="renderHistoryModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="renderHistoryModalLabel">Render History</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row mb-3 align-items-center">
						<div class="col-md-3">
							<label for="renderHistorySort" class="form-label form-label-sm">Sort by</label>
							<select id="renderHistorySort" class="form-select form-select-sm">
								<option value="newest" selected>Newest First</option>
								<option value="oldest">Oldest First</option>
							</select>
						</div>
						<div class="col-md-2">
							<label for="renderHistoryPerPage" class="form-label form-label-sm">Per Page</label>
							<select id="renderHistoryPerPage" class="form-select form-select-sm">
								<option value="12" selected>12</option>
								<option value="24">24</option>
								<option value="48">48</option>
								<option value="96">96</option>
							</select>
						</div>
						<div class="col-md-7 text-end">
							<button type="button" class="btn btn-primary btn-sm" id="addSelectedRenderImageBtn">Add Selected Image</button>
						</div>
					</div>
					<div class="row" id="renderHistoryImagesContainer"></div>
					<div class="row mt-3">
						<div class="col-12">
							<nav><ul class="pagination justify-content-center" id="renderHistoryPagination"></ul></nav>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	
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
	
	{{-- START MODIFICATION: Add Cropper Modal --}}
	<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="cropperModalLabel">Crop Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div>
						{{-- Add crossorigin for S3/remote images --}}
						<img id="imageToCrop" src="" style="max-width: 100%;" crossorigin="anonymous">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="confirmCropBtn">Confirm Crop</button>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
@endsection

@section('styles')
	{{-- START MODIFICATION: Add Cropper.js styles --}}
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
	{{-- END MODIFICATION --}}
	<style>
      .history-image-card {
          cursor: pointer;
          border: 2px solid transparent;
          transition: border-color 0.2s;
      }
      .history-image-card.selected {
          border-color: var(--bs-primary);
      }
      .history-image-card img {
          width: 100%;
          height: 150px;
          object-fit: cover;
      }
	</style>
@endsection

@section('scripts')
	<script src="{{ asset('js/queue.js') }}"></script>
	{{-- START MODIFICATION: Add Cropper.js script --}}
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	{{-- END MODIFICATION --}}
	<script src="{{ asset('js/kontext-basic-script.js') }}"></script>
@endsection
