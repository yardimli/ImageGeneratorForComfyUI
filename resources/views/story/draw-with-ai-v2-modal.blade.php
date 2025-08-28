{{-- START NEW FILE --}}
<div class="modal fade" id="drawWithAiV2Modal" tabindex="-1" aria-labelledby="drawWithAiV2ModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="drawWithAiV2ModalLabel">Draw with AI v2 (Image Edit)</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="draw-with-ai-v2-form">
					<input type="hidden" id="draw-v2-asset-id" value="">
					<div class="alert alert-info small">
						This feature uses the <strong>gemini-25-flash-image/edit</strong> model to generate a new image based on your prompt and the input images provided below.
					</div>
					<div class="mb-3">
						<label class="form-label fw-bold">Image Prompt:</label>
						<p class="form-control-plaintext p-2 bg-body-secondary rounded" id="draw-v2-image-prompt-text"></p>
					</div>
					<div class="mb-3">
						<label class="form-label fw-bold">Input Images (click to remove):</label>
						<div id="draw-v2-input-images-container" class="d-flex flex-wrap gap-2 border p-2 rounded" style="min-height: 110px;">
							{{-- Thumbnails will be injected here by JS --}}
						</div>
					</div>
					<div class="row mb-3">
						<div class="col-md-6">
							<label for="draw-v2-aspect-ratio" class="form-label">Aspect Ratio</label>
							<select class="form-select" id="draw-v2-aspect-ratio">
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
						<div class="col-md-3">
							<label for="draw-v2-width" class="form-label">Width</label>
							<input type="number" class="form-control" id="draw-v2-width" value="1024">
						</div>
						<div class="col-md-3">
							<label for="draw-v2-height" class="form-label">Height</label>
							<input type="number" class="form-control" id="draw-v2-height" value="1024">
						</div>
					</div>
					<div class="form-check">
						<input type="checkbox" class="form-check-input" id="draw-v2-upload-to-s3" value="1" checked>
						<label class="form-check-label" for="draw-v2-upload-to-s3">Upload to S3</label>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="generate-image-v2-btn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Generate Image
				</button>
			</div>
		</div>
	</div>
</div>

{{-- Template for input image thumbnails in the modal --}}
<template id="input-image-thumbnail-template">
	<div class="position-relative input-image-thumbnail" style="cursor: pointer;" title="Click to remove">
		<img src="" class="rounded" style="width: 90px; height: 90px; object-fit: cover;" alt="Input image thumbnail">
		<input type="hidden" class="image-path-input" value="">
		<button type="button" class="btn-close btn-sm position-absolute top-0 end-0 bg-light rounded-circle" aria-label="Remove" style="transform: translate(25%, -25%);"></button>
	</div>
</template>
{{-- END NEW FILE --}}
