@extends('layouts.editor-app')

@section('styles')
	<style>
      #canvas-container {
          box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
          line-height: 0;
      }
      .cropper-container-wrapper {
          max-height: 60vh;
      }
	</style>
@endsection

@section('content')
	<div class="w-full max-w-5xl bg-white rounded-lg shadow-xl p-6 mx-auto my-8 font-sans">
		<h1 class="text-2xl sm:text-3xl font-bold text-center text-gray-800 mb-4">Image Editor</h1>
		
		<!-- Step 1: Initial Background Upload -->
		<div id="initial-upload-view" class="text-center p-8 border-2 border-dashed rounded-lg">
			<h2 class="text-xl font-semibold text-gray-700 mb-2">Step 1: Upload Background Image</h2>
			<p class="text-gray-500 mb-4">The canvas will be sized to fit your screen. The final image will be saved at this image's original resolution.</p>
			<label for="bg-uploader" class="cursor-pointer bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 transition-colors">
				Select Background
			</label>
			<input type="file" id="bg-uploader" class="hidden" accept="image/*">
		</div>
		
		<!-- Step 2: Main Application View (hidden initially) -->
		<div id="app-view" class="hidden">
			<!-- Toolbar -->
			<div class="flex flex-wrap items-center justify-center gap-4 mb-4 p-4 bg-gray-50 rounded-md">
				<button id="add-image-btn" class="bg-green-500 text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">
					Add Image
				</button>
				<input type="file" id="image-uploader" class="hidden" accept="image/*">
				
				<button id="save-image-btn" class="bg-indigo-500 text-white font-bold py-2 px-4 rounded hover:bg-indigo-600 transition-colors">
					Save and Return
				</button>
				<p class="text-sm text-gray-600 w-full text-center sm:w-auto">
					Hint: Select an object and press 'Delete' to remove it.
				</p>
			</div>
			
			<!-- Canvas Container -->
			<div id="canvas-container" class="mx-auto w-full border border-gray-300">
				<canvas id="c"></canvas>
			</div>
		</div>
	</div>
	
	<!-- Cropping Modal (hidden initially) -->
	<div id="crop-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50">
		<div class="bg-white rounded-lg shadow-2xl p-6 w-full max-w-2xl">
			<h3 class="text-xl font-bold mb-4">Crop Your Image</h3>
			<div class="cropper-container-wrapper">
				<img id="image-to-crop" src="" alt="Image to crop">
			</div>
			<div class="flex justify-end gap-4 mt-4">
				<button id="cancel-crop-btn" class="bg-gray-500 text-white font-bold py-2 px-4 rounded hover:bg-gray-600">Cancel</button>
				<button id="confirm-crop-btn" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600">Add to Canvas</button>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			// --- DOM Elements ---
			const initialUploadView = document.getElementById('initial-upload-view');
			const appView = document.getElementById('app-view');
			const bgUploader = document.getElementById('bg-uploader');
			const addImageBtn = document.getElementById('add-image-btn');
			const imageUploader = document.getElementById('image-uploader');
			const saveImageBtn = document.getElementById('save-image-btn');
			const cropModal = document.getElementById('crop-modal');
			const imageToCrop = document.getElementById('image-to-crop');
			const confirmCropBtn = document.getElementById('confirm-crop-btn');
			const cancelCropBtn = document.getElementById('cancel-crop-btn');
			const canvasContainer = document.getElementById('canvas-container');
			
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
						
						initialUploadView.classList.add('hidden');
						appView.classList.remove('hidden');
						
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
					cropModal.classList.remove('hidden');
					imageToCrop.src = event.target.result;
					
					if (cropper) cropper.destroy();
					cropper = new Cropper(imageToCrop, { aspectRatio: 0, viewMode: 1, background: false });
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
				
				hideAndResetModal();
			});
			
			cancelCropBtn.addEventListener('click', hideAndResetModal);
			
			function hideAndResetModal() {
				cropModal.classList.add('hidden');
				if (cropper) {
					cropper.destroy();
					cropper = null;
				}
				imageToCrop.src = '';
			}
			
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
				saveImageBtn.textContent = 'Processing...';
				
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
					// START MODIFICATION: Use Laravel-specific form data
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
					// END MODIFICATION
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
