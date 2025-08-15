document.addEventListener('DOMContentLoaded', function () {
	const generateBtn = document.getElementById('generate-btn');
	const addRowBtn = document.getElementById('add-row-btn');
	const container = document.getElementById('dictionary-entries-container');
	const template = document.getElementById('dictionary-entry-template');
	const noEntriesMessage = document.getElementById('no-entries-message');
	const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
	
	const storyId = window.location.pathname.match(/stories\/(\d+)\/dictionary/)[1];
	
	function reindexRows () {
		const rows = container.querySelectorAll('.dictionary-entry-row');
		rows.forEach((row, index) => {
			row.querySelectorAll('[name]').forEach(input => {
				const name = input.getAttribute('name');
				if (name) {
					// Replace the first occurrence of a number index or the placeholder index.
					input.setAttribute('name', name.replace(/\[(\d+|__INDEX__)\]/, `[${index}]`));
				}
			});
		});
		if (noEntriesMessage) {
			noEntriesMessage.classList.toggle('d-none', rows.length > 0);
		}
	};
	
	// MODIFICATION: Add an optional parameter to control when re-indexing occurs.
	// This prevents the function from being called unnecessarily inside a loop.
	function addDictionaryRow (word = '', explanation = '', shouldReindex = true) {
		if (!template) return;
		
		const newRow = template.content.cloneNode(true);
		const wordInput = newRow.querySelector('input[name*="[word]"]');
		const explanationTextarea = newRow.querySelector('textarea[name*="[explanation]"]');
		
		if (wordInput) wordInput.value = word;
		if (explanationTextarea) explanationTextarea.value = explanation;
		
		container.appendChild(newRow);
		
		// MODIFICATION: Only re-index if the flag is true.
		if (shouldReindex) {
			reindexRows();
		}
	};
	
	if (addRowBtn) {
		// For manual additions, the default behavior of re-indexing immediately is correct.
		addRowBtn.addEventListener('click', () => addDictionaryRow());
	}
	
	if (container) {
		container.addEventListener('click', function (e) {
			if (e.target.classList.contains('remove-row-btn')) {
				e.target.closest('.dictionary-entry-row').remove();
				reindexRows();
			}
		});
	}
	
	if (generateBtn) {
		generateBtn.addEventListener('click', async function () {
			const button = this;
			const spinner = button.querySelector('.spinner-border');
			const prompt = document.getElementById('ai-prompt').value;
			const model = document.getElementById('ai-model').value;
			
			button.disabled = true;
			spinner.classList.remove('d-none');
			
			try {
				const response = await fetch(`/stories/${storyId}/dictionary/generate`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json'
					},
					body: JSON.stringify({ prompt, model })
				});
				
				const data = await response.json();
				
				if (!response.ok) {
					throw new Error(data.message || 'An unknown error occurred.');
				}
				
				if (data.dictionary && Array.isArray(data.dictionary)) {
					// START MODIFICATION: Append new entries instead of replacing existing ones.
					if (data.dictionary.length > 0) {
						// Add all new rows to the end of the list.
						data.dictionary.forEach(entry => {
							addDictionaryRow(entry.word, entry.explanation, false);
						});
						
						// Re-index all rows to ensure form submission is correct.
						reindexRows();
					} else {
						// If the AI returns no entries, alert the user without clearing the list.
						alert('The AI did not return any new entries. Please try adjusting your prompt.');
					}
					// END MODIFICATION
				} else {
					throw new Error('The AI returned data in an unexpected format.');
				}
			} catch (error) {
				alert('Error generating dictionary: ' + error.message);
			} finally {
				button.disabled = false;
				spinner.classList.add('d-none');
			}
		});
	}
	
	// Handle default model selection from localStorage
	const modelSelect = document.getElementById('ai-model');
	const modelStorageKey = 'storyCreateAi_model';
	
	if (modelSelect) {
		const savedModel = localStorage.getItem(modelStorageKey);
		if (savedModel) {
			modelSelect.value = savedModel;
		}
		
		modelSelect.addEventListener('change', function () {
			localStorage.setItem(modelStorageKey, this.value);
		});
	}
});
