document.addEventListener('DOMContentLoaded', function () {
	const imageContainer = document.getElementById('imageContainer');
	const imageUrlInput = document.getElementById('imageUrlInput');
	// Use the new form ID
	const kontextLoraForm = document.getElementById('kontextLoraForm');
	
	// Modals
	const uploadImageModal = new bootstrap.Modal(document.getElementById('uploadImageModal'));
	const uploadHistoryModal = new bootstrap.Modal(document.getElementById('uploadHistoryModal'));
	const renderHistoryModal = new bootstrap.Modal(document.getElementById('renderHistoryModal'));
	const promptQueuedModal = new bootstrap.Modal(document.getElementById('promptQueuedModal'));
	// START MODIFICATION: Add cropper modal elements
	const cropperModalEl = document.getElementById('cropperModal');
	const cropperModal = new bootstrap.Modal(cropperModalEl);
	const imageToCrop = document.getElementById('imageToCrop');
	let cropper;
	// END MODIFICATION
	
	// Buttons
	document.getElementById('uploadBtn').addEventListener('click', () => uploadImageModal.show());
	document.getElementById('uploadHistoryBtn').addEventListener('click', () => {
		loadUploadHistory(1);
		uploadHistoryModal.show();
	});
	document.getElementById('renderHistoryBtn').addEventListener('click', () => {
		loadRenderHistory(1);
		renderHistoryModal.show();
	});
	
	// START MODIFICATION: Add logic for Lora dropdown info panel.
	const loraSelect = document.getElementById('loraNameSelect');
	const loraInfo = document.getElementById('loraInfo');
	
	function updateLoraInfo() {
		if (!loraSelect || !loraInfo) return;
		
		const selectedOption = loraSelect.options[loraSelect.selectedIndex];
		if (selectedOption && selectedOption.value) {
			const trigger = selectedOption.dataset.trigger;
			const notes = selectedOption.dataset.notes;
			
			let infoHtml = `<strong>Trigger:</strong> ${trigger}`;
			if (notes) {
				infoHtml += ` <strong>Notes:</strong> ${notes}`;
			}
			loraInfo.innerHTML = infoHtml;
		} else {
			loraInfo.innerHTML = 'Select a Lora to see its trigger and notes.';
		}
	}
	
	if (loraSelect) {
		loraSelect.addEventListener('change', updateLoraInfo);
		// Initial call to set info on page load.
		updateLoraInfo();
	}
	// END MODIFICATION
	
	// --- Image Selection Logic ---
	function selectImage(url) {
		imageUrlInput.value = url;
		imageContainer.innerHTML = `<img src="${url}" style="max-width: 100%; max-height: 250px; object-fit: contain;" alt="Selected Image">`;
	}
	
	// START MODIFICATION: Handle returning from image editor
	const urlParams = new URLSearchParams(window.location.search);
	const editedImageUrl = urlParams.get('edited_image_url');
	if (editedImageUrl) {
		openCropper(decodeURIComponent(editedImageUrl)); // Open cropper instead of selecting directly
		// Clean up the URL to avoid re-triggering on refresh
		const newUrl = window.location.pathname;
		window.history.replaceState({}, document.title, newUrl);
	}
	// END MODIFICATION
	
	// START MODIFICATION: Cropper Logic
	function openCropper(imageUrl) {
		imageToCrop.src = imageUrl;
		cropperModal.show();
	}
	
	cropperModalEl.addEventListener('shown.bs.modal', function () {
		if (cropper) {
			cropper.destroy();
		}
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
	
	document.getElementById('confirmCropBtn').addEventListener('click', () => {
		if (!cropper) return;
		
		const button = document.getElementById('confirmCropBtn');
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
		
		cropper.getCroppedCanvas({ width: 1024, height: 1024 }).toBlob(async (blob) => {
			const formData = new FormData();
			formData.append('image', blob, 'cropped-image.png');
			formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
			
			try {
				const response = await fetch('/image-mix/upload', {
					method: 'POST',
					body: formData,
					headers: { 'Accept': 'application/json' },
				});
				const data = await response.json();
				if (data.success) {
					selectImage(data.path);
					cropperModal.hide();
				} else {
					alert('Crop upload failed: ' + (data.error || 'Unknown error'));
				}
			} catch (error) {
				alert('An error occurred during crop upload.');
				console.error(error);
			} finally {
				button.disabled = false;
				button.innerHTML = 'Confirm Crop';
			}
		}, 'image/png');
	});
	// END MODIFICATION
	
	// --- Image Upload Logic ---
	// START MODIFICATION: Changed to open cropper instead of direct upload
	document.getElementById('confirmUploadBtn').addEventListener('click', function () {
		const form = document.getElementById('uploadImageForm');
		const fileInput = document.getElementById('imageUpload');
		const file = fileInput.files[0];
		
		if (!file) {
			alert('Please select an image file.');
			return;
		}
		
		const reader = new FileReader();
		reader.onload = (e) => {
			openCropper(e.target.result);
			uploadImageModal.hide();
		};
		reader.readAsDataURL(file);
		form.reset();
	});
	// END MODIFICATION
	
	// --- Shared History Modal Logic ---
	function setupHistoryModal(modalId, containerId, paginationId, loadFunction, addBtnId) {
		const modalEl = document.getElementById(modalId);
		const sortEl = modalEl.querySelector('select[id$="Sort"]');
		const perPageEl = modalEl.querySelector('select[id$="PerPage"]');
		const addBtn = document.getElementById(addBtnId);
		
		sortEl.addEventListener('change', () => loadFunction(1));
		perPageEl.addEventListener('change', () => loadFunction(1));
		
		modalEl.addEventListener('click', function(e) {
			if (e.target.closest('.page-link')) {
				e.preventDefault();
				const page = e.target.closest('.page-link').dataset.page;
				if (page) {
					loadFunction(parseInt(page));
				}
			} else if (e.target.closest('.history-image-card')) {
				const card = e.target.closest('.history-image-card');
				// Single selection logic
				modalEl.querySelectorAll('.history-image-card.selected').forEach(c => c.classList.remove('selected'));
				card.classList.add('selected');
			}
		});
		
		// START MODIFICATION: Open cropper on selection
		addBtn.addEventListener('click', function() {
			const selected = modalEl.querySelector('.history-image-card.selected');
			if (selected) {
				openCropper(selected.dataset.path);
				(modalId === 'uploadHistoryModal' ? uploadHistoryModal : renderHistoryModal).hide();
			} else {
				alert('Please select an image first.');
			}
		});
		// END MODIFICATION
	}
	
	// --- Upload History Logic ---
	async function loadUploadHistory(page = 1) {
		const container = document.getElementById('historyImagesContainer');
		const sort = document.getElementById('historySort').value;
		const perPage = document.getElementById('historyPerPage').value;
		container.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
		
		const response = await fetch(`/image-mix/uploads?page=${page}&sort=${sort}&perPage=${perPage}`);
		const data = await response.json();
		
		container.innerHTML = '';
		data.images.forEach(img => {
			container.innerHTML += `
                        <div class="col-md-2 mb-3">
                            <div class="card history-image-card" data-path="${img.path}">
                                <img src="${img.path}" class="card-img-top" alt="${img.name}">
                                <div class="card-body p-1 small">
                                    <p class="card-text mb-0 text-truncate" title="${img.name}">${img.name}</p>
                                    <p class="card-text text-muted">Used: ${img.usage_count}</p>
                                </div>
                            </div>
                        </div>
                    `;
		});
		renderPagination(document.getElementById('historyPagination'), data.pagination, 'upload');
	}
	
	// --- Render History Logic ---
	async function loadRenderHistory(page = 1) {
		const container = document.getElementById('renderHistoryImagesContainer');
		const sort = document.getElementById('renderHistorySort').value;
		const perPage = document.getElementById('renderHistoryPerPage').value;
		container.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
		
		// Use the new render history endpoint
		const response = await fetch(`/kontext-lora/render-history?page=${page}&sort=${sort}&perPage=${perPage}`);
		const data = await response.json();
		
		container.innerHTML = '';
		data.data.forEach(render => {
			container.innerHTML += `
                        <div class="col-md-2 mb-3">
                            <div class="card history-image-card" data-path="${render.image_url}">
                                <img src="${render.thumbnail_url}" class="card-img-top" alt="Rendered Image">
                                <div class="card-body p-1 small">
                                    <p class="card-text mb-0 text-truncate" title="${render.generated_prompt}">${render.generated_prompt}</p>
                                </div>
                            </div>
                        </div>
                    `;
		});
		
		const paginationData = {
			current_page: data.current_page,
			total_pages: data.last_page,
		};
		renderPagination(document.getElementById('renderHistoryPagination'), paginationData, 'render');
	}
	
	function renderPagination(container, data, type) {
		container.innerHTML = '';
		if (data.total_pages <= 1) return;
		
		for (let i = 1; i <= data.total_pages; i++) {
			const liClass = i === data.current_page ? 'page-item active' : 'page-item';
			container.innerHTML += `<li class="${liClass}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
		}
	}
	
	setupHistoryModal('uploadHistoryModal', 'historyImagesContainer', 'historyPagination', loadUploadHistory, 'addSelectedHistoryImageBtn');
	setupHistoryModal('renderHistoryModal', 'renderHistoryImagesContainer', 'renderHistoryPagination', loadRenderHistory, 'addSelectedRenderImageBtn');
	
	
	// --- Form Submission ---
	kontextLoraForm.addEventListener('submit', async function (e) {
		e.preventDefault();
		
		if (!imageUrlInput.value) {
			alert('Please select an input image.');
			return;
		}
		if (!document.getElementById('promptTextarea').value) {
			alert('Please enter a prompt.');
			return;
		}
		
		const formData = new FormData(this);
		formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
		const button = this.querySelector('button[type="submit"]');
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
		
		try {
			const response = await fetch(this.action, {
				method: 'POST',
				body: formData,
				headers: {
					'Accept': 'application/json',
				},
			});
			
			const data = await response.json();
			if (data.success) {
				promptQueuedModal.show();
				this.reset();
				imageContainer.innerHTML = '<p class="text-muted">Select an image</p>';
				imageUrlInput.value = '';
				updateLoraInfo(); // Reset lora info panel
				updateQueueCount();
			} else {
				alert('Error: ' + (data.error || 'An unknown error occurred.'));
			}
		} catch (error) {
			alert('A network error occurred.');
			console.error(error);
		} finally {
			button.disabled = false;
			button.innerHTML = 'Generate';
		}
	});
});
