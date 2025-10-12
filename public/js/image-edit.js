document.addEventListener('DOMContentLoaded', function () {
	const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
	
	// --- Image Selection & Cropper Logic (from story-editor.js) ---
	const cropperModalEl = document.getElementById('cropperModal');
	const cropperModal = new bootstrap.Modal(cropperModalEl);
	const imageToCrop = document.getElementById('imageToCrop');
	const historyModalEl = document.getElementById('historyModal');
	const historyModal = new bootstrap.Modal(historyModalEl);
	let cropper;
	let activeImageUploadContainer = document.getElementById('input-images-container'); // Target the main container
	const thumbnailTemplate = document.getElementById('input-image-thumbnail-template');
	
	function openCropper(imageUrl) {
		imageToCrop.src = imageUrl;
		cropperModal.show();
	};
	
	cropperModalEl.addEventListener('shown.bs.modal', function () {
		if (cropper) cropper.destroy();
		cropper = new Cropper(imageToCrop, {
			aspectRatio: 1,
			viewMode: 1,
			background: false,
		});
	});
	
	cropperModalEl.addEventListener('hidden.bs.modal', function () {
		if (cropper) {
			cropper.destroy();
			cropper = null;
		}
	});
	
	function dataURLtoBlob(dataurl) {
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
	
	function addImageToContainer(path) {
		if (!thumbnailTemplate || !activeImageUploadContainer) return;
		const clone = thumbnailTemplate.content.cloneNode(true);
		clone.querySelector('img').src = path;
		clone.querySelector('.image-path-input').value = path;
		activeImageUploadContainer.appendChild(clone);
	};
	
	document.getElementById('useFullImageBtn').addEventListener('click', async () => {
		if (!imageToCrop.src) return;
		const button = document.getElementById('useFullImageBtn');
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
		const imageUrl = imageToCrop.src;
		try {
			let finalPath = imageUrl;
			if (imageUrl.startsWith('data:image')) {
				const blob = dataURLtoBlob(imageUrl);
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
					finalPath = data.path;
				} else {
					throw new Error(data.error || 'Full image upload failed.');
				}
			}
			addImageToContainer(finalPath);
			cropperModal.hide();
		} catch (error) {
			alert('An error occurred: ' + error.message);
		} finally {
			button.disabled = false;
			button.innerHTML = 'Use Full Image (No Crop)';
		}
	});
	
	document.getElementById('confirmCropBtn').addEventListener('click', () => {
		if (!cropper) return;
		const button = document.getElementById('confirmCropBtn');
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
		cropper.getCroppedCanvas({ width: 1024, height: 1024 }).toBlob(async (blob) => {
			const formData = new FormData();
			formData.append('image', blob, 'cropped-image.png');
			formData.append('_token', csrfToken);
			try {
				const response = await fetch('/image-mix/upload', {
					method: 'POST',
					body: formData,
					headers: { 'Accept': 'application/json' },
				});
				const data = await response.json();
				if (data.success) {
					addImageToContainer(data.path);
					cropperModal.hide();
				} else {
					alert('Crop upload failed: ' + (data.error || 'Unknown error'));
				}
			} catch (error) {
				alert('An error occurred during crop upload.');
			} finally {
				button.disabled = false;
				button.innerHTML = 'Confirm Crop';
			}
		}, 'image/png');
	});
	
	// --- History Modal Logic ---
	async function loadHistory(page = 1) {
		const source = document.getElementById('historySource').value;
		const sort = document.getElementById('historySort').value;
		const perPage = document.getElementById('historyPerPage').value;
		const container = document.getElementById('historyImagesContainer');
		container.innerHTML = '<div class="d-flex justify-content-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		const endpoint = source === 'uploads' ? `/image-mix/uploads?page=${page}&sort=${sort}&perPage=${perPage}` : `/kontext-basic/render-history?page=${page}&sort=${sort}&perPage=${perPage}`;
		const response = await fetch(endpoint);
		const data = await response.json();
		container.innerHTML = '';
		const images = source === 'uploads' ? data.images : data.data;
		images.forEach(img => {
			const imageUrl = source === 'uploads' ? img.path : img.image_url;
			const thumbUrl = source === 'uploads' ? img.path : img.thumbnail_url;
			const name = source === 'uploads' ? img.name : img.generated_prompt;
			container.innerHTML += `
                <div class="col-lg-2 col-md-3 col-sm-4 mb-3">
                    <div class="card history-image-card" data-path="${imageUrl}">
                        <img src="${thumbUrl}" class="card-img-top" alt="${name}">
                        <div class="card-body p-1 small"><p class="card-text mb-0 text-truncate" title="${name}">${name}</p></div>
                    </div>
                </div>`;
		});
		const paginationData = source === 'uploads' ? data.pagination : { current_page: data.current_page, total_pages: data.last_page };
		renderPagination(document.getElementById('historyPagination'), paginationData);
	};
	
	function renderPagination(paginationContainer, data) {
		const historyPagination = document.getElementById('historyPagination');
		historyPagination.innerHTML = '';
		const currentPage = data.current_page;
		const totalPages = data.total_pages;
		if (!data || totalPages <= 1) return;
		let html = '';
		const windowSize = 1;
		const range = [];
		html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">«</a></li>`;
		for (let i = 1; i <= totalPages; i++) {
			if (i === 1 || i === totalPages || (i >= currentPage - windowSize && i <= currentPage + windowSize)) {
				range.push(i);
			}
		}
		let last = 0;
		for (const i of range) {
			if (last + 1 !== i) {
				html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
			}
			html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
			last = i;
		}
		html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">»</a></li>`;
		paginationContainer.innerHTML = html;
	};
	
	if (historyModalEl) {
		historyModalEl.addEventListener('click', e => {
			if (e.target.closest('.page-link')) {
				e.preventDefault();
				loadHistory(parseInt(e.target.dataset.page));
			} else if (e.target.closest('.history-image-card')) {
				document.querySelectorAll('.history-image-card.selected').forEach(c => c.classList.remove('selected'));
				e.target.closest('.history-image-card').classList.add('selected');
			}
		});
	}
	
	const uploadNewImageBtn = document.getElementById('uploadNewImageBtn');
	const newImageUploadInput = document.getElementById('newImageUploadInput');
	if (uploadNewImageBtn && newImageUploadInput) {
		uploadNewImageBtn.addEventListener('click', () => newImageUploadInput.click());
		newImageUploadInput.addEventListener('change', (event) => {
			const file = event.target.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = (e) => {
					openCropper(e.target.result);
					historyModal.hide();
				};
				reader.readAsDataURL(file);
				newImageUploadInput.value = '';
			}
		});
	}
	
	document.getElementById('addSelectedHistoryImageBtn').addEventListener('click', () => {
		const selected = document.querySelector('#historyModal .history-image-card.selected');
		if (selected) {
			openCropper(selected.dataset.path);
			historyModal.hide();
		} else {
			alert('Please select an image.');
		}
	});
	
	['historySource', 'historySort', 'historyPerPage'].forEach(id => {
		const el = document.getElementById(id);
		if (el) el.addEventListener('change', () => loadHistory(1));
	});
	
	// --- Page Specific Logic ---
	const addImageBtn = document.getElementById('add-image-btn');
	const generateBtn = document.getElementById('generate-btn');
	const aspectRatioSelect = document.getElementById('aspect-ratio');
	const widthInput = document.getElementById('width');
	const heightInput = document.getElementById('height');
	const resultContainer = document.getElementById('result-container');
	const spinnerContainer = document.getElementById('spinner-container');
	const resultImage = document.getElementById('result-image');
	
	addImageBtn.addEventListener('click', () => {
		loadHistory(1);
		historyModal.show();
	});
	
	activeImageUploadContainer.addEventListener('click', (e) => {
		if (e.target.closest('.btn-close')) {
			e.target.closest('.input-image-thumbnail').remove();
		}
	});
	
	aspectRatioSelect.addEventListener('change', function () {
		const [ratio, baseSize] = this.value.split('-');
		const sizes = {
			'1024': { '1:1': [1024, 1024], '3:2': [1216, 832], '4:3': [1152, 896], '16:9': [1344, 768], '21:9': [1536, 640], '2:3': [832, 1216], '3:4': [896, 1152], '9:16': [768, 1344], '9:21': [640, 1536] },
			'1408': { '1:1': [1408, 1408], '3:2': [1728, 1152], '4:3': [1664, 1216], '16:9': [1920, 1088], '21:9': [2176, 960], '2:3': [1152, 1728], '3:4': [1216, 1664], '9:16': [1088, 1920], '9:21': [960, 2176] }
		};
		const [width, height] = sizes[baseSize][ratio];
		widthInput.value = width;
		heightInput.value = height;
	});
	
	generateBtn.addEventListener('click', async () => {
		const promptText = document.getElementById('prompt').value;
		const inputImagePaths = Array.from(activeImageUploadContainer.querySelectorAll('.image-path-input')).map(input => input.value);
		
		if (!promptText) {
			alert('Please enter a prompt.');
			return;
		}
		if (inputImagePaths.length === 0) {
			alert('Please add at least one input image.');
			return;
		}
		
		const button = generateBtn;
		button.disabled = true;
		button.querySelector('.spinner-border').classList.remove('d-none');
		spinnerContainer.classList.add('d-flex');
		spinnerContainer.classList.remove('d-none');
		resultImage.src = '';
		
		const body = {
			prompt: promptText,
			width: widthInput.value,
			height: heightInput.value,
			upload_to_s3: document.getElementById('upload-to-s3').checked,
			aspect_ratio: aspectRatioSelect.value,
			input_images: inputImagePaths,
		};
		
		try {
			const response = await fetch('/image-edit/generate', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
				body: JSON.stringify(body),
			});
			const data = await response.json();
			if (response.ok && data.success) {
				startPolling(data.prompt_id);
			} else {
				throw new Error(data.message || 'Failed to start generation.');
			}
		} catch (error) {
			alert('An error occurred: ' + error.message);
			button.disabled = false;
			button.querySelector('.spinner-border').classList.add('d-none');
			spinnerContainer.classList.remove('d-flex');
			spinnerContainer.classList.add('d-none');
		}
	});
	
	function startPolling(promptId) {
		let pollAttempts = 0;
		const pollInterval = setInterval(async () => {
			if (++pollAttempts > 180) { // 15 minutes timeout
				clearInterval(pollInterval);
				spinnerContainer.classList.remove('d-flex');
				spinnerContainer.classList.add('d-none');
				alert('Image generation is taking longer than expected.');
				generateBtn.disabled = false;
				generateBtn.querySelector('.spinner-border').classList.add('d-none');
				return;
			}
			
			try {
				const statusResponse = await fetch(`/image-edit/status/${promptId}`);
				const statusData = await statusResponse.json();
				
				if (statusResponse.ok && statusData.success && statusData.status === 'ready') {
					clearInterval(pollInterval);
					spinnerContainer.classList.remove('d-flex');
					spinnerContainer.classList.add('d-none');
					resultImage.src = statusData.filename;
					generateBtn.disabled = false;
					generateBtn.querySelector('.spinner-border').classList.add('d-none');
				}
			} catch (pollError) {
				clearInterval(pollInterval);
				spinnerContainer.classList.remove('d-flex');
				spinnerContainer.classList.add('d-none');
				generateBtn.disabled = false;
				generateBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		}, 5000);
	};
});
