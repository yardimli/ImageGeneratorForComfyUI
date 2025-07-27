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
		
		// START NEW MODIFICATION: Remember and pre-fill AI prompt generator settings from localStorage.
		const promptModelKey = 'storyEditor_promptModel';
		const promptInstructionsKey = 'storyEditor_promptInstructions';
		
		// Load saved settings when modal is shown
		generatePromptModalEl.addEventListener('shown.bs.modal', () => {
			const savedModel = localStorage.getItem(promptModelKey);
			if (savedModel) {
				document.getElementById('prompt-model').value = savedModel;
			}
			const savedInstructions = localStorage.getItem(promptInstructionsKey);
			if (savedInstructions) {
				document.getElementById('prompt-instructions').value = savedInstructions;
			}
		});
		
		// Save settings on change
		document.getElementById('prompt-model').addEventListener('change', (e) => {
			localStorage.setItem(promptModelKey, e.target.value);
		});
		document.getElementById('prompt-instructions').addEventListener('input', (e) => {
			localStorage.setItem(promptInstructionsKey, e.target.value);
		});
		// END NEW MODIFICATION
		
		// Reset modal on close
		generatePromptModalEl.addEventListener('hidden.bs.modal', () => {
			activeImagePromptTextarea = null;
			promptResultArea.classList.add('d-none');
			updatePromptBtn.classList.add('d-none');
			generatedPromptText.value = '';
			// MODIFICATION: The line that cleared instructions is removed to allow them to persist.
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
	
	// START NEW MODIFICATION: Logic for "Draw with AI" Modal
	const drawWithAiModalEl = document.getElementById('drawWithAiModal');
	if (drawWithAiModalEl) {
		const drawWithAiModal = new bootstrap.Modal(drawWithAiModalEl);
		const generateImageBtn = document.getElementById('generate-image-btn');
		const drawStoryPageIdInput = document.getElementById('draw-story-page-id');
		const drawImagePromptText = document.getElementById('draw-image-prompt-text');
		const drawAspectRatioSelect = document.getElementById('draw-aspect-ratio');
		const drawWidthInput = document.getElementById('draw-width');
		const drawHeightInput = document.getElementById('draw-height');
		
		// START NEW MODIFICATION: Remember and pre-fill AI draw model from localStorage.
		const drawModelKey = 'storyEditor_drawModel';
		
		// Load saved settings when modal is shown
		drawWithAiModalEl.addEventListener('shown.bs.modal', () => {
			const savedModel = localStorage.getItem(drawModelKey);
			if (savedModel) {
				document.getElementById('draw-model').value = savedModel;
			}
		});
		
		// Save settings on change
		document.getElementById('draw-model').addEventListener('change', (e) => {
			localStorage.setItem(drawModelKey, e.target.value);
		});
		// END NEW MODIFICATION
		
		function setDrawDimensions(width, height) {
			drawWidthInput.value = width;
			drawHeightInput.value = height;
		}
		
		if (drawAspectRatioSelect) {
			drawAspectRatioSelect.addEventListener('change', function () {
				const [ratio, baseSize] = this.value.split('-');
				const [w, h] = ratio.split(':');
				
				if (baseSize === '1024') {
					switch (ratio) {
						case '1:1': setDrawDimensions(1024, 1024); break;
						case '3:2': setDrawDimensions(1216, 832); break;
						case '4:3': setDrawDimensions(1152, 896); break;
						case '16:9': setDrawDimensions(1344, 768); break;
						case '21:9': setDrawDimensions(1536, 640); break;
						case '2:3': setDrawDimensions(832, 1216); break;
						case '3:4': setDrawDimensions(896, 1152); break;
						case '9:16': setDrawDimensions(768, 1344); break;
						case '9:21': setDrawDimensions(640, 1536); break;
					}
				} else { // baseSize 1408
					switch (ratio) {
						case '1:1': setDrawDimensions(1408, 1408); break;
						case '3:2': setDrawDimensions(1728, 1152); break;
						case '4:3': setDrawDimensions(1664, 1216); break;
						case '16:9': setDrawDimensions(1920, 1088); break;
						case '21:9': setDrawDimensions(2176, 960); break;
						case '2:3': setDrawDimensions(1152, 1728); break;
						case '3:4': setDrawDimensions(1216, 1664); break;
						case '9:16': setDrawDimensions(1088, 1920); break;
						case '9:21': setDrawDimensions(960, 2176); break;
					}
				}
			});
		}
		
		
		// Listener for all "Draw with AI" buttons
		pagesContainer.addEventListener('click', (e) => {
			const drawButton = e.target.closest('.draw-with-ai-btn');
			if (drawButton) {
				const pageCard = drawButton.closest('.page-card');
				const storyPageId = drawButton.dataset.storyPageId;
				const imagePromptTextarea = pageCard.querySelector('.image-prompt-textarea');
				
				// START MODIFICATION: Check for unsaved changes to the prompt.
				const initialPrompt = imagePromptTextarea.dataset.initialValue || '';
				// decode initialPrompt to handle any HTML entities
				const decodedInitialPrompt = initialPrompt ? decodeURIComponent(initialPrompt) : '';
				if (imagePromptTextarea.value !== decodedInitialPrompt) {
					alert('Your image prompt has unsaved changes. Please save the story before generating an image.');
					e.preventDefault();
					e.stopPropagation();
					return;
				}
				// END MODIFICATION
				
				if (!storyPageId) {
					alert('This page has not been saved yet. Please save the story first.');
					e.preventDefault();
					e.stopPropagation();
					return;
				}
				
				drawStoryPageIdInput.value = storyPageId;
				drawImagePromptText.textContent = imagePromptTextarea.value || '(No prompt has been set for this page yet)';
			}
		});
		
		// Listener for "Generate Image Only" button inside the modal
		generateImageBtn.addEventListener('click', async () => {
			const storyPageId = drawStoryPageIdInput.value;
			const imagePrompt = drawImagePromptText.textContent;
			
			if (!storyPageId) {
				alert('Error: No page selected.');
				return;
			}
			if (!imagePrompt || imagePrompt.startsWith('(No prompt')) {
				alert('Please add an image prompt to the page before generating an image.');
				return;
			}
			
			const model = document.getElementById('draw-model').value;
			const width = drawWidthInput.value;
			const height = drawHeightInput.value;
			const upload_to_s3 = document.getElementById('draw-upload-to-s3').checked;
			const aspect_ratio = drawAspectRatioSelect.value;
			
			generateImageBtn.disabled = true;
			generateImageBtn.querySelector('.spinner-border').classList.remove('d-none');
			
			try {
				const response = await fetch(`/stories/pages/${storyPageId}/generate-image`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json',
					},
					body: JSON.stringify({
						model: model,
						width: width,
						height: height,
						upload_to_s3: upload_to_s3,
						aspect_ratio: aspect_ratio,
					}),
				});
				
				const data = await response.json();
				
				if (response.ok && data.success) {
					alert(data.message); // A simple alert for now.
					drawWithAiModal.hide();
					
					// START MODIFICATION: Show spinner and start polling for the new image.
					const pageCard = document.querySelector(`.draw-with-ai-btn[data-story-page-id="${storyPageId}"]`).closest('.page-card');
					const imageContainer = pageCard.querySelector('.image-upload-container');
					const spinner = imageContainer.querySelector('.spinner-overlay');
					const imagePreview = imageContainer.querySelector('.page-image-preview');
					const imagePathInput = pageCard.querySelector('.image-path-input');
					
					spinner.classList.remove('d-none');
					
					let pollAttempts = 0;
					const maxPollAttempts = 60; // 5 minutes
					
					const pollInterval = setInterval(async () => {
						pollAttempts++;
						if (pollAttempts > maxPollAttempts) {
							clearInterval(pollInterval);
							spinner.classList.add('d-none');
							alert('Image generation is taking longer than expected. The page will be updated when you reload it later.');
							return;
						}
						
						try {
							const statusResponse = await fetch(`/stories/pages/${storyPageId}/image-status`);
							const statusData = await statusResponse.json();
							
							if (statusResponse.ok && statusData.success && statusData.status === 'ready') {
								clearInterval(pollInterval);
								spinner.classList.add('d-none');
								
								// Update image and inputs
								imagePreview.src = statusData.filename;
								imagePathInput.value = statusData.filename;
								
								// Update the initial prompt data attribute to prevent false unsaved changes warnings
								const promptTextarea = pageCard.querySelector('.image-prompt-textarea');
								promptTextarea.dataset.initialValue = promptTextarea.value;
								
								// Make image clickable for modal and add all necessary data
								imagePreview.style.cursor = 'pointer';
								imagePreview.dataset.bsToggle = 'modal';
								imagePreview.dataset.bsTarget = '#imageDetailModal';
								imagePreview.dataset.imageUrl = statusData.filename;
								imagePreview.dataset.promptId = statusData.prompt_id;
								imagePreview.dataset.upscaleStatus = statusData.upscale_status;
								imagePreview.dataset.upscaleUrl = statusData.upscale_url ? `/storage/upscaled/${statusData.upscale_url}` : '';
							}
						} catch (pollError) {
							console.error('Polling error:', pollError);
							clearInterval(pollInterval);
							spinner.classList.add('d-none');
						}
					}, 5000); // Poll every 5 seconds
					// END MODIFICATION
					
				} else {
					alert('An error occurred: ' + (data.message || 'Unknown error'));
				}
				
			} catch (error) {
				console.error('Fetch error:', error);
				alert('A network error occurred. Please try again.');
			} finally {
				generateImageBtn.disabled = false;
				generateImageBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		});
	}
	// END NEW MODIFICATION
	
	// START MODIFICATION: Logic for Image Detail Modal with Upscaling
	const imageDetailModalEl = document.getElementById('imageDetailModal');
	if (imageDetailModalEl) {
		const imageDetailModal = new bootstrap.Modal(imageDetailModalEl);
		const modalImage = document.getElementById('modalDetailImage');
		const upscaleBtnContainer = document.getElementById('upscale-button-container');
		const upscaleStatusContainer = document.getElementById('upscale-status-container');
		
		imageDetailModalEl.addEventListener('show.bs.modal', function (event) {
			const triggerElement = event.relatedTarget; // The image that was clicked
			if (!triggerElement || !triggerElement.dataset.imageUrl) {
				// If triggered by something else, or image has no URL, do nothing.
				event.preventDefault();
				return;
			}
			
			const imageUrl = triggerElement.dataset.imageUrl;
			const promptId = triggerElement.dataset.promptId;
			const upscaleStatus = parseInt(triggerElement.dataset.upscaleStatus, 10);
			const upscaleUrl = triggerElement.dataset.upscaleUrl;
			
			modalImage.src = imageUrl;
			upscaleStatusContainer.innerHTML = ''; // Clear previous status
			
			// Button logic
			if (upscaleStatus === 2 && upscaleUrl) {
				upscaleBtnContainer.innerHTML = `<a href="${upscaleUrl}" target="_blank" class="btn btn-info">View Upscaled</a>`;
			} else if (upscaleStatus === 1) {
				upscaleBtnContainer.innerHTML = `<button class="btn btn-warning" disabled>Upscaling...</button>`;
			} else if (promptId) {
				upscaleBtnContainer.innerHTML = `<button class="btn btn-success upscale-story-image-btn" data-prompt-id="${promptId}" data-filename="${imageUrl}">Upscale Image</button>`;
			} else {
				upscaleBtnContainer.innerHTML = ''; // No prompt, no upscale
			}
		});
		
		// Event delegation for the upscale button inside the modal
		document.body.addEventListener('click', async function (e) {
			if (e.target.classList.contains('upscale-story-image-btn')) {
				const button = e.target;
				const promptId = button.dataset.promptId;
				const filename = button.dataset.filename;
				
				button.disabled = true;
				button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Upscaling...';
				upscaleStatusContainer.innerHTML = 'Sending request...';
				
				try {
					const response = await fetch(`/images/${promptId}/upscale`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json'
						},
						body: JSON.stringify({ filename })
					});
					const data = await response.json();
					
					if (data.prediction_id) {
						upscaleStatusContainer.innerHTML = 'Upscale in progress. You can close this modal; the page will update on reload.';
						
						// Simple polling for the modal
						const checkStatus = async () => {
							const statusResponse = await fetch(`/images/${promptId}/upscale-status/${data.prediction_id}`);
							const statusData = await statusResponse.json();
							
							if (statusData.message === 'Upscale in progress.') {
								upscaleStatusContainer.innerHTML = `Upscale in progress... (${statusData.status || ''})`;
								setTimeout(checkStatus, 5000);
							} else if (statusData.upscale_result) {
								upscaleStatusContainer.innerHTML = `<span class="text-success">Upscale complete! Reload page to see changes.</span>`;
								upscaleBtnContainer.innerHTML = `<a href="${statusData.upscale_result}" target="_blank" class="btn btn-info">View Upscaled</a>`;
							} else {
								upscaleStatusContainer.innerHTML = `<span class="text-danger">Upscale failed: ${statusData.error || 'Unknown error'}</span>`;
								button.disabled = false;
								button.textContent = 'Upscale Image';
							}
						};
						setTimeout(checkStatus, 5000);
					} else {
						throw new Error(data.message || 'Failed to start upscale.');
					}
				} catch (error) {
					console.error('Upscale error:', error);
					upscaleStatusContainer.innerHTML = `<span class="text-danger">Error: ${error.message}</span>`;
					button.disabled = false;
					button.textContent = 'Upscale Image';
				}
			}
		});
	}
	// END MODIFICATION
});
