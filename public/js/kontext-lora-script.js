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
		selectImage(decodeURIComponent(editedImageUrl));
		// Clean up the URL to avoid re-triggering on refresh
		const newUrl = window.location.pathname;
		window.history.replaceState({}, document.title, newUrl);
	}
	// END MODIFICATION
	
	// --- Image Upload Logic ---
	document.getElementById('confirmUploadBtn').addEventListener('click', async function () {
		const form = document.getElementById('uploadImageForm');
		const formData = new FormData(form);
		formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
		
		const button = this;
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
		
		try {
			const response = await fetch('/image-mix/upload', {
				method: 'POST',
				body: formData,
				headers: {
					'Accept': 'application/json',
				},
			});
			const data = await response.json();
			if (data.success) {
				selectImage(data.path);
				uploadImageModal.hide();
			} else {
				alert('Upload failed: ' + data.error);
			}
		} catch (error) {
			alert('An error occurred during upload.');
			console.error(error);
		} finally {
			button.disabled = false;
			button.innerHTML = 'Upload';
			form.reset();
		}
	});
	
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
		
		addBtn.addEventListener('click', function() {
			const selected = modalEl.querySelector('.history-image-card.selected');
			if (selected) {
				selectImage(selected.dataset.path);
				(modalId === 'uploadHistoryModal' ? uploadHistoryModal : renderHistoryModal).hide();
			} else {
				alert('Please select an image first.');
			}
		});
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
