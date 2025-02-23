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

function setDimensions(width, height) {
	const widthInput = document.getElementById('width');
	const heightInput = document.getElementById('height');
	widthInput.value = width;
	heightInput.value = height;
}

async function saveTemplate() {
	const promptContent = document.querySelector('textarea[name="prompt_template"]').value;
	
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
}

document.addEventListener('DOMContentLoaded', function () {
	
	// Handle template selection
	const templateSelect = document.getElementById('template_path');
	const originalPromptArea = document.querySelector('textarea[name="original_prompt"]');
	const mainPromptArea = document.querySelector('textarea[name="prompt_template"]');
	const aspectRatioSelect = document.getElementById('aspectRatio');
	
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
				
				// Show the prompt queued modal
				const promptQueuedModal = new bootstrap.Modal(document.getElementById('promptQueuedModal'));
				promptQueuedModal.show();
				
				// Update saved settings dropdown
				const savedSettingsSelect = document.getElementById('savedSettings');
				const response2 = await fetch('/prompts/settings/latest');
				const settingsData = await response2.json();
				
				if (settingsData.success) {
					const option = new Option(
						`${settingsData.setting.created_at} - ${settingsData.setting.width}x${settingsData.setting.height} - ${settingsData.setting.template_path} - ${settingsData.setting.count * settingsData.setting.render_each_prompt_times} images`,
						settingsData.setting.id,
						true,
						true
					);
					savedSettingsSelect.add(option, 0);
				}
				
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
	
	templateSelect.addEventListener('change', async function () {
		const selectedTemplateName = this.value;
		console.log(selectedTemplateName);
		const templateContent = selectedTemplateName ? templateContents[selectedTemplateName] : '';
		
		mainPromptArea.value = templateContent;
		
		if (!selectedTemplateName) {
			// If "No Template" is selected
			originalPromptArea.disabled = false;
			originalPromptArea.placeholder = "Enter your prompt here";
			mainPromptArea.value = '';  // Clear the main prompt area
		} else if (templateContent.includes('{prompt}')) {
			// If template contains {prompt}
			originalPromptArea.disabled = false;
			originalPromptArea.placeholder = "This text will replace {prompt} in the template";
		} else {
			// If template doesn't use {prompt}
			originalPromptArea.disabled = true;
			originalPromptArea.placeholder = "This template doesn't use {prompt}";
		}
	});
	
	
	
	//---- load saved data
	
	
	document.getElementById('saveTemplateBtn').addEventListener('click', async function () {
		saveTemplate();
	});
});

