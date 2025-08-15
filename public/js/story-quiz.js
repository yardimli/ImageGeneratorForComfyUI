document.addEventListener('DOMContentLoaded', function () {
	const generateBtn = document.getElementById('generate-btn');
	const addRowBtn = document.getElementById('add-row-btn');
	const container = document.getElementById('quiz-entries-container');
	const template = document.getElementById('quiz-entry-template');
	const noEntriesMessage = document.getElementById('no-entries-message');
	const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
	
	const storyId = window.location.pathname.match(/stories\/(\d+)\/quiz/)[1];
	
	function reindexRows () {
		const rows = container.querySelectorAll('.quiz-entry-row');
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
	
	function addQuizRow (question = '', answers = '', shouldReindex = true) {
		if (!template) return;
		
		const newRow = template.content.cloneNode(true);
		const questionTextarea = newRow.querySelector('textarea[name*="[question]"]');
		const answersTextarea = newRow.querySelector('textarea[name*="[answers]"]');
		
		if (questionTextarea) questionTextarea.value = question;
		if (answersTextarea) answersTextarea.value = answers;
		
		container.appendChild(newRow);
		
		if (shouldReindex) {
			reindexRows();
		}
	};
	
	if (addRowBtn) {
		addRowBtn.addEventListener('click', () => addQuizRow());
	}
	
	if (container) {
		container.addEventListener('click', function (e) {
			if (e.target.classList.contains('remove-row-btn')) {
				e.target.closest('.quiz-entry-row').remove();
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
				const response = await fetch(`/stories/${storyId}/quiz/generate`, {
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
				
				if (data.quiz && Array.isArray(data.quiz)) {
					if (data.quiz.length > 0) {
						data.quiz.forEach(entry => {
							addQuizRow(entry.question, entry.answers, false);
						});
						
						reindexRows();
					} else {
						alert('The AI did not return any new questions. Please try adjusting your prompt.');
					}
				} else {
					throw new Error('The AI returned data in an unexpected format.');
				}
			} catch (error) {
				alert('Error generating quiz: ' + error.message);
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
