// Pexels integration
let pexelsImages = [];
let pexelsSelectedImages = [];
let pexelsCurrentPage = 1;
let pexelsTotalPages = 1;
let pexelsTargetSide = '';
let pexelsLastQuery = '';

function openPexelsModal(side) {
	pexelsTargetSide = side;
	pexelsSelectedImages = [];
	updatePexelsSelectedUI();
	const modal = new bootstrap.Modal(document.getElementById('pexelsModal'));
	modal.show();
}

async function searchPexelsImages(page = 1, query = null) {
	if (query) {
		pexelsLastQuery = query;
	} else if (pexelsLastQuery) {
		query = pexelsLastQuery;
	} else {
		return;
	}
	
	pexelsCurrentPage = page;
	
	// Show loading state
	document.getElementById('pexelsImagesContainer').innerHTML = `
        <div class="col-12 text-center py-5">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
	
	try {
		const response = await fetch(`/pexels/search?query=${encodeURIComponent(query)}&page=${page}`);
		const data = await response.json();
		
		if (data.photos && data.photos.length > 0) {
			pexelsImages = data.photos;
			
			// Calculate total pages
			const totalResults = data.total_results || 0;
			const perPage = 20; // Match the per_page in the PHP controller
			pexelsTotalPages = Math.ceil(totalResults / perPage);
			
			renderPexelsImages();
			renderPexelsPagination();
		} else {
			document.getElementById('pexelsImagesContainer').innerHTML = `
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No images found. Try a different search term.</p>
                </div>
            `;
			document.getElementById('pexelsPagination').innerHTML = '';
		}
	} catch (error) {
		console.error('Error searching Pexels:', error);
		document.getElementById('pexelsImagesContainer').innerHTML = `
            <div class="col-12 text-center py-5">
                <div class="alert alert-danger">Error searching images. Please try again.</div>
            </div>
        `;
	}
}

function renderPexelsImages() {
	const container = document.getElementById('pexelsImagesContainer');
	
	if (pexelsImages.length === 0) {
		container.innerHTML = `
            <div class="col-12 text-center py-5">
                <p class="text-muted">No images found. Try a different search term.</p>
            </div>
        `;
		return;
	}
	
	let html = '';
	pexelsImages.forEach((image, index) => {
		const isSelected = pexelsSelectedImages.some(img => img.id === image.id);
		const imageUrl = image.src.large; // Use medium size for the grid view
		
		html += `
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="card pexels-image-card ${isSelected ? 'border-primary' : ''}">
                    <div class="position-relative">
                        <img src="${imageUrl}" class="card-img-top" alt="${image.alt || 'Pexels image'}" style="height: 150px; object-fit: cover;">
                        <div class="form-check position-absolute" style="top: 10px; right: 10px;">
                            <input class="form-check-input" type="checkbox" id="pexelsCheck-${image.id}"
                                   ${isSelected ? 'checked' : ''} data-image-id="${image.id}">
                        </div>
                    </div>
                    <div class="card-footer p-2 text-center">
                        <small class="text-muted">Photo by <a href="${image.photographer_url}" target="_blank" class="text-light">${image.photographer}</a></small>
                    </div>
                </div>
            </div>
        `;
	});
	
	container.innerHTML = html;
	
	// Add event listeners
	pexelsImages.forEach(image => {
		const checkbox = document.getElementById(`pexelsCheck-${image.id}`);
		const card = checkbox.closest('.pexels-image-card');
		
		checkbox.addEventListener('change', function() {
			togglePexelsImageSelection(image);
		});
		
		card.addEventListener('click', function(e) {
			if (e.target !== checkbox) {
				checkbox.checked = !checkbox.checked;
				togglePexelsImageSelection(image);
			}
		});
	});
}

function togglePexelsImageSelection(image) {
	const isAlreadySelected = pexelsSelectedImages.some(img => img.id === image.id);
	const checkbox = document.getElementById(`pexelsCheck-${image.id}`);
	const card = checkbox.closest('.pexels-image-card');
	
	if (isAlreadySelected) {
		// Remove from selection
		pexelsSelectedImages = pexelsSelectedImages.filter(img => img.id !== image.id);
		card.classList.remove('border-primary');
	} else {
		// Add to selection
		pexelsSelectedImages.push(image);
		card.classList.add('border-primary');
	}
	
	updatePexelsSelectedUI();
}

function updatePexelsSelectedUI() {
	const container = document.getElementById('pexelsSelectedImagesContainer');
	const countEl = document.getElementById('pexelsSelectedCount');
	
	countEl.textContent = `${pexelsSelectedImages.length} selected`;
	
	if (pexelsSelectedImages.length === 0) {
		container.innerHTML = `<p class="text-muted text-center py-3">No images selected</p>`;
		return;
	}
	
	let html = '';
	pexelsSelectedImages.forEach(image => {
		html += `
            <div class="selected-pexels-item mb-2">
                <div class="d-flex align-items-center">
                    <img src="${image.src.tiny}" alt="${image.alt || 'Selected image'}"
                         style="width: 50px; height: 50px; object-fit: cover;" class="me-2">
                    <div class="flex-grow-1 small text-truncate">${image.alt || 'Image'}</div>
                    <button type="button" class="btn btn-sm btn-danger ms-1"
                            onclick="removePexelsSelected(${image.id})">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        `;
	});
	
	container.innerHTML = html;
}

function removePexelsSelected(imageId) {
	pexelsSelectedImages = pexelsSelectedImages.filter(img => img.id !== imageId);
	
	// Update checkbox and card if visible
	const checkbox = document.getElementById(`pexelsCheck-${imageId}`);
	if (checkbox) {
		checkbox.checked = false;
		const card = checkbox.closest('.pexels-image-card');
		card.classList.remove('border-primary');
	}
	
	updatePexelsSelectedUI();
}

function renderPexelsPagination() {
	const paginationContainer = document.getElementById('pexelsPagination');
	
	if (pexelsTotalPages <= 1) {
		paginationContainer.innerHTML = '';
		return;
	}
	
	let html = '';
	
	// Previous button
	html += `
        <li class="page-item ${pexelsCurrentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${pexelsCurrentPage - 1}" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `;
	
	// Page numbers
	for (let i = 1; i <= pexelsTotalPages; i++) {
		if (
			i === 1 || // First page
			i === pexelsTotalPages || // Last page
			(i >= pexelsCurrentPage - 2 && i <= pexelsCurrentPage + 2) // Pages around current
		) {
			html += `
                <li class="page-item ${i === pexelsCurrentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
		} else if (
			(i === pexelsCurrentPage - 3 && pexelsCurrentPage > 3) ||
			(i === pexelsCurrentPage + 3 && pexelsCurrentPage < pexelsTotalPages - 2)
		) {
			html += `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`;
		}
	}
	
	// Next button
	html += `
        <li class="page-item ${pexelsCurrentPage === pexelsTotalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${pexelsCurrentPage + 1}" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;
	
	paginationContainer.innerHTML = html;
	
	// Add event listeners to pagination links
	document.querySelectorAll('#pexelsPagination .page-link').forEach(link => {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			const page = parseInt(this.getAttribute('data-page'));
			if (!isNaN(page) && page > 0 && page <= pexelsTotalPages) {
				searchPexelsImages(page);
			}
		});
	});
}

async function downloadPexelsImages() {
	if (pexelsSelectedImages.length === 0) {
		alert('Please select at least one image.');
		return;
	}
	
	// Show loading state
	document.getElementById('pexelsAddSelectedBtn').innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        Downloading...
    `;
	document.getElementById('pexelsAddSelectedBtn').disabled = true;
	
	try {
		for (const image of pexelsSelectedImages) {
			const downloadUrl = image.src.large2x; // Use large size (1000px)
			
			// Download the image
			const response = await fetch('/pexels/download', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({ url: downloadUrl })
			});
			
			const data = await response.json();
			
			if (data.success) {
				// Add image to left or right side
				if (pexelsTargetSide === 'left') {
					// Generate a descriptive prompt based on the image alt text
					const prompt = image.alt || 'Image from Pexels';
					addLeftImage(data.path, 3, prompt);
				} else if (pexelsTargetSide === 'right') {
					addRightImage(data.path, 3);
				}
			} else {
				console.error('Failed to download image:', data.error);
			}
		}
		
		// Close the modal
		bootstrap.Modal.getInstance(document.getElementById('pexelsModal')).hide();
	} catch (error) {
		console.error('Error downloading images:', error);
		alert('Error downloading images. Please try again.');
	} finally {
		// Reset button state
		document.getElementById('pexelsAddSelectedBtn').innerHTML = 'Add Selected Images';
		document.getElementById('pexelsAddSelectedBtn').disabled = false;
	}
}

document.addEventListener('DOMContentLoaded', function() {
	// Add event listeners for Pexels buttons
	document.getElementById('leftPexelsBtn').addEventListener('click', function() {
		openPexelsModal('left');
	});
	
	document.getElementById('rightPexelsBtn').addEventListener('click', function() {
		openPexelsModal('right');
	});
	
	// Pexels search button
	document.getElementById('pexelsSearchBtn').addEventListener('click', function() {
		const query = document.getElementById('pexelsSearchInput').value.trim();
		if (query) {
			searchPexelsImages(1, query);
		}
	});
	
	// Enable search on Enter key
	document.getElementById('pexelsSearchInput').addEventListener('keyup', function(e) {
		if (e.key === 'Enter') {
			const query = this.value.trim();
			if (query) {
				searchPexelsImages(1, query);
			}
		}
	});
	
	// Add selected images button
	document.getElementById('pexelsAddSelectedBtn').addEventListener('click', function() {
		downloadPexelsImages();
	});
});
