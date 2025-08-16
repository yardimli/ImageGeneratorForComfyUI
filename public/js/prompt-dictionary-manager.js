document.addEventListener('DOMContentLoaded', function () {
	const container = document.getElementById('dictionary-entries-container');
	if (!container) return;
	
	const config = {
		containerId: 'dictionary-entries-container',
		addBtnId: 'add-entry-btn',
		templateId: 'entry-template',
		cardSelector: '.entry-card',
		removeBtnSelector: '.remove-entry-btn',
		namePrefix: 'entries',
		assetType: 'entry'
	};
	
	const addBtn = document.getElementById(config.addBtnId);
	const template = document.getElementById(config.templateId);
	
	// --- Image Handling Logic ---
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
		const historyContainer = document.getElementById('historyImagesContainer');
		historyContainer.innerHTML = '<div class="d-flex justify-content-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		const endpoint = source === 'uploads' ? `/image-mix/uploads?page=${page}&sort=${sort}&perPage=${perPage}` : `/kontext-basic/render-history?page=${page}&sort=${sort}&perPage=${perPage}`;
		const response = await fetch(endpoint);
		const data = await response.json();
		historyContainer.innerHTML = '';
		const images = source === 'uploads' ? data.images : data.data;
		images.forEach(img => {
			const imageUrl = source === 'uploads' ? img.path : img.image_url;
			const thumbUrl = source === 'uploads' ? img.path : img.thumbnail_url;
			const name = source === 'uploads' ? img.name : img.generated_prompt;
			historyContainer.innerHTML += `
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
	
	// --- Main Logic ---
	function reindexAssetNames() {
		const cards = container.querySelectorAll(config.cardSelector);
		cards.forEach((card, index) => {
			card.querySelectorAll('[name]').forEach(input => {
				const name = input.getAttribute('name');
				if (name) {
					input.setAttribute('name', name.replace(/\[\d+\]|\[__INDEX__\]/, `[${index}]`));
				}
			});
		});
	}
	
	addBtn.addEventListener('click', () => {
		const newAssetHtml = template.innerHTML.replace(/__INDEX__/g, container.children.length);
		container.insertAdjacentHTML('beforeend', newAssetHtml);
		reindexAssetNames();
	});
	
	container.addEventListener('click', (e) => {
		if (e.target.matches(config.removeBtnSelector)) {
			if (confirm('Are you sure you want to remove this item? This cannot be undone.')) {
				e.target.closest(config.cardSelector).remove();
				reindexAssetNames();
			}
		}
		if (e.target.matches('.select-image-btn')) {
			activeImageUploadContainer = e.target.closest('.col-md-4').querySelector('.image-upload-container');
			loadHistory(1);
			historyModal.show();
		}
	});
	
	// --- AI Asset Description Rewrite Modal ---
	const rewriteModalEl = document.getElementById('rewriteAssetDescriptionModal');
	if (rewriteModalEl) {
		const rewriteModal = new bootstrap.Modal(rewriteModalEl);
		const rewriteStyleSelect = document.getElementById('rewrite-asset-style');
		const rewriteFullPromptTextarea = document.getElementById('rewrite-asset-full-prompt');
		const rewriteModelSelect = document.getElementById('rewrite-asset-model');
		const rewriteBtn = document.getElementById('rewrite-asset-btn');
		const rewriteResultArea = document.getElementById('rewrite-asset-result-area');
		const rewrittenTextTextarea = document.getElementById('rewritten-asset-text');
		const replaceTextBtn = document.getElementById('replace-asset-text-btn');
		let activeDescriptionTextarea = null;
		let originalDescription = '';
		const rewriteModelKey = 'storyCreateAi_model';
		
		const styleOptions = {
			entry: {
				'detailed': "Rewrite the following description to be more visually detailed. Focus on specific features and its overall presence. Also remove any instructions or notes from the original text.",
				'simplify': "Simplify the following description. Use clearer, more concise language suitable for a younger audience or for a quick introduction. Also remove any instructions or notes from the original text.",
				'poetic': "Rewrite the description in a more poetic and evocative style. Use figurative language and sensory details to create a stronger mood. Also remove any instructions or notes from the original text.",
				'technical': "Rewrite the description in a more technical style, focusing on precise terminology and objective details.",
				'grammar': 'Correct any grammatical errors, improve the sentence structure, and enhance the clarity of the following text. Act as a professional editor. Also remove any instructions or notes from the original text.'
			}
		};
		
		function buildAssetRewritePrompt(text, styleInstruction) {
			const jsonStructure = `{\n  "rewritten_text": "The rewritten text goes here."\n}`;
			return `You are an expert story editor. Your task is to rewrite a description for a story asset based on a specific instruction.\nInstruction: "${styleInstruction}"\n\nOriginal Text:\n"${text}"\n\nPlease provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.\nThe JSON object must follow this exact structure:\n${jsonStructure}\n\nNow, rewrite the text based on the instruction.`;
		}
		
		function updateRewritePromptPreview() {
			if (!originalDescription) return;
			const selectedStyleKey = rewriteStyleSelect.value;
			const instruction = styleOptions[config.assetType][selectedStyleKey];
			const fullPrompt = buildAssetRewritePrompt(originalDescription, instruction);
			rewriteFullPromptTextarea.value = fullPrompt;
		}
		
		container.addEventListener('click', (e) => {
			const rewriteButton = e.target.closest('.rewrite-asset-description-btn');
			if (rewriteButton) {
				const card = rewriteButton.closest(config.cardSelector);
				activeDescriptionTextarea = card.querySelector('.asset-description');
				originalDescription = activeDescriptionTextarea.value;
			}
		});
		
		rewriteModalEl.addEventListener('shown.bs.modal', () => {
			if (!activeDescriptionTextarea) {
				alert('Could not find the description text. Please close this and try again.');
				rewriteModal.hide();
				return;
			}
			rewriteStyleSelect.innerHTML = '';
			const currentStyles = styleOptions[config.assetType];
			for (const key in currentStyles) {
				const option = document.createElement('option');
				option.value = key;
				option.textContent = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
				rewriteStyleSelect.appendChild(option);
			}
			const savedModel = localStorage.getItem(rewriteModelKey);
			if (savedModel) rewriteModelSelect.value = savedModel;
			updateRewritePromptPreview();
		});
		
		rewriteModalEl.addEventListener('hidden.bs.modal', () => {
			activeDescriptionTextarea = null;
			originalDescription = '';
			rewriteResultArea.classList.add('d-none');
			replaceTextBtn.classList.add('d-none');
			rewrittenTextTextarea.value = '';
			rewriteFullPromptTextarea.value = '';
			rewriteBtn.disabled = false;
			rewriteBtn.querySelector('.spinner-border').classList.add('d-none');
		});
		
		rewriteStyleSelect.addEventListener('change', updateRewritePromptPreview);
		rewriteModelSelect.addEventListener('change', (e) => localStorage.setItem(rewriteModelKey, e.target.value));
		
		rewriteBtn.addEventListener('click', async () => {
			const prompt = rewriteFullPromptTextarea.value;
			const model = rewriteModelSelect.value;
			if (!prompt || !model) {
				alert('Prompt and model are required.');
				return;
			}
			rewriteBtn.disabled = true;
			rewriteBtn.querySelector('.spinner-border').classList.remove('d-none');
			try {
				const response = await fetch('/prompt-dictionary/rewrite-description', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
					body: JSON.stringify({ prompt, model }),
				});
				const data = await response.json();
				if (response.ok && data.success) {
					rewrittenTextTextarea.value = data.rewritten_text;
					rewriteResultArea.classList.remove('d-none');
					replaceTextBtn.classList.remove('d-none');
				} else {
					alert('An error occurred: ' + (data.message || 'Unknown error'));
				}
			} catch (error) {
				console.error('Rewrite error:', error);
				alert('A network error occurred.');
			} finally {
				rewriteBtn.disabled = false;
				rewriteBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		});
		
		replaceTextBtn.addEventListener('click', () => {
			if (activeDescriptionTextarea) {
				activeDescriptionTextarea.value = rewrittenTextTextarea.value;
				rewriteModal.hide();
			}
		});
	}
	
	// --- AI Image Prompt and Image Generation ---
	function buildAssetImageGenerationPrompt(assetDescription, assetType, userInstructions) {
		const instructionsText = userInstructions ? `User's specific instructions: "${userInstructions}"` : "No specific instructions from the user.";
		let assetInstructions = "Output should be a high-quality image that captures the essence of the asset.";
		if (assetType === "entry") {
			assetInstructions = "Output should be a high-quality image that captures the essence of the dictionary entry.";
		}
		const jsonStructure = `{\n  "prompt": "A detailed, comma-separated list of visual descriptors for the image."\n}`;
		return `You are an expert at writing image generation prompts for AI art models like DALL-E 3 or Midjourney.\nYour task is to create a single, concise, and descriptive image prompt for a dictionary ${assetType}.\n\n**Context:**\n1.  **${assetType} Description:**\n    "${assetDescription}"\n\n2.  **User Guidance:**\n    ${instructionsText}\n\n**Instructions:**\n- ${assetInstructions}\n- The prompt should be a single paragraph of comma-separated descriptive phrases.\n- Focus on visual details: appearance, key features, mood, and lighting.\n- Provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.\n- The JSON object must follow this exact structure:\n${jsonStructure}\n\nNow, generate the image prompt for the provided context in the specified JSON format.`;
	}
	
	const generatePromptModalEl = document.getElementById('generatePromptModal');
	if (generatePromptModalEl) {
		const generatePromptModal = new bootstrap.Modal(generatePromptModalEl);
		const writePromptBtn = document.getElementById('write-prompt-btn');
		const updatePromptBtn = document.getElementById('update-prompt-btn');
		const promptResultArea = document.getElementById('prompt-result-area');
		const generatedPromptText = document.getElementById('generated-prompt-text');
		const fullPromptTextarea = document.getElementById('full-prompt-text');
		let activeImagePromptTextarea = null;
		const promptModelKey = 'storyCreateAi_model';
		const promptInstructionsKey = 'storyEditor_promptInstructions';
		
		function updateFullPromptPreview() {
			if (!activeImagePromptTextarea || !fullPromptTextarea) return;
			const card = activeImagePromptTextarea.closest(config.cardSelector);
			const description = card.querySelector('.asset-description').value;
			const instructions = document.getElementById('prompt-instructions').value;
			const fullPrompt = buildAssetImageGenerationPrompt(description, config.assetType, instructions);
			fullPromptTextarea.value = fullPrompt;
		}
		
		generatePromptModalEl.addEventListener('shown.bs.modal', () => {
			const savedModel = localStorage.getItem(promptModelKey);
			if (savedModel) document.getElementById('prompt-model').value = savedModel;
			const savedInstructions = localStorage.getItem(promptInstructionsKey);
			if (savedInstructions) document.getElementById('prompt-instructions').value = savedInstructions;
			updateFullPromptPreview();
		});
		
		document.getElementById('prompt-instructions').addEventListener('input', updateFullPromptPreview);
		container.addEventListener('input', (e) => {
			if (e.target.matches('.asset-description') && generatePromptModalEl.classList.contains('show')) {
				if (activeImagePromptTextarea && e.target.closest(config.cardSelector) === activeImagePromptTextarea.closest(config.cardSelector)) {
					updateFullPromptPreview();
				}
			}
		});
		
		document.getElementById('prompt-model').addEventListener('change', (e) => localStorage.setItem(promptModelKey, e.target.value));
		
		generatePromptModalEl.addEventListener('hidden.bs.modal', () => {
			activeImagePromptTextarea = null;
			promptResultArea.classList.add('d-none');
			updatePromptBtn.classList.add('d-none');
			generatedPromptText.value = '';
			if (fullPromptTextarea) fullPromptTextarea.value = '';
			writePromptBtn.disabled = false;
			writePromptBtn.querySelector('.spinner-border').classList.add('d-none');
		});
		
		container.addEventListener('click', (e) => {
			if (e.target.matches('.generate-prompt-btn')) {
				const card = e.target.closest(config.cardSelector);
				activeImagePromptTextarea = card.querySelector('.image-prompt-textarea');
			}
		});
		
		writePromptBtn.addEventListener('click', async () => {
			if (!activeImagePromptTextarea) return;
			const prompt = fullPromptTextarea.value;
			const model = document.getElementById('prompt-model').value;
			if (!model) {
				alert('Please select an AI model.');
				return;
			}
			writePromptBtn.disabled = true;
			writePromptBtn.querySelector('.spinner-border').classList.remove('d-none');
			const endpoint = `/prompt-dictionary/generate-image-prompt`;
			try {
				const response = await fetch(endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
					body: JSON.stringify({ prompt, model }),
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
				alert('A network error occurred.');
			} finally {
				writePromptBtn.disabled = false;
				writePromptBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		});
		
		updatePromptBtn.addEventListener('click', () => {
			if (activeImagePromptTextarea) {
				activeImagePromptTextarea.value = generatedPromptText.value;
				generatePromptModal.hide();
			}
		});
	}
	
	// --- "Draw with AI" Modal Logic ---
	const drawWithAiModalEl = document.getElementById('drawWithAiModal');
	if (drawWithAiModalEl) {
		const drawWithAiModal = new bootstrap.Modal(drawWithAiModalEl);
		const generateImageBtn = document.getElementById('generate-image-btn');
		const drawAssetIdInput = document.getElementById('draw-asset-id');
		const drawImagePromptText = document.getElementById('draw-image-prompt-text');
		const drawAspectRatioSelect = document.getElementById('draw-aspect-ratio');
		const drawWidthInput = document.getElementById('draw-width');
		const drawHeightInput = document.getElementById('draw-height');
		const drawModelKey = 'promptDict_drawModel';
		
		drawWithAiModalEl.addEventListener('shown.bs.modal', () => {
			const savedModel = localStorage.getItem(drawModelKey);
			if (savedModel) document.getElementById('draw-model').value = savedModel;
		});
		document.getElementById('draw-model').addEventListener('change', (e) => localStorage.setItem(drawModelKey, e.target.value));
		
		function setDrawDimensions(width, height) {
			drawWidthInput.value = width;
			drawHeightInput.value = height;
		}
		
		if (drawAspectRatioSelect) {
			drawAspectRatioSelect.addEventListener('change', function () {
				const [ratio, baseSize] = this.value.split('-');
				const sizes = {
					'1024': { '1:1': [1024, 1024], '3:2': [1216, 832], '4:3': [1152, 896], '16:9': [1344, 768], '21:9': [1536, 640], '2:3': [832, 1216], '3:4': [896, 1152], '9:16': [768, 1344], '9:21': [640, 1536] },
					'1408': { '1:1': [1408, 1408], '3:2': [1728, 1152], '4:3': [1664, 1216], '16:9': [1920, 1088], '21:9': [2176, 960], '2:3': [1152, 1728], '3:4': [1216, 1664], '9:16': [1088, 1920], '9:21': [960, 2176] }
				};
				const [width, height] = sizes[baseSize][ratio];
				setDrawDimensions(width, height);
			});
		}
		
		container.addEventListener('click', (e) => {
			const drawButton = e.target.closest('.draw-with-ai-btn');
			if (drawButton) {
				const card = drawButton.closest(config.cardSelector);
				const assetId = drawButton.dataset.assetId;
				const imagePromptTextarea = card.querySelector('.image-prompt-textarea');
				if (imagePromptTextarea.value !== (imagePromptTextarea.dataset.initialValue || '')) {
					alert('Your image prompt has unsaved changes. Please save all changes before generating an image.');
					e.preventDefault(); e.stopPropagation(); return;
				}
				if (!assetId) {
					alert('This item has not been saved yet. Please save all changes first.');
					e.preventDefault(); e.stopPropagation(); return;
				}
				drawAssetIdInput.value = assetId;
				drawImagePromptText.textContent = imagePromptTextarea.value || '(No prompt has been set for this item yet)';
				drawWithAiModal.show();
			}
		});
		
		generateImageBtn.addEventListener('click', async () => {
			const assetId = drawAssetIdInput.value;
			if (!assetId) return;
			generateImageBtn.disabled = true;
			generateImageBtn.querySelector('.spinner-border').classList.remove('d-none');
			const body = {
				model: document.getElementById('draw-model').value,
				width: drawWidthInput.value,
				height: drawHeightInput.value,
				upload_to_s3: document.getElementById('draw-upload-to-s3').checked,
				aspect_ratio: drawAspectRatioSelect.value,
			};
			const endpoint = `/prompt-dictionary/${assetId}/generate-image`;
			try {
				const response = await fetch(endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
					body: JSON.stringify(body),
				});
				const data = await response.json();
				if (response.ok && data.success) {
					alert(data.message);
					drawWithAiModal.hide();
					startPolling(assetId);
				} else {
					alert('An error occurred: ' + (data.message || 'Unknown error'));
				}
			} catch (error) {
				alert('A network error occurred.');
			} finally {
				generateImageBtn.disabled = false;
				generateImageBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		});
		
		function startPolling(assetId) {
			const card = document.querySelector(`.draw-with-ai-btn[data-asset-id="${assetId}"]`).closest(config.cardSelector);
			const imageContainer = card.querySelector('.image-upload-container');
			const spinner = imageContainer.querySelector('.spinner-overlay');
			const imagePreview = imageContainer.querySelector('.asset-image-preview');
			const imagePathInput = card.querySelector('.image-path-input');
			spinner.classList.remove('d-none');
			let pollAttempts = 0;
			const pollInterval = setInterval(async () => {
				if (++pollAttempts > 60) {
					clearInterval(pollInterval);
					spinner.classList.add('d-none');
					alert('Image generation is taking longer than expected.');
					return;
				}
				const statusEndpoint = `/prompt-dictionary/${assetId}/image-status`;
				try {
					const statusResponse = await fetch(statusEndpoint);
					const statusData = await statusResponse.json();
					if (statusResponse.ok && statusData.success && statusData.status === 'ready') {
						clearInterval(pollInterval);
						spinner.classList.add('d-none');
						imagePreview.src = statusData.filename;
						imagePathInput.value = statusData.filename;
						const promptTextarea = card.querySelector('.image-prompt-textarea');
						promptTextarea.dataset.initialValue = promptTextarea.value;
						imagePreview.style.cursor = 'pointer';
						imagePreview.dataset.bsToggle = 'modal';
						imagePreview.dataset.bsTarget = '#imageDetailModal';
						imagePreview.dataset.imageUrl = statusData.filename;
						imagePreview.dataset.promptId = statusData.prompt_id;
						imagePreview.dataset.upscaleStatus = statusData.upscale_status;
						imagePreview.dataset.upscaleUrl = statusData.upscale_url ? `/storage/upscaled/${statusData.upscale_url}` : '';
					}
				} catch (pollError) {
					clearInterval(pollInterval);
					spinner.classList.add('d-none');
				}
			}, 5000);
		}
	}
	
	// --- Image Detail Modal (Upscaling) Logic ---
	const imageDetailModalEl = document.getElementById('imageDetailModal');
	if (imageDetailModalEl) {
		const modalImage = document.getElementById('modalDetailImage');
		const upscaleBtnContainer = document.getElementById('upscale-button-container');
		const upscaleStatusContainer = document.getElementById('upscale-status-container');
		let activeImageTrigger = null;
		
		imageDetailModalEl.addEventListener('show.bs.modal', function (event) {
			const trigger = event.relatedTarget;
			if (!trigger || !trigger.dataset.imageUrl) {
				event.preventDefault();
				return;
			}
			activeImageTrigger = trigger;
			modalImage.src = trigger.dataset.imageUrl;
			upscaleStatusContainer.innerHTML = '';
			const upscaleStatus = parseInt(trigger.dataset.upscaleStatus, 10);
			if (upscaleStatus === 2 && trigger.dataset.upscaleUrl) {
				upscaleBtnContainer.innerHTML = `<a href="${trigger.dataset.upscaleUrl}" target="_blank" class="btn btn-info">View Upscaled</a>`;
			} else if (upscaleStatus === 1) {
				upscaleBtnContainer.innerHTML = `<button class="btn btn-warning" disabled>Upscaling...</button>`;
			} else if (trigger.dataset.promptId) {
				upscaleBtnContainer.innerHTML = `<button class="btn btn-success upscale-story-image-btn" data-prompt-id="${trigger.dataset.promptId}" data-filename="${trigger.dataset.imageUrl}">Upscale Image</button>`;
			} else {
				upscaleBtnContainer.innerHTML = '';
			}
		});
		
		document.body.addEventListener('click', async function (e) {
			if (e.target.classList.contains('upscale-story-image-btn')) {
				const button = e.target;
				button.disabled = true;
				button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Upscaling...';
				upscaleStatusContainer.innerHTML = 'Sending request...';
				try {
					const response = await fetch(`/images/${button.dataset.promptId}/upscale`, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
						body: JSON.stringify({ filename: button.dataset.filename })
					});
					const data = await response.json();
					if (data.prediction_id) {
						upscaleStatusContainer.innerHTML = 'Upscale in progress...';
						if (activeImageTrigger) {
							const imageContainer = activeImageTrigger.closest('.image-upload-container');
							if (imageContainer) {
								const label = imageContainer.previousElementSibling;
								if (label && label.tagName === 'LABEL') {
									label.querySelectorAll('.badge').forEach(b => b.remove());
									const newBadge = document.createElement('span');
									newBadge.className = 'badge bg-warning ms-2';
									newBadge.title = 'Image is being upscaled';
									newBadge.textContent = 'Upscaling...';
									label.appendChild(newBadge);
								}
							}
						}
					} else {
						throw new Error(data.message || 'Failed to start upscale.');
					}
				} catch (error) {
					upscaleStatusContainer.innerHTML = `<span class="text-danger">Error: ${error.message}</span>`;
					button.disabled = false;
					button.textContent = 'Upscale Image';
				}
			}
		});
	}
});
