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
}

document.addEventListener('DOMContentLoaded', function () {
	
	// Handle template selection
	const templateSelect = document.getElementById('template_path');
	const originalPromptArea = document.querySelector('textarea[name="original_prompt"]');
	const mainPromptArea = document.querySelector('textarea[name="prompt"]');
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
		const templateContent = templateContents[selectedTemplateName];
		
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

// Initial load
	const firstTemplateName = templateSelect.options[0].value;
	const firstTemplateContent = templateContents[firstTemplateName];
	mainPromptArea.value = firstTemplateContent;
	
	// Enable/disable original prompt based on whether template contains {prompt}
	if (firstTemplateContent.includes('{prompt}')) {
		originalPromptArea.disabled = false;
		originalPromptArea.placeholder = "This text will replace {prompt} in the template";
	} else {
		originalPromptArea.disabled = true;
		originalPromptArea.placeholder = "This template doesn't use {prompt}";
	}
	
	
	//---- load saved data
	
	
	document.getElementById('saveTemplateBtn').addEventListener('click', async function () {
		saveTemplate();
	});
});

