document.addEventListener('DOMContentLoaded', function () {
	const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
	const llmModelKey = 'promptDictAi_model';
	
	// START MODIFICATION: Added auto-submit for category filter
	const categoryFilter = document.getElementById('category-filter');
	if (categoryFilter) {
		categoryFilter.addEventListener('change', function () {
			this.closest('form').submit();
		});
	}
	// END MODIFICATION
	
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
		
		// START MODIFICATION: Updated prompt builder to include category
		function buildGenerateEntriesPrompt (userRequest, count, category) {
			const jsonStructure = `{\n  "entries": [\n    { "name": "Entry Name 1", "description": "Description 1" },\n    { "name": "Entry Name 2", "description": "Description 2" }\n  ]\n}`;
			const categoryContext = category
				? `All entries should belong to the category: "${category}".`
				: 'The entries can be of various types.';
			
			return `You are an AI assistant that generates lists of dictionary entries based on a user's request. The user will provide a topic and a desired number of entries. Your task is to generate that many entries, each with a 'name' and a 'description'. The description should be optimized for AI image generation, focusing on visual, descriptive terms.\n\nUser's request: "${userRequest}"\n\n${categoryContext}\n\nPlease generate exactly ${count} entries based on this request.\n\nProvide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.\nThe JSON object must follow this exact structure:\n${jsonStructure}`;
		}
		
		function updateFullGenerateEntriesPrompt () {
			const userRequest = promptTextarea.value;
			const count = countSelect.value;
			const category = document.getElementById('generate-entries-category').value; // Added category
			fullPromptTextarea.value = buildGenerateEntriesPrompt(userRequest, count, category);
		}
		// END MODIFICATION
		
		// START MODIFICATION: Updated preview rendering to show category
		function renderPreview (entries) {
			if (!entries || entries.length === 0) {
				previewArea.innerHTML = '<p class="text-danger">The AI did not return any entries. Please try again or adjust your prompt.</p>';
				addBtn.classList.add('d-none'); // MODIFICATION: Hide save button if no entries
				return;
			}
			let html = '<ul class="list-group">';
			entries.forEach(entry => {
				const categoryBadge = entry.word_category ? `<span class="badge bg-secondary">${entry.word_category}</span>` : '';
				html += `<li class="list-group-item">
					<div class="d-flex justify-content-between align-items-center">
						<h6 class="mb-1">${entry.name || '<em>No Name Provided</em>'}</h6>
						${categoryBadge}
					</div>
					<p class="mb-0 small">${entry.description || '<em>No Description Provided</em>'}</p>
				</li>`;
			});
			html += '</ul>';
			previewArea.innerHTML = html;
			addBtn.classList.remove('d-none');
		}
		// END MODIFICATION
		
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
		// START MODIFICATION: Added event listener for category input
		document.getElementById('generate-entries-category').addEventListener('input', updateFullGenerateEntriesPrompt);
		// END MODIFICATION
		
		// MODIFICATION START: This button now only generates a preview, it does not save.
		createBtn.addEventListener('click', async () => {
			const prompt = fullPromptTextarea.value;
			const model = modelSelect.value;
			const category = document.getElementById('generate-entries-category').value; // Added category
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
					body: JSON.stringify({ prompt, model, word_category: category }), // Added category to request
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
