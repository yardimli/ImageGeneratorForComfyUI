<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="cropperModalLabel">Crop Image</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div>
					<img id="imageToCrop" src="" style="max-width: 100%;" crossorigin="anonymous">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				{{-- START MODIFICATION: Add button to use the full image without cropping --}}
				<button type="button" class="btn btn-info" id="useFullImageBtn">Use Full Image (No Crop)</button>
				{{-- END MODIFICATION --}}
				<button type="button" class="btn btn-primary" id="confirmCropBtn">Confirm Crop</button>
			</div>
		</div>
	</div>
</div>
