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
		
		displayPrompts(data.prompts);
		
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

function displayPrompts(prompts) {
	let html = '<h4 class="mb-3">Previously Generated Prompts:</h4>';
	html += `<div class="row">`;
	
	prompts.forEach(prompt => {
		html += `
            <div class="result-item col-3">
                <div>${prompt.generated_prompt}</div>
                ${prompt.filename ? `
                    <div class="mt-2">
                        <img src="${prompt.thumbnail}"
                             style="max-width: 300px; width: 100%; height: auto; cursor: pointer;"
                             alt="Generated Image"
                             onclick="openImageModal('${prompt.filename}')"
                             class="generated-image">
                        <div class="mt-2">
                            <input type="text" class="form-control notes-input mb-2"
                                   value="${prompt.notes || ''}"
                                   placeholder="Add notes..."
                                   data-prompt-id="${prompt.id}">
                            <button class="btn btn-primary btn-sm update-notes-btn mb-2"
                                    data-prompt-id="${prompt.id}">
                                Update Notes
                            </button>
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
	
	const savedSettingsSelect = document.getElementById('savedSettings');
	
	savedSettingsSelect.addEventListener('change', async function () {
		const settingId = this.value;
		if (settingId) {
			try {
				const response = await fetch(`/prompts/settings/${settingId}`);
				const data = await response.json();
				
				
				// Fill form with saved settings
				document.querySelector('select[name="template_path"]').value = data.template_path;
				document.querySelector('select[name="precision"]').value = data.precision;
				document.querySelector('textarea[name="original_prompt"]').value = data.original_prompt;
				document.querySelector('textarea[name="prompt_template"]').value = data.prompt;
				document.querySelector('input[name="count"]').value = data.count;
				document.querySelector('input[name="prepend_text"]').value = data.prepend_text;
				document.querySelector('input[name="append_text"]').value = data.append_text;
				
				// Handle checkboxes
				const generateOriginalCheckbox = document.querySelector('input[type="checkbox"][name="generate_original_prompt"]');
				const appendToPromptCheckbox = document.querySelector('input[type="checkbox"][name="append_to_prompt"]');
				
				if (generateOriginalCheckbox) {
					generateOriginalCheckbox.checked = Boolean(data.generate_original_prompt);
				}
				
				if (appendToPromptCheckbox) {
					appendToPromptCheckbox.checked = Boolean(data.append_to_prompt);
				}
				
				document.querySelector('select[name="aspect_ratio"]').value = data.aspect_ratio;
				document.querySelector('input[name="width"]').value = data.width;
				document.querySelector('input[name="height"]').value = data.height;
				
				document.querySelector('select[name="model"]').value = data.model;
				document.querySelector('input[name="upload_to_s3"]').checked = data.upload_to_s3;
				
				
				displayPrompts(data.prompts);
				
				if (!allPromptsHaveImages(data.prompts)) {
					reloadAttempts = 0;
					clearInterval(reloadInterval);
					reloadInterval = setInterval(() => reloadPrompts(settingId), 10000);
				}
				
				
			} catch (error) {
				console.error('Error loading settings:', error);
			}
		}
	});
}

})
;
