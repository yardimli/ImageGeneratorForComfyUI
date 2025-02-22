document.addEventListener('DOMContentLoaded', function () {
	
	let queueUpdateInterval;
	
	function updateQueueCount() {
		fetch('/api/prompts/queue-count')
			.then(response => response.json())
			.then(data => {
				const queueCountElement = document.getElementById('queueCount');
				queueCountElement.textContent = data.count;
				
				// Optional: Change badge color based on count
				queueCountElement.className = 'badge ' +
					(data.count > 10 ? 'bg-danger' :
						data.count > 5 ? 'bg-warning' :
							'bg-primary');
			})
			.catch(error => console.error('Error fetching queue count:', error));
	}

	queueUpdateInterval = setInterval(updateQueueCount, 3000);
	updateQueueCount();

	window.addEventListener('beforeunload', () => {
		if (queueUpdateInterval) {
			clearInterval(queueUpdateInterval);
		}
	});
	
	
	const form = document.getElementById('promptForm');
	const resultContainer = document.getElementById('resultContainer');
	
	form.addEventListener('submit', async function (e) {
		e.preventDefault();
		
		resultContainer.innerHTML = `
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;
		resultContainer.classList.remove('d-none');
		
		const formData = new FormData(form);
		
		try {
			const response = await fetch('/generate', {
				method: 'POST',
				body: formData
			});
			
			const data = await response.json();
			
			if (data.success) {
				let html = '<h4 class="mb-3">Generated Prompts:</h4>';
				data.prompts.forEach(prompt => {
					html += `
                                    <div class="result-item">
                                        <div>
                                            ${prompt}
                                        </div>
                                    </div>
                                `;
				});
				resultContainer.innerHTML = html;
			} else {
				resultContainer.innerHTML = `
                            <div class="alert alert-danger" role="alert">
                                Error: ${data.error}
                            </div>
                        `;
			}
		} catch (error) {
			resultContainer.innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            Error: ${error.message}
                        </div>
                    `;
		}
	});
	
	// Handle template selection
	const templateSelect = document.getElementById('template');
	const originalPromptArea = document.querySelector('textarea[name="original_prompt"]');
	const mainPromptArea = document.querySelector('textarea[name="prompt"]');
	const aspectRatioSelect = document.getElementById('aspectRatio');
	const widthInput = document.getElementById('width');
	const heightInput = document.getElementById('height');
	
	function setDimensions(width, height) {
		widthInput.value = width;
		heightInput.value = height;
	}
	
	if (aspectRatioSelect) {
		aspectRatioSelect.addEventListener('change', function () {
			const [ratio, baseSize] = this.value.split('-');
			const [w, h] = ratio.split(':');
			
			if (baseSize === '1024') {
				switch (ratio) {
					case '1:1':
						setDimensions(1024, 1024);
						break;
					case '3:2':
						setDimensions(1216, 832);
						break;
					case '4:3':
						setDimensions(1152, 896);
						break;
					case '16:9':
						setDimensions(1344, 768);
						break;
					case '21:9':
						setDimensions(1536, 640);
						break;
					case '2:3':
						setDimensions(832, 1216);
						break;
					case '3:4':
						setDimensions(896, 1152);
						break;
					case '9:16':
						setDimensions(768, 1344);
						break;
					case '9:21':
						setDimensions(640, 1536);
						break;
				}
			} else {
				switch (ratio) {
					case '1:1':
						setDimensions(1408, 1408);
						break;
					case '3:2':
						setDimensions(1728, 1152);
						break;
					case '4:3':
						setDimensions(1664, 1216);
						break;
					case '16:9':
						setDimensions(1920, 1088);
						break;
					case '21:9':
						setDimensions(2176, 960);
						break;
					case '2:3':
						setDimensions(1152, 1728);
						break;
					case '3:4':
						setDimensions(1216, 1664);
						break;
					case '9:16':
						setDimensions(1088, 1920);
						break;
					case '9:21':
						setDimensions(960, 2176);
						break;
				}
			}
		});
	}
	
	if (templateSelect) {
		templateSelect.addEventListener('change', async function () {
			
			const templateContent = this.value;
			mainPromptArea.value = templateContent;
			// Enable/disable original prompt based on whether template contains {prompt}
			if (templateContent.includes('{prompt}')) {
				originalPromptArea.disabled = false;
				originalPromptArea.placeholder = "This text will replace {prompt} in the template";
			} else {
				originalPromptArea.disabled = true;
				originalPromptArea.placeholder = "This template doesn't use {prompt}";
			}
		});
	}
	
	if (templateSelect && mainPromptArea) {
		const firstTemplateContent = templateSelect.options[0].value;
		mainPromptArea.value = firstTemplateContent;
		
		// Enable/disable original prompt based on whether template contains {prompt}
		if (firstTemplateContent.includes('{prompt}')) {
			originalPromptArea.disabled = false;
			originalPromptArea.placeholder = "This text will replace {prompt} in the template";
		} else {
			originalPromptArea.disabled = true;
			originalPromptArea.placeholder = "This template doesn't use {prompt}";
		}
	}
	
	
	//---- load saved data
	const savedSettingsSelect = document.getElementById('savedSettings');
	if (savedSettingsSelect) {
		savedSettingsSelect.addEventListener('change', async function () {
			const settingId = this.value;
			if (settingId) {
				try {
					const response = await fetch(`/prompts/settings/${settingId}`);
					const data = await response.json();
					
					// Fill form with saved settings
					document.querySelector('select[name="template"]').value = data.template_path;
					document.querySelector('select[name="precision"]').value = data.precision;
					document.querySelector('textarea[name="original_prompt"]').value = data.original_prompt;
					document.querySelector('textarea[name="prompt"]').value = data.prompt;
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
					
					
					// Display previously generated prompts
					// Replace the existing code for displaying previously generated prompts with this:
					if (data.prompts && data.prompts.length > 0) {
						let html = '<h4 class="mb-3">Previously Generated Prompts:</h4>';
						html += `<div class="row">`;
						data.prompts.forEach(prompt => {
							html += `
            <div class="result-item col-3">
                <div>${prompt.generated_prompt}</div>
                ${prompt.filename ?
								`<div class="mt-2">
                        <img src="${prompt.filename}" style="max-width: 300px; width: 100%; height: auto;" alt="Generated Image">
                    </div>`
								: ''
							}
            </div>
        `;
						});
						html += `</div>`;
						document.getElementById('resultContainer').innerHTML = html;
						document.getElementById('resultContainer').classList.remove('d-none');
					}
				} catch (error) {
					console.error('Error loading settings:', error);
				}
			}
		});
	}
	
	
	document.getElementById('saveTemplateBtn').addEventListener('click', async function() {
		const promptContent = document.querySelector('textarea[name="prompt"]').value;
		
		if (!promptContent) {
			alert('Please enter a prompt template first');
			return;
		}
		
		const templateName = prompt('Enter a name for this template:');
		if (!templateName) return;
		
		try {
			const response = await fetch('/templates/save', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					name: templateName,
					content: promptContent
				})
			});
			
			const data = await response.json();
			
			if (data.success) {
				// Refresh the page to update the template list
				location.reload();
			} else {
				alert('Error saving template');
			}
		} catch (error) {
			console.error('Error:', error);
			alert('Error saving template');
		}
	});
});

