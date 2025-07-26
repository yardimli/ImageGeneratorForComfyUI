@extends('layouts.bootstrap-app') {{-- MODIFICATION: Use bootstrap layout for consistent UI --}}

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
	</style>
@endsection

@section('content')
	{{-- START MODIFICATION: Converted entire view to use Bootstrap 5 for UI consistency --}}
	<div class="container py-4">
		<div class="card shadow-sm">
			<div class="card-header">
				<h1 class="h3 mb-0">Image Editor</h1>
			</div>
			<div class="card-body">
				<!-- Step 1: Initial Background Upload -->
				<div id="initial-upload-view" class="text-center p-5 border-2 border-dashed rounded-lg">
					<h2 class="h4">Step 1: Upload Background Image</h2>
					<p class="text-muted mb-4">The canvas will be sized to fit your screen. The final image will be saved at this image's original resolution.</p>
					<label for="bg-uploader" class="btn btn-primary">
						Select Background
					</label>
					<input type="file" id="bg-uploader" class="d-none" accept="image/*">
				</div>
				
				<!-- Step 2: Main Application View (hidden initially) -->
				<div id="app-view" class="d-none">
					<!-- Toolbar -->
					<div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mb-3 p-3 bg-light rounded">
						<button id="add-image-btn" class="btn btn-success">
							Add Image
						</button>
						<input type="file" id="image-uploader" class="d-none" accept="image/*">
						
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
	
	<!-- Cropping Modal (Bootstrap 5) -->
	<div class="modal fade" id="crop-modal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="cropModalLabel">Crop Your Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="cropper-container-wrapper">
						<img id="image-to-crop" src="" alt="Image to crop" style="max-width: 100%;">
					</div>
				</div>
				<div class="modal-footer">
					<button id="cancel-crop-btn" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button id="confirm-crop-btn" type="button" class="btn btn-primary">Add to Canvas</button>
				</div>
			</div>
		</div>
	</div>
	{{-- END MODIFICATION --}}
@endsection

@section('scripts')
	{{-- MODIFICATION: Add required JS libraries for the editor --}}
	<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			// --- DOM Elements ---
			const initialUploadView = document.getElementById('initial-upload-view');
			const appView = document.getElementById('app-view');
			const bgUploader = document.getElementById('bg-uploader');
			const addImageBtn = document.getElementById('add-image-btn');
			const imageUploader = document.getElementById('image-uploader');
			const saveImageBtn = document.getElementById('save-image-btn');
			const imageToCrop = document.getElementById('image-to-crop');
			const confirmCropBtn = document.getElementById('confirm-crop-btn');
			const canvasContainer = document.getElementById('canvas-container');
			
			// START MODIFICATION: Use Bootstrap Modal API
			const cropModalEl = document.getElementById('crop-modal');
			const cropModal = new bootstrap.Modal(cropModalEl);
			// END MODIFICATION
			
			// --- App State ---
			let canvas; // The visible, responsive canvas
			let cropper;
			let backgroundImageObject; // The fabric.Image object for the background
			let originalWidth, originalHeight; // Dimensions of the original background
			let currentScale = 1; // The ratio of display size to original size
			
			// --- Utility: Debounce function for resize events ---
			function debounce(func, delay) {
				let timeout;
				return function(...args) {
					const context = this;
					clearTimeout(timeout);
					timeout = setTimeout(() => func.apply(context, args), delay);
				};
			}
			
			// --- Canvas Resizing Logic ---
			const resizeCanvas = () => {
				if (!canvas || !backgroundImageObject) return;
				
				const containerWidth = canvasContainer.offsetWidth;
				currentScale = containerWidth / originalWidth;
				const newHeight = originalHeight * currentScale;
				
				// Resize the display canvas
				canvas.setWidth(containerWidth);
				canvas.setHeight(newHeight);
				
				// Scale the background image to fit the new canvas size
				canvas.setBackgroundImage(backgroundImageObject, canvas.renderAll.bind(canvas), {
					scaleX: currentScale,
					scaleY: currentScale,
				});
				
				// Scale all foreground objects
				canvas.getObjects().forEach(obj => {
					obj.setCoords();
				});
				
				canvas.renderAll();
			};
			
			const debouncedResize = debounce(resizeCanvas, 150);
			
			// --- Step 1: Handle Background Image Upload ---
			bgUploader.addEventListener('change', (e) => {
				const file = e.target.files[0];
				if (!file) return;
				
				const reader = new FileReader();
				reader.onload = (event) => {
					canvas = new fabric.Canvas('c');
					
					fabric.Image.fromURL(event.target.result, (img) => {
						backgroundImageObject = img;
						originalWidth = img.width;
						originalHeight = img.height;
						
						initialUploadView.classList.add('d-none'); // Use d-none for bootstrap
						appView.classList.remove('d-none');
						
						resizeCanvas();
						window.addEventListener('resize', debouncedResize);
					});
				};
				reader.readAsDataURL(file);
			});
			
			// --- Step 2: Add New Images ---
			addImageBtn.addEventListener('click', () => imageUploader.click());
			
			imageUploader.addEventListener('change', (e) => {
				const file = e.target.files[0];
				if (!file) return;
				
				const reader = new FileReader();
				reader.onload = (event) => {
					imageToCrop.src = event.target.result;
					
					if (cropper) cropper.destroy();
					cropper = new Cropper(imageToCrop, { aspectRatio: 0, viewMode: 1, background: false });
					
					cropModal.show(); // Use Bootstrap modal API
				};
				reader.readAsDataURL(file);
				e.target.value = '';
			});
			
			// --- Cropping Modal Logic ---
			confirmCropBtn.addEventListener('click', () => {
				if (!cropper) return;
				
				const croppedImageData = cropper.getCroppedCanvas().toDataURL('image/png');
				
				fabric.Image.fromURL(croppedImageData, (fabricImage) => {
					fabricImage.set({
						left: canvas.width / 2,
						top: canvas.height / 2,
						originX: 'center',
						originY: 'center'
					});
					canvas.add(fabricImage);
					canvas.setActiveObject(fabricImage);
					canvas.renderAll();
				});
				
				cropModal.hide(); // Use Bootstrap modal API
			});
			
			// START MODIFICATION: Clean up cropper instance when modal is hidden
			cropModalEl.addEventListener('hidden.bs.modal', () => {
				if (cropper) {
					cropper.destroy();
					cropper = null;
				}
				imageToCrop.src = '';
			});
			// END MODIFICATION
			
			// --- Deleting Objects ---
			window.addEventListener('keydown', (e) => {
				if ((e.key === 'Delete' || e.key === 'Backspace') && canvas) {
					const activeObject = canvas.getActiveObject();
					if (activeObject) {
						canvas.remove(activeObject);
						canvas.renderAll();
					}
				}
			});
			
			// --- Saving the Canvas (The Core Logic) ---
			saveImageBtn.addEventListener('click', async () => {
				if (!canvas) return;
				
				saveImageBtn.disabled = true;
				saveImageBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
				
				// 1. Create a new, in-memory static canvas at the original resolution
				const fullResCanvas = new fabric.StaticCanvas(null, {
					width: originalWidth,
					height: originalHeight
				});
				
				// 2. Set the background image at its natural scale (1)
				fullResCanvas.setBackgroundImage(backgroundImageObject, fullResCanvas.renderAll.bind(fullResCanvas), {
					scaleX: 1,
					scaleY: 1
				});
				
				// 3. Clone all objects from the display canvas and transform them for the full-res canvas
				const clonePromises = canvas.getObjects().map(obj => {
					return new Promise(resolve => {
						obj.clone(cloned => {
							// Transform properties back to the original scale
							cloned.set({
								left: obj.left / currentScale,
								top: obj.top / currentScale,
								scaleX: obj.scaleX / currentScale,
								scaleY: obj.scaleY / currentScale,
							});
							resolve(cloned);
						});
					});
				});
				
				// 4. Wait for all cloning and transformations to complete
				const clonedObjects = await Promise.all(clonePromises);
				clonedObjects.forEach(clonedObj => fullResCanvas.add(clonedObj));
				
				// 5. Render the full-resolution canvas and get its data URL
				fullResCanvas.renderAll();
				const dataURL = fullResCanvas.toDataURL({ format: 'png', quality: 0.9 });
				
				// 6. Clean up the temporary canvas to free memory
				fullResCanvas.dispose();
				
				// 7. Post the data to the Laravel controller
				try {
					const formData = new FormData();
					formData.append('imageData', dataURL);
					formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
					formData.append('return_url', @json($return_url)); // Pass the return URL from the controller
					
					const response = await fetch('{{ route("image-editor.save") }}', { method: 'POST', body: formData });
					const result = await response.json();
					
					if (result.success) {
						// If the editor was opened in a new tab, update the original tab and close this one.
						if (window.opener && !window.opener.closed) {
							window.opener.location.href = result.redirect_url;
							window.close();
						} else {
							// Fallback: if it wasn't a popup, just redirect the current window.
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
@endsection
