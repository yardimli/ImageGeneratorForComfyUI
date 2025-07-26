let imageModal;
let reloadInterval;
const MAX_RELOAD_ATTEMPTS = 30; // Maximum number of reload attempts (5 minutes at 10-second intervals)
let reloadAttempts = 0;

function openImageModal(filename) {
	const modalImage = document.getElementById('modalImage');
	modalImage.src = `${filename}`;
	imageModal.show();
}

function allPromptsHaveImages(prompts) {
	return prompts.every(prompt => prompt.filename);
}

async function reloadPrompts(settingId) {
	if (reloadAttempts >= MAX_RELOAD_ATTEMPTS) {
		clearInterval(reloadInterval);
		console.log('Max reload attempts reached');
		return;
	}
	
	try {
		const response = await fetch(`/prompts/settings/${settingId}`);
		const data = await response.json();
		
		displayPrompts(data.prompts, settingId);
		
		if (allPromptsHaveImages(data.prompts)) {
			clearInterval(reloadInterval);
		} else {
			reloadAttempts++;
		}
	} catch (error) {
		console.error('Error reloading prompts:', error);
		clearInterval(reloadInterval);
	}
}

function displayPrompts(prompts, settingId) {
	let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Previously Generated Prompts:</h4>
            <button class="btn btn-danger delete-all-btn" data-setting-id="${settingId}">
                Delete Settings & All Images
            </button>
        </div>
    `;
	
	html += `<div class="row">`;
	prompts.forEach(prompt => {
		html += `
            <div class="result-item col-3">
                <div>${prompt.generated_prompt}</div>
                ${prompt.filename ? `
                    <div class="mt-2">
                        <img src="${prompt.thumbnail}" style="max-width: 300px; width: 100%; height: auto; cursor: pointer;" alt="Generated Image" onclick="openImageModal('${prompt.filename}')" class="generated-image">
                        <div class="mt-2">
                            <input type="text" class="form-control notes-input mb-2" value="${prompt.notes || ''}" placeholder="Add notes..." data-prompt-id="${prompt.id}">
                            <div class="d-flex">
                                <button class="btn btn-primary btn-sm update-notes-btn me-1 mb-2" data-prompt-id="${prompt.id}">
                                    Update Notes
                                </button>
                                <button class="btn btn-danger btn-sm delete-image-btn mb-2" data-prompt-id="${prompt.id}">
                                    Delete
                                </button>
                            </div>
                            ${getUpscaleButton(prompt)}
                            <div id="upscale-status-${prompt.id}" class="mt-2"></div>
                        </div>
                    </div>
                ` : `
                    <div class="mt-2 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Generating image...</div>
                    </div>
                `}
            </div>
        `;
	});
	html += `</div>`;
	
	document.getElementById('resultContainer').innerHTML = html;
	document.getElementById('resultContainer').classList.remove('d-none');
	
	attachButtonEventListeners(settingId);
	
}

function attachButtonEventListeners(settingId) {
	
	// Add event listeners for buttons
	document.querySelectorAll('.update-notes-btn').forEach(button => {
		button.addEventListener('click', async function () {
			const promptId = this.dataset.promptId;
			const notesInput = document.querySelector(`.notes-input[data-prompt-id="${promptId}"]`);
			const notes = notesInput.value;
			
			try {
				const response = await fetch(`/images/${promptId}/update-notes`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
					},
					body: JSON.stringify({notes})
				});
				
				const data = await response.json();
				if (data.message) {
					alert('Notes updated successfully');
				}
			} catch (error) {
				console.error('Error updating notes:', error);
				alert('Error updating notes');
			}
		});
	});
	
	document.querySelectorAll('.upscale-btn').forEach(button => {
		button.addEventListener('click', async function () {
			const promptId = this.dataset.promptId;
			const filename = this.dataset.filename;
			const statusDiv = document.getElementById(`upscale-status-${promptId}`);
			
			// Remove the upscale button
			this.remove();
			statusDiv.innerHTML = 'Upscaling in progress...';
			
			try {
				const response = await fetch(`/images/${promptId}/upscale`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
					},
					body: JSON.stringify({filename})
				});
				const data = await response.json();
				
				if (data.prediction_id) {
					// Start checking status
					const checkStatus = async () => {
						const statusResponse = await fetch(`/images/${promptId}/upscale-status/${data.prediction_id}`);
						const statusData = await statusResponse.json();
						
						if (statusData.message === 'Upscale in progress.') {
							statusDiv.innerHTML = 'Still processing...';
							setTimeout(checkStatus, 5000); // Check again in 5 seconds
						} else if (statusData.upscale_result) {
							statusDiv.innerHTML = `
                            <a href="${statusData.upscale_result}"
                               class="btn btn-info btn-sm"
                               target="_blank">
                                View Upscaled Image
                            </a>`;
						} else {
							statusDiv.innerHTML = 'Upscale failed: ' + (statusData.error || 'Unknown error');
						}
					};
					
					setTimeout(checkStatus, 5000); // Start checking after 5 seconds
				}
			} catch (error) {
				console.error('Error starting upscale:', error);
				statusDiv.innerHTML = 'Error starting upscale process';
			}
		});
	});
	
	document.querySelectorAll('.delete-image-btn').forEach(button => {
		button.addEventListener('click', async function () {
			if (!confirm('Are you sure you want to delete this image?')) return;
			
			const promptId = this.dataset.promptId;
			try {
				const response = await fetch(`/prompts/${promptId}`, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
					}
				});
				const data = await response.json();
				if (data.success) {
					// Remove the image container from the UI
					this.closest('.result-item').remove();
					alert('Image deleted successfully');
				} else {
					alert('Error: ' + (data.message || 'Failed to delete image'));
				}
			} catch (error) {
				console.error('Error deleting image:', error);
				alert('Error deleting image');
			}
		});
	});
	
	const deleteAllBtn = document.querySelector('.delete-all-btn');
	if (deleteAllBtn) {
		deleteAllBtn.addEventListener('click', async function () {
			if (!confirm('Are you sure you want to delete these settings and ALL associated images? This cannot be undone!')) return;
			
			const settingId = this.dataset.settingId;
			try {
				const response = await fetch(`/prompt-settings/${settingId}`, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
					}
				});
				const data = await response.json();
				if (data.success) {
					// Remove the setting from the dropdown and clear the result container
					const option = document.querySelector(`#savedSettings option[value="${settingId}"]`);
					if (option) option.remove();
					document.getElementById('resultContainer').classList.add('d-none');
					alert('Settings and all images deleted successfully');
				} else {
					alert('Error: ' + (data.message || 'Failed to delete settings'));
				}
			} catch (error) {
				console.error('Error deleting settings:', error);
				alert('Error deleting settings');
			}
		});
	}
}


function getUpscaleButton(prompt) {
	if (prompt.upscale_status === 0) {
		return `
            <button class="btn btn-success btn-sm upscale-btn mb-2"
                    data-prompt-id="${prompt.id}"
                    data-filename="${prompt.filename}">
                Upscale Image
            </button>
        `;
	} else if (prompt.upscale_status === 1) {
		return `<div class="text-warning">Upscale in progress...</div>`;
	} else if (prompt.upscale_status === 2) {
		return `
            <a href="/storage/upscaled/${prompt.upscale_url}"
               class="btn btn-info btn-sm mb-2"
               target="_blank">
                View Upscaled
            </a>
        `;
	}
	return '';
}

document.addEventListener('DOMContentLoaded', function () {
	imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
});
