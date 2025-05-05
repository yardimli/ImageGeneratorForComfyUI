let leftImages = [];
let rightImages = [];
let leftImageCounter = 0;
let rightImageCounter = 0;

let historyImages = [];
let currentPage = 1;
let totalPages = 1;
let selectedImages = [];
let targetSide = '';

let prompts = [];
let promptCounter = 0;

let currentHistorySort = 'newest';
let currentHistoryPerPage = 12;


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
	
	const promptDisplay = document.querySelector('#singleMode').checked ? 'd-none' : '';
	
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
                     <div class="mb-3 ${promptDisplay}">
				                <label class="form-label">Prompt</label>
				                <textarea class="form-control" id="${id}-prompt" rows="2">${prompt}</textarea>
				            </div>
                </div>
            </div>
        `;
	
	document.getElementById('leftImagesContainer').insertAdjacentHTML('beforeend', imageHtml);
	
	// Add event listeners for the new elements
	document.getElementById(`${id}-strength`).addEventListener('input', function () {
		document.getElementById(`${id}-strength-value`).textContent = this.value;
		updateLeftImagesJson();
	});
	
	document.getElementById(`${id}-prompt`).addEventListener('input', function () {
		updateLeftImagesJson();
	});
	
	document.querySelector(`#${id}-card .remove-image`).addEventListener('click', function () {
		const id = this.getAttribute('data-id');
		document.getElementById(`${id}-card`).remove();
		leftImages = leftImages.filter(img => img.id !== id);
		updateLeftImagesJson();
	});
	
	document.querySelector(`#${id}-card .upload-image`).addEventListener('click', function () {
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
	document.getElementById(`${id}-strength`).addEventListener('input', function () {
		document.getElementById(`${id}-strength-value`).textContent = this.value;
		updateRightImagesJson();
	});
	
	document.querySelector(`#${id}-card .remove-image`).addEventListener('click', function () {
		const id = this.getAttribute('data-id');
		document.getElementById(`${id}-card`).remove();
		rightImages = rightImages.filter(img => img.id !== id);
		updateRightImagesJson();
	});
	
	document.querySelector(`#${id}-card .upload-image`).addEventListener('click', function () {
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

//use history functions

function openUploadHistory(side) {
	targetSide = side;
	selectedImages = [];
	document.getElementById('selectedCount').textContent = '0 selected';
	
	// NEW: Set dropdowns to current values when opening
	const sortDropdown = document.getElementById('historySort');
	const perPageDropdown = document.getElementById('historyPerPage');
	if (sortDropdown) sortDropdown.value = currentHistorySort;
	if (perPageDropdown) perPageDropdown.value = currentHistoryPerPage;
	
	loadUploadHistory(1); // Load page 1 with current settings
	const modal = new bootstrap.Modal(document.getElementById('uploadHistoryModal'));
	modal.show();
}

async function loadUploadHistory(page) {
	currentPage = page;
	const historyContainer = document.getElementById('historyImagesContainer');
	historyContainer.innerHTML = `
        <div class="col-12 text-center py-5">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
	
	try {
		// NEW: Include sort and perPage parameters in the fetch URL
		const response = await fetch(`/image-mix/uploads?page=${page}&sort=${currentHistorySort}&perPage=${currentHistoryPerPage}`);
		const data = await response.json();
		
		if (!response.ok) {
			throw new Error(data.message || `HTTP error! status: ${response.status}`);
		}
		
		historyImages = data.images;
		totalPages = data.pagination.total_pages;
		// Optional: Update currentHistoryPerPage if backend corrected it, though unlikely with validation
		// currentHistoryPerPage = data.pagination.per_page;
		
		renderHistoryImages();
		renderPagination();
		
	} catch (error) {
		console.error('Error loading upload history:', error);
		historyContainer.innerHTML = `
            <div class="col-12 text-center">
                <div class="alert alert-danger">Failed to load images. ${error.message}. Please try again.</div>
            </div>
        `;
		// Clear pagination if load fails
		document.getElementById('historyPagination').innerHTML = '';
	}
}

function renderHistoryImages() {
	const historyContainer = document.getElementById('historyImagesContainer');
	historyContainer.innerHTML = ''; // Clear previous content or spinner
	
	if (historyImages.length === 0) {
		historyContainer.innerHTML = `
            <div class="col-12 text-center">
                <div class="alert alert-info">No uploaded images found matching the criteria.</div>
            </div>
        `;
		return;
	}
	
	let html = '';
	historyImages.forEach((image, index) => {
		const isSelected = selectedImages.some(img => img.path === image.path);
		// Use a unique ID for the usage badge based on image path hash or index + page for stability across pages/sorts
		const uniqueBadgeId = `usage-${currentPage}-${index}`;
		html += `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4"> {{-- Adjusted grid for more items --}}
                <div class="card image-history-card h-100 ${isSelected ? 'border border-primary' : ''}">
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="text-center mb-2 position-relative flex-grow-1 d-flex align-items-center justify-content-center" style="min-height: 150px;">
                            <span class="position-absolute badge bg-primary" style="top: 5px; left: 5px; cursor: pointer; z-index: 1;" id="${uniqueBadgeId}" title="Used ${image.usage_count} time(s). Click to view generated images.">
                                ${image.usage_count || 0}
                            </span>
                            <img src="${image.path}" class="img-fluid" style="max-height: 150px; object-fit: contain;">
                        </div>
                        <div class="form-check mt-auto">
                            <input class="form-check-input" type="checkbox" value="${index}" id="check-${index}" ${isSelected ? 'checked' : ''}>
                            <label class="form-check-label small" for="check-${index}" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;" title="${image.name}">
                                ${image.name}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;
	});
	historyContainer.innerHTML = html;
	
	// Add event listeners to checkboxes, cards, and usage badges
	historyImages.forEach((image, index) => {
		const uniqueBadgeId = `usage-${currentPage}-${index}`;
		const badge = document.getElementById(uniqueBadgeId);
		if (badge) {
			badge.addEventListener('click', function (e) {
				e.stopPropagation(); // Prevent triggering the card click event
				window.open(`/gallery/filter?source_image=${encodeURIComponent(image.path)}`, '_blank');
			});
		}
		
		const checkbox = document.getElementById(`check-${index}`);
		if (checkbox) {
			checkbox.addEventListener('change', function () {
				toggleImageSelection(index);
			});
			const card = checkbox.closest('.image-history-card');
			if (card) {
				card.addEventListener('click', function (e) {
					if (e.target !== checkbox && !e.target.closest('input') && !e.target.closest('.badge')) { // Prevent toggle if clicking checkbox or badge
						checkbox.checked = !checkbox.checked;
						toggleImageSelection(index);
					}
				});
				card.style.cursor = 'pointer'; // Indicate card is clickable
			}
		}
	});
}

function toggleImageSelection(index) {
	const image = historyImages[index];
	const isAlreadySelected = selectedImages.some(img => img.path === image.path);
	
	// Get the card element correctly using the checkbox's closest method
	const checkbox = document.getElementById(`check-${index}`);
	const card = checkbox ? checkbox.closest('.image-history-card') : null;
	
	if (isAlreadySelected) {
		// Remove from selection
		selectedImages = selectedImages.filter(img => img.path !== image.path);
		if (card) {
			card.classList.remove('border', 'border-primary');
		}
	} else {
		// Add to selection
		selectedImages.push(image);
		if (card) {
			card.classList.add('border', 'border-primary');
		}
	}
	
	document.getElementById('selectedCount').textContent = `${selectedImages.length} selected`;
}

function renderPagination() {
	const paginationContainer = document.getElementById('historyPagination');
	let html = '';
	
	// Previous button
	html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `;
	
	// Page numbers
	for (let i = 1; i <= totalPages; i++) {
		if (
			i === 1 || // First page
			i === totalPages || // Last page
			(i >= currentPage - 2 && i <= currentPage + 2) // Pages around current
		) {
			html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
		} else if (
			(i === currentPage - 3 && currentPage > 3) ||
			(i === currentPage + 3 && currentPage < totalPages - 2)
		) {
			html += `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`;
		}
	}
	
	// Next button
	html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;
	
	paginationContainer.innerHTML = html;
	
	// Add event listeners to pagination links
	document.querySelectorAll('#historyPagination .page-link').forEach(link => {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			const page = parseInt(this.getAttribute('data-page'));
			if (!isNaN(page) && page > 0 && page <= totalPages) {
				loadUploadHistory(page);
			}
		});
	});
}

function addSelectedImages() {
	if (selectedImages.length === 0) {
		alert('Please select at least one image.');
		return;
	}
	
	if (targetSide === 'left') {
		selectedImages.forEach(image => {
			addLeftImage(image.path, 3, image.prompt || '');
		});
	} else if (targetSide === 'right') {
		selectedImages.forEach(image => {
			addRightImage(image.path, 3);
		});
	}
	
	// Close the modal
	bootstrap.Modal.getInstance(document.getElementById('uploadHistoryModal')).hide();
}

function filterEmptyImageBoxes() {
	// Filter left images
	leftImages = leftImages.filter(img => img.path && img.path.trim() !== '');
	updateLeftImagesJson();
	
	// Filter right images
	rightImages = rightImages.filter(img => img.path && img.path.trim() !== '');
	updateRightImagesJson();
	
	// Remove empty image boxes from the DOM
	document.querySelectorAll('#leftImagesContainer .image-card').forEach(card => {
		const id = card.id.replace('-card', '');
		const imageFound = leftImages.some(img => img.id === id);
		if (!imageFound) {
			card.remove();
		}
	});
	
	document.querySelectorAll('#rightImagesContainer .image-card').forEach(card => {
		const id = card.id.replace('-card', '');
		const imageFound = rightImages.some(img => img.id === id);
		if (!imageFound) {
			card.remove();
		}
	});
}

function addPrompt(promptText = '') {
	const id = 'prompt-' + promptCounter++;
	const promptHtml = `
    <div class="card mb-3 prompt-card" id="${id}-card">
        <div class="card-body">
            <div class="d-flex mb-2">
                <div class="flex-grow-1">
                    <strong>Prompt ${prompts.length + 1}</strong>
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-prompt" data-id="${id}">Remove</button>
            </div>
            <div class="mb-3">
                <textarea class="form-control" id="${id}-text" rows="3">${promptText}</textarea>
            </div>
        </div>
    </div>`;
	
	document.getElementById('rightPromptsContainer').insertAdjacentHTML('beforeend', promptHtml);
	
	// Add event listeners
	document.querySelector(`#${id}-card .remove-prompt`).addEventListener('click', function () {
		const id = this.getAttribute('data-id');
		document.getElementById(`${id}-card`).remove();
		prompts = prompts.filter(p => p.id !== id);
		updatePromptsJson();
	});
	
	document.getElementById(`${id}-text`).addEventListener('input', function () {
		updatePromptsJson();
	});
	
	// Add to tracking array
	prompts.push({
		id: id,
		text: promptText
	});
	
	updatePromptsJson();
}

function updatePromptsJson() {
	const updatedPrompts = prompts.map(p => {
		const id = p.id;
		return {
			id: id,
			text: document.getElementById(`${id}-text`).value
		};
	});
	
	// Store prompts in input_images_2 field for single mode
	document.getElementById('inputImages2Json').value = JSON.stringify(updatedPrompts);
	prompts = updatedPrompts;
}

document.addEventListener('DOMContentLoaded', function () {
	// Add initial empty image to each side
	addLeftImage();
	addRightImage();
	
	document.querySelector('#singleMode').addEventListener('change', function () {
		if (this.checked) {
			// Hide dual mode elements
			document.getElementById('rightImagesContainer').classList.add('d-none');
			document.getElementById('dualModeButtons').classList.add('d-none');
			
			// Show single mode elements
			document.getElementById('rightPromptsContainer').classList.remove('d-none');
			document.getElementById('singleModeButtons').classList.remove('d-none');
			
			// Hide prompts under left images
			document.querySelectorAll('#leftImagesContainer .image-card textarea').forEach(textarea => {
				textarea.closest('.mb-3').classList.add('d-none');
			});
			
			// Empty the right images when switching to single mode
			rightImages = [];
			updateRightImagesJson();
			
			// Add an initial prompt if there are none
			if (prompts.length === 0) {
				addPrompt();
			}
		}
	});

// Modify the existing dualMode event listener
	document.querySelector('#dualMode').addEventListener('change', function () {
		if (this.checked) {
			// Show dual mode elements
			document.getElementById('rightImagesContainer').classList.remove('d-none');
			document.getElementById('dualModeButtons').classList.remove('d-none');
			
			// Hide single mode elements
			document.getElementById('rightPromptsContainer').classList.add('d-none');
			document.getElementById('singleModeButtons').classList.add('d-none');
			
			// Show prompts under left images
			document.querySelectorAll('#leftImagesContainer .image-card textarea').forEach(textarea => {
				textarea.closest('.mb-3').classList.remove('d-none');
			});
			
			// Add an empty image if there are none
			if (rightImages.length === 0) {
				addRightImage();
			}
			
			// Empty the prompts when switching to dual mode
			prompts = [];
			updatePromptsJson();
		}
	});
	
	document.getElementById('addPromptBtn').addEventListener('click', function () {
		addPrompt();
	});
	
	// Event listener for aspect ratio change
	document.getElementById('aspectRatio').addEventListener('change', function () {
		const [ratio, baseSize] = this.value.split('-');
		const [w, h] = ratio.split(':');
		if (baseSize === '1024') {
			switch (ratio) {
				case '1:1':
					setDimensions(1024, 1024);
					break;
				case '3:2':
					setDimensions(1216, 832);
					break;
				case '4:3':
					setDimensions(1152, 896);
					break;
				case '16:9':
					setDimensions(1344, 768);
					break;
				case '21:9':
					setDimensions(1536, 640);
					break;
				case '2:3':
					setDimensions(832, 1216);
					break;
				case '3:4':
					setDimensions(896, 1152);
					break;
				case '9:16':
					setDimensions(768, 1344);
					break;
				case '9:21':
					setDimensions(640, 1536);
					break;
			}
		} else {
			switch (ratio) {
				case '1:1':
					setDimensions(1408, 1408);
					break;
				case '3:2':
					setDimensions(1728, 1152);
					break;
				case '4:3':
					setDimensions(1664, 1216);
					break;
				case '16:9':
					setDimensions(1920, 1088);
					break;
				case '21:9':
					setDimensions(2176, 960);
					break;
				case '2:3':
					setDimensions(1152, 1728);
					break;
				case '3:4':
					setDimensions(1216, 1664);
					break;
				case '9:16':
					setDimensions(1088, 1920);
					break;
				case '9:21':
					setDimensions(960, 2176);
					break;
			}
		}
	});
	
	// Event listeners for adding new images
	document.getElementById('addLeftImageBtn').addEventListener('click', function () {
		addLeftImage();
	});
	
	document.getElementById('addRightImageBtn').addEventListener('click', function () {
		addRightImage();
	});
	
	// Handle image upload
	document.getElementById('uploadImageBtn').addEventListener('click', async function () {
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
				document.querySelector(`#${targetId}-card .upload-image`).addEventListener('click', function () {
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
	document.getElementById('imageMixForm').addEventListener('submit', async function (e) {
		e.preventDefault();
		
		filterEmptyImageBoxes();
		
		// Validate inputs
		if (leftImages.length === 0) {
			alert('Please add at least one image on the left side');
			return;
		}
		
		const mixMode = document.querySelector('input[name="mixMode"]:checked').value;
		const isSingleMode = document.querySelector('#singleMode').checked;
		
		if (mixMode === 'mix' && rightImages.length === 0) {
			alert('Please add at least one image on the right side for dual mix mode');
			return;
		}
		
		// Check if all images have paths
		const missingLeftImage = leftImages.some(img => !img.path);
		const missingRightImage = mixMode === 'mix' && rightImages.some(img => !img.path);
		
		if (missingLeftImage || missingRightImage) {
			alert('All image slots must have an uploaded image');
			return;
		}
		
		// Update form data before submit
		updateLeftImagesJson();
		if (isSingleMode) {
			updatePromptsJson();
		} else {
			updateRightImagesJson();
		}
		// Submit the form
		const formData = new FormData(this);
		formData.append('generation_type', mixMode);
		
		
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
			} else {
				alert('Error: ' + data.error);
			}
		} catch (error) {
			console.error('Error:', error);
			alert('An error occurred while processing your request');
		}
	});
	
	// Add event listeners for upload history buttons
	document.getElementById('leftUploadHistoryBtn').addEventListener('click', function () {
		openUploadHistory('left');
	});
	
	document.getElementById('rightUploadHistoryBtn').addEventListener('click', function () {
		openUploadHistory('right');
	});
	
	// Add event listener for adding selected images
	document.getElementById('addSelectedImagesBtn').addEventListener('click', function () {
		addSelectedImages();
	});
});
