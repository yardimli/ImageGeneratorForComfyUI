let leftImages = [];
let rightImages = [];
let leftImageCounter = 0;
let rightImageCounter = 0;

function setDimensions(width, height) {
	const widthInput = document.getElementById('width');
	const heightInput = document.getElementById('height');
	widthInput.value = width;
	heightInput.value = height;
}

function addLeftImage(imagePath = '', strength = 3, prompt = '', id = null) {
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
                        <label class="form-label">Strength (1 Strongest)</label>
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

function addRightImage(imagePath = '', strength = 3, id = null) {
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
                        <label class="form-label">Strength (1 Strongest)</label>
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
