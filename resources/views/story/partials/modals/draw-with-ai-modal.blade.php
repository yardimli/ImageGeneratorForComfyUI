<div class="modal fade" id="drawWithAiModal" tabindex="-1" aria-labelledby="drawWithAiModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="drawWithAiModalLabel">Draw with AI</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="draw-with-ai-form">
					<input type="hidden" id="draw-asset-id" value="">
					<div class="mb-3">
						<label class="form-label fw-bold">Image Prompt:</label>
						<p class="form-control-plaintext p-2 bg-body-secondary rounded" id="draw-image-prompt-text"></p>
					</div>
					<div class="row mb-3">
						<div class="col-md-6">
							<label for="draw-model" class="form-label">AI Model</label>
							<select class="form-select" id="draw-model">
								@foreach($imageModels as $model)
									<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6">
							<label for="draw-aspect-ratio" class="form-label">Aspect Ratio</label>
							<select class="form-select" id="draw-aspect-ratio">
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
					</div>
					<div class="row mb-3">
						<div class="col-md-4">
							<label for="draw-width" class="form-label">Width</label>
							<input type="number" class="form-control" id="draw-width" value="1024">
						</div>
						<div class="col-md-4">
							<label for="draw-height" class="form-label">Height</label>
							<input type="number" class="form-control" id="draw-height" value="1024">
						</div>
						<div class="col-md-4 d-flex align-items-center">
							<div class="form-check mt-3">
								<input type="checkbox" class="form-check-input" id="draw-upload-to-s3" value="1" checked>
								<label class="form-check-label" for="draw-upload-to-s3">Upload to S3</label>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="generate-image-btn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Generate Image Only
				</button>
			</div>
		</div>
	</div>
</div>
