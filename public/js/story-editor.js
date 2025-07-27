document.addEventListener('DOMContentLoaded', function () {
	const pagesContainer = document.getElementById('pages-container');
	const addPageBtn = document.getElementById('add-page-btn');
	const pageTemplate = document.getElementById('page-template');
	
	// --- Image Handling Logic (shared with story-asset-manager.js) ---
	const cropperModalEl = document.getElementById('cropperModal');
	const cropperModal = new bootstrap.Modal(cropperModalEl);
	const imageToCrop = document.getElementById('imageToCrop');
	const historyModalEl = document.getElementById('historyModal');
	const historyModal = new bootstrap.Modal(historyModalEl);
	const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
	let cropper;
	let activeImageUploadContainer = null;
	
	function openCropper(imageUrl) {
		imageToCrop.src = imageUrl;
		cropperModal.show();
	}
	
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
	
	// START MODIFICATION: Add handler for "Use Full Image" button and its helper function.
	/**
	 * Converts a data URL string to a Blob object.
	 * @param {string} dataurl - The data URL to convert.
	 * @returns {Blob}
	 */
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
	}
	
	document.getElementById('useFullImageBtn').addEventListener('click', async () => {
		if (!imageToCrop.src || !activeImageUploadContainer) return;
		
		const button = document.getElementById('useFullImageBtn');
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
		
		const imageUrl = imageToCrop.src;
		
		try {
			let finalPath = imageUrl;
			
			// If it's a data URL (from a new upload), we need to upload it to get a server path.
			if (imageUrl.startsWith('data:image')) {
				const blob = dataURLtoBlob(imageUrl);
				const formData = new FormData();
				formData.append('image', blob, 'full-image.png'); // Filename can be anything.
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
			
			// Now update the UI with the final path (either original or newly uploaded).
			activeImageUploadContainer.querySelector('img').src = finalPath;
			activeImageUploadContainer.closest('.card-body').querySelector('.image-path-input').value = finalPath;
			cropperModal.hide();
			
		} catch (error) {
			alert('An error occurred: ' + error.message);
		} finally {
			button.disabled = false;
			button.innerHTML = 'Use Full Image (No Crop)';
		}
	});
	// END MODIFICATION
	
	document.getElementById('confirmCropBtn').addEventListener('click', () => {
		if (!cropper || !activeImageUploadContainer) return;
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
					activeImageUploadContainer.querySelector('img').src = data.path;
					activeImageUploadContainer.closest('.card-body').querySelector('.image-path-input').value = data.path;
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
	}
	
	function renderPagination(container, data) {
		container.innerHTML = '';
		if (!data || data.total_pages <= 1) return;
		for (let i = 1; i <= data.total_pages; i++) {
			container.innerHTML += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
		}
	}
	
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
	
	// START MODIFICATION: Add handlers for new image upload from history modal.
	const uploadNewImageBtn = document.getElementById('uploadNewImageBtn');
	const newImageUploadInput = document.getElementById('newImageUploadInput');
	
	if (uploadNewImageBtn && newImageUploadInput) {
		uploadNewImageBtn.addEventListener('click', () => {
			newImageUploadInput.click();
		});
		
		newImageUploadInput.addEventListener('change', (event) => {
			const file = event.target.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = (e) => {
					openCropper(e.target.result);
					historyModal.hide();
				};
				reader.readAsDataURL(file);
				newImageUploadInput.value = ''; // Reset for same-file selection.
			}
		});
	}
	// END MODIFICATION
	
	const addSelectedHistoryImageBtn = document.getElementById('addSelectedHistoryImageBtn');
	if (addSelectedHistoryImageBtn) {
		addSelectedHistoryImageBtn.addEventListener('click', () => {
			const selected = document.querySelector('#historyModal .history-image-card.selected');
			if (selected) {
				openCropper(selected.dataset.path);
				historyModal.hide();
			} else {
				alert('Please select an image.');
			}
		});
	}
	
	['historySource', 'historySort', 'historyPerPage'].forEach(id => {
		const el = document.getElementById(id);
		if (el) el.addEventListener('change', () => loadHistory(1));
	});
	
	// --- Page Management Logic ---
	function reindexPages() {
		const pageCards = pagesContainer.querySelectorAll('.page-card');
		pageCards.forEach((card, index) => {
			// Update page number display
			card.querySelector('.page-number').textContent = index + 1;
			
			// Update names and IDs of all form elements
			card.querySelectorAll('[name]').forEach(input => {
				const name = input.getAttribute('name');
				if (name) {
					const newName = name.replace(/pages\[(?:\d+|__INDEX__)\]/, `pages[${index}]`);
					input.setAttribute('name', newName);
				}
			});
			card.querySelectorAll('[id^="char_"], [id^="place_"]').forEach(input => {
				const id = input.getAttribute('id');
				if (id) {
					const newId = id.replace(/_\d+_/, `_${index}_`);
					const oldId = input.getAttribute('id');
					input.setAttribute('id', newId);
					const label = card.querySelector(`label[for="${oldId}"]`);
					if (label) {
						label.setAttribute('for', newId);
					}
				}
			});
		});
	}
	
	if (addPageBtn) {
		addPageBtn.addEventListener('click', () => {
			const newPageHtml = pageTemplate.innerHTML.replace(/__INDEX__/g, pagesContainer.children.length);
			pagesContainer.insertAdjacentHTML('beforeend', newPageHtml);
			reindexPages();
		});
	}
	
	if (pagesContainer) {
		pagesContainer.addEventListener('click', (e) => {
			// Handle remove button
			if (e.target.matches('.remove-page-btn')) {
				if (confirm('Are you sure you want to remove this page? This will happen immediately when you save the story.')) {
					e.target.closest('.page-card').remove();
					reindexPages();
				}
			}
			
			// Handle image selection
			if (e.target.matches('.select-image-btn')) {
				activeImageUploadContainer = e.target.closest('.col-md-4').querySelector('.image-upload-container');
				loadHistory(1);
				historyModal.show();
			}
		});
	}
	
	// START MODIFICATION: Logic for AI Image Prompt Generation
	const generatePromptModalEl = document.getElementById('generatePromptModal');
	if (generatePromptModalEl) {
		const generatePromptModal = new bootstrap.Modal(generatePromptModalEl);
		const writePromptBtn = document.getElementById('write-prompt-btn');
		const updatePromptBtn = document.getElementById('update-prompt-btn');
		const promptResultArea = document.getElementById('prompt-result-area');
		const generatedPromptText = document.getElementById('generated-prompt-text');
		let activeImagePromptTextarea = null;
		
		// Reset modal on close
		generatePromptModalEl.addEventListener('hidden.bs.modal', () => {
			activeImagePromptTextarea = null;
			promptResultArea.classList.add('d-none');
			updatePromptBtn.classList.add('d-none');
			generatedPromptText.value = '';
			document.getElementById('prompt-instructions').value = '';
			writePromptBtn.disabled = false;
			writePromptBtn.querySelector('.spinner-border').classList.add('d-none');
		});
		
		// Listener for all "Fill with AI" buttons
		pagesContainer.addEventListener('click', (e) => {
			if (e.target.matches('.generate-prompt-btn')) {
				const pageCard = e.target.closest('.page-card');
				activeImagePromptTextarea = pageCard.querySelector('.image-prompt-textarea');
				// The button's data-bs-toggle attribute handles showing the modal
			}
		});
		
		// Listener for "Write with AI" button inside the modal
		writePromptBtn.addEventListener('click', async () => {
			if (!activeImagePromptTextarea) {
				alert('Error: No active page selected.');
				return;
			}
			
			const pageCard = activeImagePromptTextarea.closest('.page-card');
			
			const pageText = pageCard.querySelector('textarea[name*="[story_text]"]').value;
			const characterDescriptions = Array.from(pageCard.querySelectorAll('.character-checkbox:checked')).map(cb => cb.dataset.description);
			const placeDescriptions = Array.from(pageCard.querySelectorAll('.place-checkbox:checked')).map(cb => cb.dataset.description);
			const instructions = document.getElementById('prompt-instructions').value;
			const model = document.getElementById('prompt-model').value;
			
			if (!model) {
				alert('Please select an AI model.');
				return;
			}
			
			writePromptBtn.disabled = true;
			writePromptBtn.querySelector('.spinner-border').classList.remove('d-none');
			
			try {
				const response = await fetch('/stories/generate-image-prompt', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json',
					},
					body: JSON.stringify({
						page_text: pageText,
						character_descriptions: characterDescriptions,
						place_descriptions: placeDescriptions,
						instructions: instructions,
						model: model,
					}),
				});
				
				const data = await response.json();
				
				if (response.ok && data.success) {
					generatedPromptText.value = data.prompt;
					promptResultArea.classList.remove('d-none');
					updatePromptBtn.classList.remove('d-none');
				} else {
					alert('An error occurred: ' + (data.message || 'Unknown error'));
				}
				
			} catch (error) {
				console.error('Fetch error:', error);
				alert('A network error occurred. Please try again.');
			} finally {
				writePromptBtn.disabled = false;
				writePromptBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		});
		
		// Listener for "Update Prompt" button inside the modal
		updatePromptBtn.addEventListener('click', () => {
			if (activeImagePromptTextarea) {
				activeImagePromptTextarea.value = generatedPromptText.value;
				generatePromptModal.hide();
			}
		});
	}
	// END MODIFICATION
});
