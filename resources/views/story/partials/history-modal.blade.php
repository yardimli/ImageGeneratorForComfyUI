<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="historyModalLabel">Select an Image</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="row mb-3">
					<div class="col-md-4">
						<label for="historySource" class="form-label form-label-sm">Image Source</label>
						<select id="historySource" class="form-select form-select-sm">
							<option value="uploads" selected>My Uploads</option>
							<option value="renders">My Renders</option>
						</select>
					</div>
					<div class="col-md-3">
						<label for="historySort" class="form-label form-label-sm">Sort by</label>
						<select id="historySort" class="form-select form-select-sm">
							<option value="newest" selected>Newest First</option>
							<option value="oldest">Oldest First</option>
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
				</div>
				<div class="row" id="historyImagesContainer"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
				<div class="row mt-3">
					<div class="col-12">
						<nav><ul class="pagination justify-content-center" id="historyPagination"></ul></nav>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				{{-- START MODIFICATION: Add button and hidden input for direct image uploads --}}
				<input type="file" id="newImageUploadInput" class="d-none" accept="image/*">
				<button type="button" class="btn btn-info me-auto" id="uploadNewImageBtn">Upload New Image</button>
				{{-- END MODIFICATION --}}
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="addSelectedHistoryImageBtn">Use Selected Image</button>
			</div>
		</div>
	</div>
</div>
