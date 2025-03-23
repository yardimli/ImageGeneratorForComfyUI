let queueUpdateInterval;

function setDimensions(width, height) {
	const widthInput = document.getElementById('width');
	const heightInput = document.getElementById('height');
	widthInput.value = width;
	heightInput.value = height;
}


document.addEventListener('DOMContentLoaded', function () {
	
	// Handle multiple prompt template textareas
	const promptTemplateContainer = document.getElementById('prompt-template-container');
	const form = document.getElementById('promptForm');
	const resultContainer = document.getElementById('resultContainer');
	
	// Handle template selection
	const templateSelect = document.getElementById('template_path');
	const originalPromptArea = document.querySelector('textarea[name="original_prompt"]');
	const mainPromptArea = document.querySelector('textarea[name="prompt_template"]');
	const aspectRatioSelect = document.getElementById('aspectRatio');
	
	
	
	
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
				
				// Clear existing rows in the prompt template container
				const promptTemplateContainer = document.getElementById('prompt-template-container');
				while (promptTemplateContainer.children.length > 0) {
					promptTemplateContainer.removeChild(promptTemplateContainer.lastChild);
				}
				
				// Split the template content by "::" and create rows
				if (data.prompt_template) {
					const parts = data.prompt_template.split('::');
					
					// Create the first row
					const firstRow = document.createElement('div');
					firstRow.className = 'prompt-template-row mb-2 d-flex';
					firstRow.innerHTML = `
        <textarea class="form-control prompt-template-part me-2" rows="2" placeholder="Prompt section"></textarea>
        <button type="button" style="width:100px;" class="btn btn-success add-template-row">Add</button>
    `;
					promptTemplateContainer.appendChild(firstRow);
					
					// Set the first part to the first textarea
					promptTemplateContainer.querySelector('.prompt-template-part').value = parts[0] || '';
					
					// Create rows for additional parts
					for (let i = 1; i < parts.length; i++) {
						const newRow = document.createElement('div');
						newRow.className = 'prompt-template-row mb-2 d-flex';
						newRow.innerHTML = `
            <textarea class="form-control prompt-template-part me-2" rows="2" placeholder="Prompt section"></textarea>
            <button type="button" style="width:100px;" class="btn btn-danger remove-template-row">Remove</button>
        `;
						promptTemplateContainer.appendChild(newRow);
						newRow.querySelector('.prompt-template-part').value = parts[i].trim() || '';
					}
					
					// Add event listeners to the new buttons
					promptTemplateContainer.querySelectorAll('.remove-template-row').forEach(button => {
						button.addEventListener('click', function () {
							promptTemplateContainer.removeChild(this.parentNode);
						});
					});
				} else {
					// Add an empty row if no template
					const emptyRow = document.createElement('div');
					emptyRow.className = 'prompt-template-row mb-2 d-flex';
					emptyRow.innerHTML = `
        <textarea class="form-control prompt-template-part me-2" rows="2" placeholder="Prompt section"></textarea>
        <button type="button" style="width:100px;" class="btn btn-success add-template-row">Add</button>
    `;
					promptTemplateContainer.appendChild(emptyRow);
				}
				
				// Set the hidden input value for form submission
				document.getElementById('combined-prompt-template').value = data.prompt_template || '';
				
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
				document.querySelector('input[name="create_minimax"]').checked = data.create_minimax;
				document.querySelector('input[name="create_imagen"]').checked = data.create_imagen;
				document.querySelector('input[name="create_aura_flow"]').checked = data.create_aura_flow;
				document.querySelector('input[name="create_ideogram_v2a"]').checked = data.create_ideogram_v2a;
				
				
				displayPrompts(data.prompts, settingId);
				
				if (!allPromptsHaveImages(data.prompts)) {
					reloadAttempts = 0;
					clearInterval(reloadInterval);
					reloadInterval = setInterval(() => reloadPrompts(settingId), 15000);
				}
				
				
			} catch (error) {
				console.error('Error loading settings:', error);
			}
		}
	});


// Function to add a new template row
	function addTemplateRow() {
		const newRow = document.createElement('div');
		newRow.className = 'prompt-template-row mb-2 d-flex';
		newRow.innerHTML = `
        <textarea class="form-control prompt-template-part me-2" rows="2" placeholder="Prompt section"></textarea>
        <button type="button" style="width:100px;" class="btn btn-danger remove-template-row">Remove</button>
    `;
		promptTemplateContainer.appendChild(newRow);
		
		// Add event listener to the new remove button
		newRow.querySelector('.remove-template-row').addEventListener('click', function() {
			promptTemplateContainer.removeChild(newRow);
		});
	}

// Add event delegation for the "Add" button
	promptTemplateContainer.addEventListener('click', function(e) {
		if (e.target.classList.contains('add-template-row')) {
			addTemplateRow();
		}
	});


// Modify the template selection to split content by "::"
	templateSelect.addEventListener('change', async function () {
		const selectedTemplateName = this.value;
		const templateContent = selectedTemplateName ? templateContents[selectedTemplateName] : '';
		
		if (selectedTemplateName) {
			// Clear existing rows except the first one
			while (promptTemplateContainer.children.length > 1) {
				promptTemplateContainer.removeChild(promptTemplateContainer.lastChild);
			}
			
			// Split the template content by "::" and create rows
			const parts = templateContent.split('::');
			
			// Set the first part to the first textarea
			promptTemplateContainer.querySelector('.prompt-template-part').value = parts[0] || '';
			
			// Create rows for additional parts
			for (let i = 1; i < parts.length; i++) {
				addTemplateRow();
				const allRows = promptTemplateContainer.querySelectorAll('.prompt-template-row');
				allRows[i].querySelector('.prompt-template-part').value = parts[i].trim() || '';
			}
		} else {
			// Clear all rows and keep just one empty row
			while (promptTemplateContainer.children.length > 0) {
				promptTemplateContainer.removeChild(promptTemplateContainer.lastChild);
			}
			
			// Add an empty first row
			const emptyRow = document.createElement('div');
			emptyRow.className = 'prompt-template-row mb-2 d-flex';
			emptyRow.innerHTML = `
            <textarea class="form-control prompt-template-part me-2" rows="2" placeholder="Prompt section"></textarea>
            <button type="button"  style="width:100px;"  class="btn btn-success add-template-row">Add</button>
        `;
			promptTemplateContainer.appendChild(emptyRow);
		}
		
		// Handle original prompt field similar to before
		if (!selectedTemplateName) {
			originalPromptArea.disabled = false;
			originalPromptArea.placeholder = "Enter your prompt here";
		} else if (templateContent.includes('{prompt}')) {
			originalPromptArea.disabled = false;
			originalPromptArea.placeholder = "This text will replace {prompt} in the template";
		} else {
			originalPromptArea.disabled = true;
			originalPromptArea.placeholder = "This template doesn't use {prompt}";
		}
	});

// Modify saveTemplate function to combine the textarea values
	async function saveTemplate() {
		const templateParts = Array.from(document.querySelectorAll('.prompt-template-part'))
			.map(textarea => textarea.value.trim());
		
		const promptContent = templateParts.join('::');
		
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
				location.reload();
			} else {
				alert('Error saving template');
			}
		} catch (error) {
			console.error('Error:', error);
			alert('Error saving template');
		}
	}
	
	
	
	
	
	form.addEventListener('submit', async function (e) {
		e.preventDefault();
		
		// Combine all textarea values with "::" delimiter
		const templateParts = Array.from(document.querySelectorAll('.prompt-template-part'))
			.map(textarea => textarea.value.trim());
		
		// Set the combined value to the hidden input
		document.getElementById('combined-prompt-template').value = templateParts.join("\n::\n");
		
		const formData = new FormData(form);
		
		resultContainer.innerHTML = `
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
		resultContainer.classList.remove('d-none');
		
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
                        <div>${prompt}</div>
                    </div>
                `;
				});
				
				// Add the "Generate with these results" button
				html += `
                <div class="text-center mt-4">
                    <button id="storeGeneratedPrompts" class="btn btn-success btn-lg">Generate Images with these Prompts</button>
                </div>
            `;
				
				resultContainer.innerHTML = html;
				
				// Add event listener for the new button
				document.getElementById('storeGeneratedPrompts').addEventListener('click', async function() {
					this.disabled = true;
					this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`;
					
					try {
						const storeResponse = await fetch('/store-generated-prompts', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
							},
							body: JSON.stringify({
								prompts: data.prompts,
								settings: data.settings
							})
						});
						
						const storeData = await storeResponse.json();
						
						if (storeData.success) {
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
								savedSettingsSelect.dispatchEvent(new Event('change'));
							}
						} else {
							alert('Error: ' + storeData.error);
							this.disabled = false;
							this.innerHTML = 'Generate Images with these Prompts';
						}
					} catch (error) {
						alert('Error: ' + error.message);
						this.disabled = false;
						this.innerHTML = 'Generate Images with these Prompts';
					}
				});
				
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
	
	
	
	
	//---- load saved data
	
	
	document.getElementById('saveTemplateBtn').addEventListener('click', async function () {
		saveTemplate();
	});
});

