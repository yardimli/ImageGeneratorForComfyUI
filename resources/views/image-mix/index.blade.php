@extends('layouts.bootstrap-app')

@section('content')
	<div class="queue-status">
		<span class="me-2">Queue:</span>
		<span id="queueCount" class="badge bg-primary">0</span>
	</div>
	
	<div class="container py-4">
		<div class="card mb-4">
			<div class="card-header">
				<h3 class="mb-0">Image Mixing Tool</h3>
			</div>
			<div class="card-body">
				<form id="imageMixForm" method="POST" action="{{ route('image-mix.store') }}">
					@csrf
					
					<div class="row mb-3">
						<div class="col-md-3">
							<label class="form-label">Aspect Ratio</label>
							<select class="form-select" name="aspect_ratio" id="aspectRatio">
								<optgroup label="1MP">
									<option value="1:1-1024" selected>1:1 (1024 x 1024)</option>
									<option value="3:2-1024">3:2 (1216 x 832) Landscape</option>
									<option value="4:3-1024">4:3 (1152 x 896) Landscape</option>
									<option value="16:9-1024">16:9 (1344 x 768) Landscape</option>
									<option value="21:9-1024">21:9 (1536 x 640) Landscape</option>
									<option value="2:3-1024">2:3 (832 x 1216) Portrait</option>
									<option value="3:4-1024">3:4 (896 x 1152) Portrait</option>
									<option value="9:16-1024">9:16 (768 x 1344) Portrait</option>
									<option value="9:21-1024">9:21 (640 x 1536) Portrait</option>
								</optgroup>
								<optgroup label="2MP">
									<option value="1:1-1408">1:1 (1408 x 1408)</option>
									<option value="3:2-1408">3:2 (1728 x 1152) Landscape</option>
									<option value="4:3-1408">4:3 (1664 x 1216) Landscape</option>
									<option value="16:9-1408">16:9 (1920 x 1088) Landscape</option>
									<option value="21:9-1408">21:9 (2176 x 960) Landscape</option>
									<option value="2:3-1408">2:3 (1152 x 1728) Portrait</option>
									<option value="3:4-1408">3:4 (1216 x 1664) Portrait</option>
									<option value="9:16-1408">9:16 (1088 x 1920) Portrait</option>
									<option value="9:21-1408">9:21 (960 x 2176) Portrait</option>
								</optgroup>
							</select>
						</div>
						<div class="col-md-2">
							<label class="form-label">Width</label>
							<input type="number" class="form-control" name="width" id="width" value="1024">
						</div>
						<div class="col-md-2">
							<label class="form-label">Height</label>
							<input type="number" class="form-control" name="height" id="height" value="1024">
						</div>
						<div class="col-md-2">
							<label class="form-label">Render Count</label>
							<input type="number" class="form-control" name="render_each_prompt_times" value="1" min="1">
						</div>
						<div class="col-md-2">
							<label class="form-label">Model</label>
							<select class="form-select" name="model">
								<option value="schnell">Schnell</option>
								<option value="dev" selected>Dev</option>
							</select>
						</div>
						<div class="col-md-1">
							<label class="form-check-label" for="uploadToS3">To S3</label>
							<div class="form-check mt-2">
								<input type="hidden" name="upload_to_s3" value="0">
								<input type="checkbox" class="form-check-input" name="upload_to_s3" id="uploadToS3" value="1" checked>
							</div>
						</div>
					</div>
					
					<div class="row">
						<!-- Left Column (Images with Prompts) -->
						<div class="col-md-6">
							<div class="card mb-3">
								<div class="card-header">
									<h5>Images with Prompts (Left Side)</h5>
								</div>
								<div class="card-body">
									<div id="leftImagesContainer">
										<!-- Images will be added here -->
									</div>
									<button type="button" class="btn btn-primary mt-3" id="addLeftImageBtn">
										Add Image
									</button>
									<button type="button" class="btn btn-info mt-3" id="leftPexelsBtn">
										Pexels
									</button>
									<button type="button" class="btn btn-secondary mt-3" id="leftUploadHistoryBtn">
										Upload History
									</button>
								</div>
							</div>
						</div>
						
						<!-- Right Column (Images without Prompts) -->
						<div class="col-md-6">
							<div class="card mb-3">
								<div class="card-header">
									<h5>Images (Right Side)</h5>
								</div>
								<div class="card-body">
									<div id="rightImagesContainer">
										<!-- Images will be added here -->
									</div>
									<button type="button" class="btn btn-primary mt-3" id="addRightImageBtn">
										Add Image
									</button>
									<button type="button" class="btn btn-info mt-3" id="rightPexelsBtn">
										Pexels
									</button>
									<button type="button" class="btn btn-secondary mt-3" id="rightUploadHistoryBtn">
										Upload History
									</button>
								</div>
							</div>
						</div>
					</div>
					
					<!-- Hidden inputs to store JSON data -->
					<input type="hidden" name="input_images_1" id="inputImages1Json" value="[]">
					<input type="hidden" name="input_images_2" id="inputImages2Json" value="[]">
					<input type="hidden" name="count" id="count" value="1">
					
					<div class="text-end mt-3">
						<button type="submit" class="btn btn-primary">Generate</button>
					</div>
				</form>
			</div>
		</div>
		
		<div class="container mb-4">
			<a href="{{ route('gallery.index') }}" class="btn btn-secondary">Go to Gallery</a>
			<a href="{{ route('prompts.index') }}" class="btn btn-secondary">Go to Prompts</a>
			<a href="{{ route('home') }}" class="btn btn-secondary">Back to Home</a>
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
	
	<!-- Image Upload Modal -->
	<div class="modal fade" id="uploadImageModal" tabindex="-1" aria-labelledby="uploadImageModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content bg-dark">
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
						<input type="hidden" id="uploadTarget" value="">
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="uploadImageBtn">Upload</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Upload History Modal -->
	<div class="modal fade" id="uploadHistoryModal" tabindex="-1" aria-labelledby="uploadHistoryModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title" id="uploadHistoryModalLabel">Upload History</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row mb-3">
						<div class="col-12 text-end">
							<span id="selectedCount" class="me-2">0 selected</span>
							<button type="button" class="btn btn-primary" id="addSelectedImagesBtn">Add Selected Images</button>
						</div>
					</div>
					<div class="row" id="historyImagesContainer">
						<!-- Images will be loaded here -->
						<div class="text-center py-5">
							<div class="spinner-border" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
						</div>
					</div>
					<div class="row mt-3">
						<div class="col-12">
							<nav aria-label="Page navigation">
								<ul class="pagination justify-content-center" id="historyPagination">
									<!-- Pagination will be added here -->
								</ul>
							</nav>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Prompt Queued Modal -->
	<div class="modal fade" id="promptQueuedModal" tabindex="-1" aria-labelledby="promptQueuedModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title" id="promptQueuedModalLabel">Success</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Your image mix has been queued for generation!
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Pexels Search Modal -->
	<div class="modal fade" id="pexelsModal" tabindex="-1" aria-labelledby="pexelsModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content bg-dark">
				<div class="modal-header">
					<h5 class="modal-title" id="pexelsModalLabel">Search Pexels Images</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row mb-3">
						<div class="col-md-8">
							<div class="input-group">
								<input type="text" class="form-control" id="pexelsSearchInput" placeholder="Search for images...">
								<button class="btn btn-primary" type="button" id="pexelsSearchBtn">Search</button>
							</div>
						</div>
						<div class="col-md-4 text-end">
							<span id="pexelsSelectedCount" class="me-2">0 selected</span>
						</div>
					</div>
					
					<div class="row">
						<!-- Left side: Search results -->
						<div class="col-md-8">
							<div id="pexelsImagesContainer" class="row">
								<div class="col-12 text-center py-5">
									<p class="text-muted">Search for images to get started</p>
								</div>
							</div>
							<div class="mt-3">
								<nav aria-label="Pexels pagination">
									<ul class="pagination justify-content-center" id="pexelsPagination"></ul>
								</nav>
							</div>
						</div>
						
						<!-- Right side: Selected images -->
						<div class="col-md-4">
							<div class="card">
								<div class="card-header">
									<h6 class="mb-0">Selected Images</h6>
								</div>
								<div class="card-body p-2" style="max-height: 500px; overflow-y: auto;">
									<div id="pexelsSelectedImagesContainer">
										<p class="text-muted text-center py-3">No images selected</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<small class="text-muted me-auto">Photos provided by <a href="https://www.pexels.com" target="_blank" class="text-light">Pexels</a></small>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="pexelsAddSelectedBtn">Add Selected Images</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script src="{{ asset('js/image-results-script.js') }}"></script>
	<script src="{{ asset('js/mix-script.js') }}"></script>
	<script src="{{ asset('js/pexels-script.js') }}"></script>
@endsection
