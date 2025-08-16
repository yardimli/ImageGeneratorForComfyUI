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
	const templateRelatedControls = document.getElementById('template-related-controls');
	
	
	function toggleTemplateControls() {
		if (templateSelect.value) { // Show if a template is selected
			templateRelatedControls.classList.remove('d-none');
		} else { // Hide if "No Template" is selected
			templateRelatedControls.classList.add('d-none');
		}
	}
	
	toggleTemplateControls();

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
		toggleTemplateControls();
		
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
			originalPromptArea.placeholder = "Enter your prompt here. Type # to search dictionary.";
		} else if (templateContent.includes('{prompt}')) {
			originalPromptArea.disabled = false;
			originalPromptArea.placeholder = "This text will replace {prompt} in the template. Type # to search dictionary.";
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
			const response = await fetch('/prompts/generate', {
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
						const storeResponse = await fetch('/prompts/store-generated', {
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
	
	// START MODIFICATION: Dictionary search popup logic
	const dictionaryPopup = document.getElementById('dictionary-popup');
	let dictionarySearchActive = false;
	
	async function fetchDictionaryEntries(term) {
		try {
			const response = await fetch(`/prompt-dictionary/search?term=${encodeURIComponent(term)}`);
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return await response.json();
		} catch (error) {
			console.error('Failed to fetch dictionary entries:', error);
			return [];
		}
	}
	
	function renderDictionaryPopup(entries, textarea) {
		dictionaryPopup.innerHTML = '';
		if (entries.length === 0) {
			dictionaryPopup.innerHTML = '<div class="list-group-item disabled">No results found</div>';
		} else {
			entries.forEach(entry => {
				const item = document.createElement('a');
				item.href = '#';
				item.className = 'list-group-item list-group-item-action';
				item.dataset.name = entry.name; // We will insert the name
				item.innerHTML = `
                <img src="${entry.image_path}" alt="${entry.name}" style="max-width: 64px; max-height: 64px;">
                <div class="dictionary-item-info">
                    <strong>${entry.name}</strong>
                    <p>${entry.description || entry.image_prompt || 'No description'}</p>
                </div>
            `;
				item.addEventListener('click', function(e) {
					e.preventDefault();
					insertDictionaryEntry(this.dataset.name);
				});
				dictionaryPopup.appendChild(item);
			});
		}
		
		const rect = textarea.getBoundingClientRect();
		dictionaryPopup.style.top = `${rect.bottom + window.scrollY + 5}px`;
		dictionaryPopup.style.left = `${rect.left + window.scrollX}px`;
		dictionaryPopup.style.display = 'block';
	}
	
	function insertDictionaryEntry(name) {
		const text = originalPromptArea.value;
		const cursorPos = originalPromptArea.selectionStart;
		
		// Find the start of the search term (#...)
		const textBeforeCursor = text.substring(0, cursorPos);
		const searchStart = textBeforeCursor.lastIndexOf('#');
		
		if (searchStart !== -1) {
			const textBefore = text.substring(0, searchStart);
			const textAfter = text.substring(cursorPos);
			
			originalPromptArea.value = textBefore + name + ' ' + textAfter;
			
			// Set cursor position after the inserted text
			const newCursorPos = (textBefore + name + ' ').length;
			originalPromptArea.focus();
			originalPromptArea.setSelectionRange(newCursorPos, newCursorPos);
		}
		
		hideDictionaryPopup();
	}
	
	function hideDictionaryPopup() {
		dictionaryPopup.style.display = 'none';
		dictionarySearchActive = false;
	}
	
	if (originalPromptArea && dictionaryPopup) {
		originalPromptArea.addEventListener('input', async function(e) {
			const text = this.value;
			const cursorPos = this.selectionStart;
			
			// Find the last '#' before the cursor
			const textBeforeCursor = text.substring(0, cursorPos);
			const tagIndex = textBeforeCursor.lastIndexOf('#');
			
			if (tagIndex !== -1) {
				// Check if there's a space between '#' and cursor. If so, it's not an active search.
				const potentialTerm = textBeforeCursor.substring(tagIndex);
				if (!/\s/.test(potentialTerm)) {
					dictionarySearchActive = true;
					const searchTerm = potentialTerm.substring(1); // remove #
					
					const entries = await fetchDictionaryEntries(searchTerm);
					renderDictionaryPopup(entries, this);
					return; // Exit to prevent hiding
				}
			}
			
			// If we reach here, it's not an active search
			hideDictionaryPopup();
		});
		
		originalPromptArea.addEventListener('keydown', function(e) {
			if (dictionarySearchActive && e.key === 'Escape') {
				e.preventDefault();
				hideDictionaryPopup();
			}
		});
		
		// Hide popup when clicking outside
		document.addEventListener('click', function(e) {
			if (dictionarySearchActive && !dictionaryPopup.contains(e.target) && e.target !== originalPromptArea) {
				hideDictionaryPopup();
			}
		});
	}
	// END MODIFICATION
});
