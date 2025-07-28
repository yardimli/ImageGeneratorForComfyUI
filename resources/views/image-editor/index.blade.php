@extends('layouts.bootstrap-app')

@section('styles')
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
	<style>
      #canvas-container {
          box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
          line-height: 0;
          border: 1px solid #dee2e6;
      }
      .cropper-container-wrapper {
          max-height: 60vh;
      }
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

@section('content')
	<div class="container py-4">
		<div class="card shadow-sm">
			<div class="card-header">
				<h1 class="h3 mb-0">Image Editor</h1>
			</div>
			<div class="card-body">
				{{-- START MODIFICATION: New initial view for selecting a background --}}
				<div id="initial-upload-view" class="text-center p-5 border-2 border-dashed rounded-lg">
					<h2 class="h4">Step 1: Select a Background Image</h2>
					<p class="text-muted mb-4">The canvas will be sized to fit your screen. The final image will be saved at this image's original resolution.</p>
					<div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
						<button id="upload-new-bg-btn" class="btn btn-primary">Upload New</button>
						<button id="choose-from-history-bg-btn" class="btn btn-secondary">Choose from History</button>
					</div>
					<input type="file" id="bg-uploader-input" class="d-none" accept="image/*">
				</div>
				{{-- END MODIFICATION --}}
				
				<!-- Step 2: Main Application View (hidden initially) -->
				<div id="app-view" class="d-none">
					<!-- Toolbar -->
					<div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mb-3 p-3 bg-light rounded">
						{{-- MODIFICATION: This button now opens the history modal --}}
						<button id="add-image-btn" class="btn btn-success">
							Add Image
						</button>
						
						<button id="save-image-btn" class="btn btn-info">
							Save and Return
						</button>
						<p class="text-muted small w-100 text-center w-auto-sm mb-0">
							Hint: Select an object and press 'Delete' to remove it.
						</p>
					</div>
					
					<!-- Canvas Container -->
					<div id="canvas-container" class="mx-auto w-100">
						<canvas id="c"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	{{-- START MODIFICATION: Added a comprehensive history modal for image selection --}}
	<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="historyModalLabel">Select an Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<!-- Filters -->
					<div class="row mb-3 align-items-center">
						<div class="col-md-3">
							<label for="historySource" class="form-label form-label-sm">Source</label>
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
					<!-- Image Container -->
					<div class="row" id="historyImagesContainer" style="min-height: 200px;"></div>
					<!-- Pagination -->
					<div class="row mt-3">
						<div class="col-12">
							<nav><ul class="pagination justify-content-center" id="historyPagination"></ul></nav>
						</div>
					</div>
				</div>
				<div class="modal-footer justify-content-between">
					<div>
						<button type="button" class="btn btn-success" id="uploadNewImageBtn">Upload New Image</button>
						<input type="file" id="newImageUploadInput" class="d-none" accept="image/*">
					</div>
					<div>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" id="addSelectedHistoryImageBtn">Add Selected</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
	
	<!-- Cropping Modal (Bootstrap 5) -->
	<div class="modal fade" id="crop-modal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="cropModalLabel">Adjust Your Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="cropper-container-wrapper">
						{{-- MODIFICATION: Added crossorigin attribute for remote images --}}
						<img id="image-to-crop" src="" alt="Image to crop" style="max-width: 100%;" crossorigin="anonymous">
					</div>
				</div>
				<div class="modal-footer">
					{{-- START MODIFICATION: Added "Use Full Image" button --}}
					<button id="use-full-image-btn" type="button" class="btn btn-info me-auto">Use Full Image (No Crop)</button>
					{{-- END MODIFICATION --}}
					<button id="cancel-crop-btn" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button id="confirm-crop-btn" type="button" class="btn btn-primary">Confirm and Add</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	
	{{-- START MODIFICATION: The entire script has been rewritten for the new workflow --}}
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			// --- DOM Elements ---
			const initialUploadView = document.getElementById('initial-upload-view');
			const appView = document.getElementById('app-view');
			const saveImageBtn = document.getElementById('save-image-btn');
			const canvasContainer = document.getElementById('canvas-container');
			const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
			
			// Initial view buttons
			const uploadNewBgBtn = document.getElementById('upload-new-bg-btn');
			const chooseFromHistoryBgBtn = document.getElementById('choose-from-history-bg-btn');
			const bgUploaderInput = document.getElementById('bg-uploader-input');
			
			// App view buttons
			const addImageBtn = document.getElementById('add-image-btn');
			
			// History Modal
			const historyModalEl = document.getElementById('historyModal');
			const historyModal = new bootstrap.Modal(historyModalEl);
			const historySource = document.getElementById('historySource');
			const historySort = document.getElementById('historySort');
			const historyPerPage = document.getElementById('historyPerPage');
			const historyImagesContainer = document.getElementById('historyImagesContainer');
			const historyPagination = document.getElementById('historyPagination');
			const uploadNewImageBtn = document.getElementById('uploadNewImageBtn');
			const newImageUploadInput = document.getElementById('newImageUploadInput');
			const addSelectedHistoryImageBtn = document.getElementById('addSelectedHistoryImageBtn');
			
			// Crop Modal
			const cropModalEl = document.getElementById('crop-modal');
			const cropModal = new bootstrap.Modal(cropModalEl);
			const imageToCrop = document.getElementById('image-to-crop');
			const useFullImageBtn = document.getElementById('use-full-image-btn');
			const confirmCropBtn = document.getElementById('confirm-crop-btn');
			
			// --- App State ---
			let canvas;
			let cropper;
			let backgroundImageObject;
			let originalWidth, originalHeight;
			let currentScale = 1;
			let isSettingBackground = false; // Critical state to determine image destination
			
			// --- Utility Functions ---
			const debounce = (func, delay) => {
				let timeout;
				return (...args) => {
					clearTimeout(timeout);
					timeout = setTimeout(() => func.apply(this, args), delay);
				};
			};
			
			const dataURLtoBlob = (dataurl) => {
				const arr = dataurl.split(',');
				const mime = arr[0].match(/:(.*?);/)[1];
				const bstr = atob(arr[1]);
				let n = bstr.length;
				const u8arr = new Uint8Array(n);
				while (n--) {
					u8arr[n] = bstr.charCodeAt(n);
				}
				return new Blob([u8arr], { type: mime });
			};
			
			// --- Canvas Resizing ---
			const resizeCanvas = () => {
				if (!canvas || !backgroundImageObject) return;
				const containerWidth = canvasContainer.offsetWidth;
				currentScale = containerWidth / originalWidth;
				const newHeight = originalHeight * currentScale;
				
				canvas.setWidth(containerWidth);
				canvas.setHeight(newHeight);
				canvas.setBackgroundImage(backgroundImageObject, canvas.renderAll.bind(canvas), {
					scaleX: currentScale,
					scaleY: currentScale,
				});
				canvas.getObjects().forEach(obj => obj.setCoords());
				canvas.renderAll();
			};
			const debouncedResize = debounce(resizeCanvas, 150);
			
			// --- History Modal Logic ---
			const loadHistory = async (page = 1) => {
				const source = historySource.value;
				const sort = historySort.value;
				const perPage = historyPerPage.value;
				historyImagesContainer.innerHTML = '<div class="d-flex justify-content-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
				
				const endpoint = source === 'uploads' ? `/image-mix/uploads?page=${page}&sort=${sort}&perPage=${perPage}` : `/kontext-basic/render-history?page=${page}&sort=${sort}&perPage=${perPage}`;
				const response = await fetch(endpoint);
				const data = await response.json();
				
				historyImagesContainer.innerHTML = '';
				const images = source === 'uploads' ? data.images : data.data;
				images.forEach(img => {
					const imageUrl = source === 'uploads' ? img.path : img.image_url;
					const thumbUrl = source === 'uploads' ? img.path : img.thumbnail_url;
					const name = source === 'uploads' ? img.name : (img.generated_prompt || 'Rendered Image');
					historyImagesContainer.innerHTML += `
                        <div class="col-lg-2 col-md-3 col-sm-4 mb-3">
                            <div class="card history-image-card" data-path="${imageUrl}">
                                <img src="${thumbUrl}" class="card-img-top" alt="${name}" crossorigin="anonymous">
                                <div class="card-body p-1 small"><p class="card-text mb-0 text-truncate" title="${name}">${name}</p></div>
                            </div>
                        </div>`;
				});
				const paginationData = source === 'uploads' ? data.pagination : { current_page: data.current_page, total_pages: data.last_page };
				renderPagination(paginationData);
			};
			
			const renderPagination = (data) => {
				historyPagination.innerHTML = '';
				if (!data || data.total_pages <= 1) return;
				for (let i = 1; i <= data.total_pages; i++) {
					historyPagination.innerHTML += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
				}
			};
			
			// --- Cropper Modal Logic ---
			const openCropper = (imageUrl) => {
				imageToCrop.src = imageUrl;
				historyModal.hide();
				cropModal.show();
			};
			
			cropModalEl.addEventListener('shown.bs.modal', () => {
				if (cropper) cropper.destroy();
				cropper = new Cropper(imageToCrop, {
					viewMode: 1,
					background: false,
					// Allow free-form cropping for foreground, but no aspect ratio for background
					aspectRatio: isSettingBackground ? NaN : 0,
				});
			});
			
			cropModalEl.addEventListener('hidden.bs.modal', () => {
				if (cropper) cropper.destroy();
				cropper = null;
				imageToCrop.src = '';
			});
			
			// --- Core Image Processing ---
			const processFinalImage = (imageUrl) => {
				if (isSettingBackground) {
					initializeCanvas(imageUrl);
				} else {
					addForegroundImage(imageUrl);
				}
				cropModal.hide();
			};
			
			const initializeCanvas = (imageUrl) => {
				canvas = new fabric.Canvas('c');
				fabric.Image.fromURL(imageUrl, (img) => {
					backgroundImageObject = img;
					originalWidth = img.width;
					originalHeight = img.height;
					
					initialUploadView.classList.add('d-none');
					appView.classList.remove('d-none');
					
					resizeCanvas();
					window.addEventListener('resize', debouncedResize);
				}, { crossorigin: 'anonymous' });
			};
			
			const addForegroundImage = (imageUrl) => {
				fabric.Image.fromURL(imageUrl, (fabricImage) => {
					fabricImage.set({
						left: canvas.width / 2,
						top: canvas.height / 2,
						originX: 'center',
						originY: 'center',
						// Start with a reasonable scale relative to the canvas
						scaleX: (canvas.width / 4) / fabricImage.width,
						scaleY: (canvas.width / 4) / fabricImage.width,
					});
					canvas.add(fabricImage);
					canvas.setActiveObject(fabricImage);
					canvas.renderAll();
				}, { crossorigin: 'anonymous' });
			};
			
			// --- Event Listeners ---
			
			// Step 1: Background Selection
			uploadNewBgBtn.addEventListener('click', () => {
				isSettingBackground = true;
				bgUploaderInput.click();
			});
			
			chooseFromHistoryBgBtn.addEventListener('click', () => {
				isSettingBackground = true;
				loadHistory(1);
				historyModal.show();
			});
			
			bgUploaderInput.addEventListener('change', (e) => {
				const file = e.target.files[0];
				if (file) openCropper(URL.createObjectURL(file));
				e.target.value = '';
			});
			
			// Step 2: Add Foreground Image
			addImageBtn.addEventListener('click', () => {
				isSettingBackground = false;
				loadHistory(1);
				historyModal.show();
			});
			
			// History Modal Events
			[historySource, historySort, historyPerPage].forEach(el => el.addEventListener('change', () => loadHistory(1)));
			historyPagination.addEventListener('click', (e) => {
				if (e.target.matches('.page-link')) {
					e.preventDefault();
					loadHistory(parseInt(e.target.dataset.page));
				}
			});
			historyImagesContainer.addEventListener('click', (e) => {
				const card = e.target.closest('.history-image-card');
				if (card) {
					document.querySelectorAll('.history-image-card.selected').forEach(c => c.classList.remove('selected'));
					card.classList.add('selected');
				}
			});
			addSelectedHistoryImageBtn.addEventListener('click', () => {
				const selected = historyImagesContainer.querySelector('.history-image-card.selected');
				if (selected) openCropper(selected.dataset.path);
				else alert('Please select an image.');
			});
			uploadNewImageBtn.addEventListener('click', () => newImageUploadInput.click());
			newImageUploadInput.addEventListener('change', (e) => {
				const file = e.target.files[0];
				if (file) openCropper(URL.createObjectURL(file));
				e.target.value = '';
			});
			
			// Cropper Modal Events
			confirmCropBtn.addEventListener('click', () => {
				if (!cropper) return;
				const croppedDataUrl = cropper.getCroppedCanvas().toDataURL('image/png');
				processFinalImage(croppedDataUrl);
			});
			
			useFullImageBtn.addEventListener('click', async () => {
				if (!imageToCrop.src) return;
				const imageUrl = imageToCrop.src;
				
				useFullImageBtn.disabled = true;
				useFullImageBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
				
				try {
					// If it's a blob URL (from a new upload), we must upload it to get a persistent path.
					if (imageUrl.startsWith('blob:') || imageUrl.startsWith('data:')) {
						const blob = imageUrl.startsWith('blob:') ? await (await fetch(imageUrl)).blob() : dataURLtoBlob(imageUrl);
						const formData = new FormData();
						formData.append('image', blob, 'full-image.png');
						formData.append('_token', csrfToken);
						
						const response = await fetch('/image-mix/upload', {
							method: 'POST',
							body: formData,
							headers: { 'Accept': 'application/json' },
						});
						const data = await response.json();
						
						if (data.success) {
							processFinalImage(data.path);
						} else {
							throw new Error(data.error || 'Full image upload failed.');
						}
					} else {
						// It's already a persistent URL from history
						processFinalImage(imageUrl);
					}
				} catch (error) {
					alert('An error occurred: ' + error.message);
				} finally {
					useFullImageBtn.disabled = false;
					useFullImageBtn.innerHTML = 'Use Full Image (No Crop)';
				}
			});
			
			// Canvas Object Deletion
			window.addEventListener('keydown', (e) => {
				if ((e.key === 'Delete' || e.key === 'Backspace') && canvas) {
					const activeObject = canvas.getActiveObject();
					if (activeObject) {
						canvas.remove(activeObject);
						canvas.renderAll();
					}
				}
			});
			
			// --- Saving the Final Image (Original logic, unchanged) ---
			saveImageBtn.addEventListener('click', async () => {
				if (!canvas) return;
				
				saveImageBtn.disabled = true;
				saveImageBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
				
				const fullResCanvas = new fabric.StaticCanvas(null, { width: originalWidth, height: originalHeight });
				fullResCanvas.setBackgroundImage(backgroundImageObject, fullResCanvas.renderAll.bind(fullResCanvas), { scaleX: 1, scaleY: 1 });
				
				const clonePromises = canvas.getObjects().map(obj => new Promise(resolve => {
					obj.clone(cloned => {
						cloned.set({
							left: obj.left / currentScale,
							top: obj.top / currentScale,
							scaleX: obj.scaleX / currentScale,
							scaleY: obj.scaleY / currentScale,
						});
						resolve(cloned);
					});
				}));
				
				const clonedObjects = await Promise.all(clonePromises);
				clonedObjects.forEach(clonedObj => fullResCanvas.add(clonedObj));
				
				fullResCanvas.renderAll();
				const dataURL = fullResCanvas.toDataURL({ format: 'png', quality: 0.9 });
				fullResCanvas.dispose();
				
				try {
					const formData = new FormData();
					formData.append('imageData', dataURL);
					formData.append('_token', csrfToken);
					formData.append('return_url', @json($return_url));
					
					const response = await fetch('{{ route("image-editor.save") }}', { method: 'POST', body: formData });
					const result = await response.json();
					
					if (result.success) {
						if (window.opener && !window.opener.closed) {
							window.opener.location.href = result.redirect_url;
							window.close();
						} else {
							window.location.href = result.redirect_url;
						}
					} else {
						alert(`Error: ${result.message}`);
					}
				} catch (error) {
					console.error('Error saving image:', error);
					alert('An error occurred while trying to save the image.');
				} finally {
					saveImageBtn.disabled = false;
					saveImageBtn.textContent = 'Save and Return';
				}
			});
		});
	</script>
	{{-- END MODIFICATION --}}
@endsection
