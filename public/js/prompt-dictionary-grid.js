document.addEventListener('DOMContentLoaded', function () {
	const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
	const llmModelKey = 'promptDictAi_model';
	
	// --- AI Auto-Generate Entries Modal ---
	const generateEntriesModalEl = document.getElementById('generateEntriesModal');
	if (generateEntriesModalEl) {
		const generateEntriesModal = new bootstrap.Modal(generateEntriesModalEl);
		const createBtn = document.getElementById('generate-entries-create-btn');
		const addBtn = document.getElementById('add-generated-entries-btn');
		const previewArea = document.getElementById('generate-entries-preview-area');
		const promptTextarea = document.getElementById('generate-entries-prompt');
		const countSelect = document.getElementById('generate-entries-count');
		const modelSelect = document.getElementById('generate-entries-model');
		const fullPromptTextarea = document.getElementById('generate-entries-full-prompt');
		let generatedEntriesCache = [];
		
		function buildGenerateEntriesPrompt (userRequest, count) {
			const jsonStructure = `{\n  "entries": [\n    { "name": "Entry Name 1", "description": "Description 1" },\n    { "name": "Entry Name 2", "description": "Description 2" }\n  ]\n}`;
			return `You are an AI assistant that generates lists of dictionary entries based on a user's request. The user will provide a topic and a desired number of entries. Your task is to generate that many entries, each with a 'name' and a 'description'. The description should be optimized for AI image generation, focusing on visual, descriptive terms.\n\nUser's request: "${userRequest}"\n\nPlease generate exactly ${count} entries based on this request.\n\nProvide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.\nThe JSON object must follow this exact structure:\n${jsonStructure}`;
		}
		
		function updateFullGenerateEntriesPrompt () {
			const userRequest = promptTextarea.value;
			const count = countSelect.value;
			fullPromptTextarea.value = buildGenerateEntriesPrompt(userRequest, count);
		}
		
		function renderPreview (entries) {
			if (!entries || entries.length === 0) {
				previewArea.innerHTML = '<p class="text-danger">The AI did not return any entries. Please try again or adjust your prompt.</p>';
				addBtn.classList.add('d-none'); // MODIFICATION: Hide save button if no entries
				return;
			}
			let html = '<ul class="list-group">';
			entries.forEach(entry => {
				html += `<li class="list-group-item">
					<h6 class="mb-1">${entry.name || '<em>No Name Provided</em>'}</h6>
					<p class="mb-0 small">${entry.description || '<em>No Description Provided</em>'}</p>
				</li>`;
			});
			html += '</ul>';
			previewArea.innerHTML = html;
			addBtn.classList.remove('d-none');
		}
		
		generateEntriesModalEl.addEventListener('shown.bs.modal', () => {
			updateFullGenerateEntriesPrompt();
			const savedModel = localStorage.getItem(llmModelKey);
			if (savedModel) modelSelect.value = savedModel;
		});
		
		generateEntriesModalEl.addEventListener('hidden.bs.modal', () => {
			previewArea.innerHTML = '<p class="text-muted">Click "Generate Preview" to create new entries and see a preview here.</p>';
			addBtn.classList.add('d-none');
			createBtn.disabled = false;
			createBtn.querySelector('.spinner-border').classList.add('d-none');
			generatedEntriesCache = [];
		});
		
		modelSelect.addEventListener('change',  function (e) {
			localStorage.setItem(llmModelKey, e.target.value);
		});
		
		promptTextarea.addEventListener('input', updateFullGenerateEntriesPrompt);
		countSelect.addEventListener('change', updateFullGenerateEntriesPrompt);
		
		// MODIFICATION START: This button now only generates a preview, it does not save.
		createBtn.addEventListener('click', async () => {
			const prompt = fullPromptTextarea.value;
			const model = modelSelect.value;
			if (!prompt || !model) {
				alert('Prompt and model are required.');
				return;
			}
			createBtn.disabled = true;
			createBtn.querySelector('.spinner-border').classList.remove('d-none');
			previewArea.innerHTML = '<div class="d-flex justify-content-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
			addBtn.classList.add('d-none');
			
			try {
				const response = await fetch('/prompt-dictionary/preview-generated-entries', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
					body: JSON.stringify({ prompt, model }),
				});
				const data = await response.json();
				if (response.ok && data.success) {
					generatedEntriesCache = data.entries;
					renderPreview(data.entries);
				} else {
					previewArea.innerHTML = `<p class="text-danger">An error occurred: ${data.message || 'Unknown error'}</p>`;
				}
			} catch (error) {
				console.error('Generate entries error:', error);
				previewArea.innerHTML = '<p class="text-danger">A network error occurred.</p>';
			} finally {
				createBtn.disabled = false;
				createBtn.querySelector('.spinner-border').classList.add('d-none');
			}
		});
		
		// MODIFICATION START: This button now saves the cached entries and reloads the page.
		addBtn.addEventListener('click', async () => {
			if (generatedEntriesCache.length === 0) return;
			
			addBtn.disabled = true;
			addBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
			
			try {
				const response = await fetch('/prompt-dictionary/store-generated-entries', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
					body: JSON.stringify({ entries: generatedEntriesCache }),
				});
				const data = await response.json();
				if (response.ok && data.success) {
					generateEntriesModal.hide();
					location.reload();
				} else {
					alert('An error occurred while saving: ' + (data.message || 'Unknown error'));
				}
			} catch (error) {
				alert('A network error occurred while saving.');
			} finally {
				addBtn.disabled = false;
				addBtn.innerHTML = 'Save Entries & Refresh';
			}
		});
		// MODIFICATION END
	}
});
