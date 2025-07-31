document.addEventListener('DOMContentLoaded', function () {
	const imageContainer = document.getElementById('imageContainer');
	const imageUrlInput = document.getElementById('imageUrlInput');
	const kontextLoraForm = document.getElementById('kontextLoraForm');
	
	// START MODIFICATION: Only the prompt queued modal is needed now.
	const promptQueuedModal = new bootstrap.Modal(document.getElementById('promptQueuedModal'));
	// END MODIFICATION
	
	// START MODIFICATION: Add logic for Lora dropdown info panel.
	const loraSelect = document.getElementById('loraNameSelect');
	const loraInfo = document.getElementById('loraInfo');
	
	function updateLoraInfo() {
		if (!loraSelect || !loraInfo) return;
		
		const selectedOption = loraSelect.options[loraSelect.selectedIndex];
		if (selectedOption && selectedOption.value) {
			const trigger = selectedOption.dataset.trigger;
			const notes = selectedOption.dataset.notes;
			const promptTextarea = document.getElementById('promptTextarea');
			const currentPrompt = promptTextarea.value.trim();
			
			let infoHtml = `<strong>Trigger:</strong> ${trigger}`;
			if (notes) {
				infoHtml += ` <strong>Notes:</strong> ${notes}`;
			}
			loraInfo.innerHTML = infoHtml;
			//prepend the trigger to the prompt textarea
			if (!currentPrompt.startsWith(trigger)) {
				promptTextarea.value = trigger + (currentPrompt ? ' ' + currentPrompt : '');
			}
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
	
	// START MODIFICATION: Handle returning from image editor.
	// When the user saves in the Image Editor, they are redirected back here
	// with the URL of the newly created image.
	const urlParams = new URLSearchParams(window.location.search);
	const editedImageUrl = urlParams.get('edited_image_url');
	if (editedImageUrl) {
		// Directly select the image, bypassing the crop dialog.
		selectImage(decodeURIComponent(editedImageUrl));
		
		// Clean up the URL to avoid re-triggering on refresh.
		const newUrl = window.location.pathname;
		window.history.replaceState({}, document.title, newUrl);
	}
	// END MODIFICATION
	
	// All logic for upload, history, and cropping modals has been removed as it's no longer used.
	
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
