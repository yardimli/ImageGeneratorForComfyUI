document.addEventListener('DOMContentLoaded', function () {
	const isCharactersPage = !!document.getElementById('characters-container');
	const isPlacesPage = !!document.getElementById('places-container');
	
	if (!isCharactersPage && !isPlacesPage) return;
	
	const config = isCharactersPage ? {
		containerId: 'characters-container',
		addBtnId: 'add-character-btn',
		templateId: 'character-template',
		cardSelector: '.character-card',
		removeBtnSelector: '.remove-character-btn',
		namePrefix: 'characters'
	} : {
		containerId: 'places-container',
		addBtnId: 'add-place-btn',
		templateId: 'place-template',
		cardSelector: '.place-card',
		removeBtnSelector: '.remove-place-btn',
		namePrefix: 'places'
	};
	
	const container = document.getElementById(config.containerId);
	const addBtn = document.getElementById(config.addBtnId);
	const template = document.getElementById(config.templateId);
	
	// --- Image Handling Logic (shared with story-editor.js) ---
	const cropperModalEl = document.getElementById('cropperModal');
	const cropperModal = new bootstrap.Modal(cropperModalEl);
	const imageToCrop = document.getElementById('imageToCrop');
	const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
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
	
	document.getElementById('historyModal').addEventListener('click', e => {
		if (e.target.closest('.page-link')) {
			e.preventDefault();
			loadHistory(parseInt(e.target.dataset.page));
		} else if (e.target.closest('.history-image-card')) {
			document.querySelectorAll('.history-image-card.selected').forEach(c => c.classList.remove('selected'));
			e.target.closest('.history-image-card').classList.add('selected');
		}
	});
	
	document.getElementById('addSelectedHistoryImageBtn').addEventListener('click', () => {
		const selected = document.querySelector('#historyModal .history-image-card.selected');
		if (selected) {
			openCropper(selected.dataset.path);
			historyModal.hide();
		} else {
			alert('Please select an image.');
		}
	});
	
	['historySource', 'historySort', 'historyPerPage'].forEach(id => document.getElementById(id).addEventListener('change', () => loadHistory(1)));
	
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
		const newAsset = template.content.cloneNode(true);
		container.appendChild(newAsset);
		reindexAssetNames();
	});
	
	container.addEventListener('click', (e) => {
		// Handle remove button
		if (e.target.matches(config.removeBtnSelector)) {
			if (confirm('Are you sure you want to remove this item? This cannot be undone.')) {
				e.target.closest(config.cardSelector).remove();
				reindexAssetNames();
			}
		}
		
		// Handle image selection
		if (e.target.matches('.select-image-btn')) {
			activeImageUploadContainer = e.target.closest('.col-md-4').querySelector('.image-upload-container');
			loadHistory(1);
			historyModal.show();
		}
	});
});
