@extends('layouts.app')

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
		
		<div class="card mb-4">
			<div class="card-header">
				<h3 class="mb-0">Saved Settings</h3>
			</div>
			<div class="card-body">
				<select class="form-select" id="savedSettings">
					<option value="">Select saved settings</option>
					@foreach($settings as $setting)
						<option value="{{ $setting->id }}">
							{{ $setting->created_at->format('Y-m-d H:i') }} - {{ $setting->width }}x{{ $setting->height }} - {{ $setting->render_each_prompt_times }} images
						</option>
					@endforeach
				</select>
			</div>
		</div>
		
		<div class="container mb-4">
			<a href="{{ route('gallery.index') }}" class="btn btn-secondary">Go to Gallery</a>
			<a href="{{ route('prompts.index') }}" class="btn btn-secondary">Go to Prompts</a>
			<a href="{{ route('home') }}" class="btn btn-secondary">Back to Home</a>
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
@endsection

@section('scripts')
	<script src="{{ asset('js/bootstrap.min.js') }}"></script>
	<script>
		let queueUpdateInterval;
		let leftImages = [];
		let rightImages = [];
		let leftImageCounter = 0;
		let rightImageCounter = 0;
		
		function updateQueueCount() {
			fetch('/api/prompts/queue-count')
				.then(response => response.json())
				.then(data => {
					const queueCountElement = document.getElementById('queueCount');
					queueCountElement.textContent = data.count;
					queueCountElement.className = 'badge ' + (data.count > 10 ? 'bg-danger' : data.count > 5 ? 'bg-warning' : 'bg-primary');
				})
				.catch(error => console.error('Error fetching queue count:', error));
		}
		
		function setDimensions(width, height) {
			const widthInput = document.getElementById('width');
			const heightInput = document.getElementById('height');
			widthInput.value = width;
			heightInput.value = height;
		}
		
		function addLeftImage(imagePath = '', strength = 1, prompt = '', id = null) {
			if (!id) {
				id = 'left-' + leftImageCounter++;
			}
			
			const imageHtml = `
            <div class="card mb-3 image-card" id="${id}-card">
                <div class="card-body">
                    <div class="d-flex mb-2">
                        <div class="flex-grow-1">
                            <strong>Left Image ${leftImages.length + 1}</strong>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-image" data-id="${id}">Remove</button>
                    </div>
                    <div class="mb-3 text-center">
                        ${imagePath ? `<img src="${imagePath}" class="img-fluid mb-2" style="max-height: 200px;">` : '<div class="alert alert-secondary">No image selected</div>'}
                        <button type="button" class="btn btn-sm btn-primary upload-image" data-target="left" data-id="${id}">Upload Image</button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strength (1-5)</label>
                        <input type="range" class="form-range" min="1" max="5" step="1" value="${strength}" id="${id}-strength">
                        <div class="text-center"><span id="${id}-strength-value">${strength}</span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prompt</label>
                        <textarea class="form-control" id="${id}-prompt" rows="2">${prompt}</textarea>
                    </div>
                </div>
            </div>
        `;
			
			document.getElementById('leftImagesContainer').insertAdjacentHTML('beforeend', imageHtml);
			
			// Add event listeners for the new elements
			document.getElementById(`${id}-strength`).addEventListener('input', function() {
				document.getElementById(`${id}-strength-value`).textContent = this.value;
				updateLeftImagesJson();
			});
			
			document.getElementById(`${id}-prompt`).addEventListener('input', function() {
				updateLeftImagesJson();
			});
			
			document.querySelector(`#${id}-card .remove-image`).addEventListener('click', function() {
				const id = this.getAttribute('data-id');
				document.getElementById(`${id}-card`).remove();
				leftImages = leftImages.filter(img => img.id !== id);
				updateLeftImagesJson();
			});
			
			document.querySelector(`#${id}-card .upload-image`).addEventListener('click', function() {
				const id = this.getAttribute('data-id');
				document.getElementById('uploadTarget').value = id;
				const uploadModal = new bootstrap.Modal(document.getElementById('uploadImageModal'));
				uploadModal.show();
			});
			
			// Add the image to our tracking array
			leftImages.push({
				id: id,
				path: imagePath,
				strength: strength,
				prompt: prompt
			});
			
			updateLeftImagesJson();
		}
		
		function addRightImage(imagePath = '', strength = 1, id = null) {
			if (!id) {
				id = 'right-' + rightImageCounter++;
			}
			
			const imageHtml = `
            <div class="card mb-3 image-card" id="${id}-card">
                <div class="card-body">
                    <div class="d-flex mb-2">
                        <div class="flex-grow-1">
                            <strong>Right Image ${rightImages.length + 1}</strong>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-image" data-id="${id}">Remove</button>
                    </div>
                    <div class="mb-3 text-center">
                        ${imagePath ? `<img src="${imagePath}" class="img-fluid mb-2" style="max-height: 200px;">` : '<div class="alert alert-secondary">No image selected</div>'}
                        <button type="button" class="btn btn-sm btn-primary upload-image" data-target="right" data-id="${id}">Upload Image</button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strength (1-5)</label>
                        <input type="range" class="form-range" min="1" max="5" step="1" value="${strength}" id="${id}-strength">
                        <div class="text-center"><span id="${id}-strength-value">${strength}</span></div>
                    </div>
                </div>
            </div>
        `;
			
			document.getElementById('rightImagesContainer').insertAdjacentHTML('beforeend', imageHtml);
			
			// Add event listeners for the new elements
			document.getElementById(`${id}-strength`).addEventListener('input', function() {
				document.getElementById(`${id}-strength-value`).textContent = this.value;
				updateRightImagesJson();
			});
			
			document.querySelector(`#${id}-card .remove-image`).addEventListener('click', function() {
				const id = this.getAttribute('data-id');
				document.getElementById(`${id}-card`).remove();
				rightImages = rightImages.filter(img => img.id !== id);
				updateRightImagesJson();
			});
			
			document.querySelector(`#${id}-card .upload-image`).addEventListener('click', function() {
				const id = this.getAttribute('data-id');
				document.getElementById('uploadTarget').value = id;
				const uploadModal = new bootstrap.Modal(document.getElementById('uploadImageModal'));
				uploadModal.show();
			});
			
			// Add the image to our tracking array
			rightImages.push({
				id: id,
				path: imagePath,
				strength: strength
			});
			
			updateRightImagesJson();
		}
		
		function updateLeftImagesJson() {
			const updatedImages = leftImages.map(img => {
				const id = img.id;
				return {
					id: id,
					path: img.path,
					strength: parseFloat(document.getElementById(`${id}-strength`).value),
					prompt: document.getElementById(`${id}-prompt`).value
				};
			});
			
			document.getElementById('inputImages1Json').value = JSON.stringify(updatedImages);
			leftImages = updatedImages;
		}
		
		function updateRightImagesJson() {
			const updatedImages = rightImages.map(img => {
				const id = img.id;
				return {
					id: id,
					path: img.path,
					strength: parseFloat(document.getElementById(`${id}-strength`).value)
				};
			});
			
			document.getElementById('inputImages2Json').value = JSON.stringify(updatedImages);
			rightImages = updatedImages;
		}
		
		async function loadSettings(id) {
			try {
				const response = await fetch(`/image-mix/settings/${id}`);
				const data = await response.json();
				
				// Clear current images
				document.getElementById('leftImagesContainer').innerHTML = '';
				document.getElementById('rightImagesContainer').innerHTML = '';
				leftImages = [];
				rightImages = [];
				
				// Set basic form values
				document.getElementById('width').value = data.width;
				document.getElementById('height').value = data.height;
				
				// Find aspect ratio option that matches
				const aspectRatioSelect = document.getElementById('aspectRatio');
				for (let i = 0; i < aspectRatioSelect.options.length; i++) {
					const option = aspectRatioSelect.options[i];
					const [ratio, baseSize] = option.value.split('-');
					const [w, h] = ratio.split(':');
					
					if (option.value.includes(baseSize) &&
						((w/h) * data.height).toFixed(0) == data.width ||
						((h/w) * data.width).toFixed(0) == data.height) {
						aspectRatioSelect.value = option.value;
						break;
					}
				}
				
				document.querySelector('select[name="model"]').value = data.model;
				document.getElementById('uploadToS3').checked = data.upload_to_s3;
				document.querySelector('input[name="render_each_prompt_times"]').value = data.render_each_prompt_times;
				
				// Load left images
				const leftImagesData = JSON.parse(data.input_images_1);
				leftImagesData.forEach(img => {
					addLeftImage(img.path, img.strength, img.prompt, img.id);
				});
				
				// Load right images
				const rightImagesData = JSON.parse(data.input_images_2);
				rightImagesData.forEach(img => {
					addRightImage(img.path, img.strength, img.id);
				});
				
			} catch (error) {
				console.error('Error loading settings:', error);
				alert('Failed to load settings. Please try again.');
			}
		}
		
		document.addEventListener('DOMContentLoaded', function() {
			queueUpdateInterval = setInterval(updateQueueCount, 10000);
			updateQueueCount();
			
			window.addEventListener('beforeunload', () => {
				if (queueUpdateInterval) {
					clearInterval(queueUpdateInterval);
				}
			});
			
			// Add initial empty image to each side
			addLeftImage();
			addRightImage();
			
			// Event listener for aspect ratio change
			document.getElementById('aspectRatio').addEventListener('change', function() {
				const [ratio, baseSize] = this.value.split('-');
				const [w, h] = ratio.split(':');
				if (baseSize === '1024') {
					switch (ratio) {
						case '1:1': setDimensions(1024, 1024); break;
						case '3:2': setDimensions(1216, 832); break;
						case '4:3': setDimensions(1152, 896); break;
						case '16:9': setDimensions(1344, 768); break;
						case '21:9': setDimensions(1536, 640); break;
						case '2:3': setDimensions(832, 1216); break;
						case '3:4': setDimensions(896, 1152); break;
						case '9:16': setDimensions(768, 1344); break;
						case '9:21': setDimensions(640, 1536); break;
					}
				} else {
					switch (ratio) {
						case '1:1': setDimensions(1408, 1408); break;
						case '3:2': setDimensions(1728, 1152); break;
						case '4:3': setDimensions(1664, 1216); break;
						case '16:9': setDimensions(1920, 1088); break;
						case '21:9': setDimensions(2176, 960); break;
						case '2:3': setDimensions(1152, 1728); break;
						case '3:4': setDimensions(1216, 1664); break;
						case '9:16': setDimensions(1088, 1920); break;
						case '9:21': setDimensions(960, 2176); break;
					}
				}
			});
			
			// Event listeners for adding new images
			document.getElementById('addLeftImageBtn').addEventListener('click', function() {
				addLeftImage();
			});
			
			document.getElementById('addRightImageBtn').addEventListener('click', function() {
				addRightImage();
			});
			
			// Handle image upload
			document.getElementById('uploadImageBtn').addEventListener('click', async function() {
				const targetId = document.getElementById('uploadTarget').value;
				const fileInput = document.getElementById('imageUpload');
				
				if (!fileInput.files.length) {
					alert('Please select an image file');
					return;
				}
				
				const formData = new FormData();
				formData.append('image', fileInput.files[0]);
				formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
				
				try {
					const response = await fetch('/image-mix/upload', {
						method: 'POST',
						body: formData
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Update image in the UI and tracking arrays
						if (targetId.startsWith('left-')) {
							const index = leftImages.findIndex(img => img.id === targetId);
							if (index !== -1) {
								leftImages[index].path = data.path;
								const imgContainer = document.querySelector(`#${targetId}-card .text-center`);
								imgContainer.innerHTML = `
                                <img src="${data.path}" class="img-fluid mb-2" style="max-height: 200px;">
                                <button type="button" class="btn btn-sm btn-primary upload-image" data-target="left" data-id="${targetId}">Upload Image</button>
                            `;
								updateLeftImagesJson();
							}
						} else if (targetId.startsWith('right-')) {
							const index = rightImages.findIndex(img => img.id === targetId);
							if (index !== -1) {
								rightImages[index].path = data.path;
								const imgContainer = document.querySelector(`#${targetId}-card .text-center`);
								imgContainer.innerHTML = `
                                <img src="${data.path}" class="img-fluid mb-2" style="max-height: 200px;">
                                <button type="button" class="btn btn-sm btn-primary upload-image" data-target="right" data-id="${targetId}">Upload Image</button>
                            `;
								updateRightImagesJson();
							}
						}
						
						// Reattach event listener to the new upload button
						document.querySelector(`#${targetId}-card .upload-image`).addEventListener('click', function() {
							const id = this.getAttribute('data-id');
							document.getElementById('uploadTarget').value = id;
							const uploadModal = new bootstrap.Modal(document.getElementById('uploadImageModal'));
							uploadModal.show();
						});
						
						// Close the modal
						bootstrap.Modal.getInstance(document.getElementById('uploadImageModal')).hide();
						fileInput.value = '';
					} else {
						alert('Failed to upload image: ' + data.error);
					}
				} catch (error) {
					console.error('Error:', error);
					alert('An error occurred during upload');
				}
			});
			
			// Form submission
			document.getElementById('imageMixForm').addEventListener('submit', async function(e) {
				e.preventDefault();
				
				// Validate inputs
				if (leftImages.length === 0) {
					alert('Please add at least one image on the left side');
					return;
				}
				
				if (rightImages.length === 0) {
					alert('Please add at least one image on the right side');
					return;
				}
				
				// Check if all images have paths
				const missingLeftImage = leftImages.some(img => !img.path);
				const missingRightImage = rightImages.some(img => !img.path);
				
				if (missingLeftImage || missingRightImage) {
					alert('All image slots must have an uploaded image');
					return;
				}
				
				// Update form data before submit
				updateLeftImagesJson();
				updateRightImagesJson();
				
				// Submit the form
				const formData = new FormData(this);
				
				try {
					const response = await fetch('/image-mix/store', {
						method: 'POST',
						body: formData
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Show success modal
						const modal = new bootstrap.Modal(document.getElementById('promptQueuedModal'));
						modal.show();
						
						// Refresh saved settings dropdown
						const response = await fetch('/image-mix/settings/latest');
						const settingsData = await response.json();
						
						if (settingsData.success) {
							const select = document.getElementById('savedSettings');
							const option = document.createElement('option');
							option.value = settingsData.setting.id;
							option.text = `${settingsData.setting.created_at} - ${settingsData.setting.width}x${settingsData.setting.height} - ${settingsData.setting.render_each_prompt_times} images`;
							option.selected = true;
							
							// Add to top of dropdown
							if (select.options.length > 0) {
								select.insertBefore(option, select.options[0]);
							} else {
								select.appendChild(option);
							}
						}
					} else {
						alert('Error: ' + data.error);
					}
				} catch (error) {
					console.error('Error:', error);
					alert('An error occurred while processing your request');
				}
			});
			
			// Load saved settings
			document.getElementById('savedSettings').addEventListener('change', function() {
				const settingId = this.value;
				if (settingId) {
					loadSettings(settingId);
				}
			});
		});
	</script>
@endsection
