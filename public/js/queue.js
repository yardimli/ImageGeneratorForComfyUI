function updateQueueCount() {
	fetch('/api/prompts/queue-count')
		.then(response => response.json())
		.then(data => {
			// Update all queue count elements on the page
			document.querySelectorAll('#queueCount, #navQueueCount').forEach(element => {
				if (element) {
					element.textContent = data.count;
					element.className = 'badge ' + (data.count > 10 ? 'bg-danger' : data.count > 5 ? 'bg-info' : 'bg-primary');
				}
			});
		})
		.catch(error => console.error('Error fetching queue count:', error));
}

document.addEventListener('DOMContentLoaded', function () {
	queueUpdateInterval = setInterval(updateQueueCount, 3000);
	updateQueueCount();
	
	window.addEventListener('beforeunload', () => {
		if (queueUpdateInterval) {
			clearInterval(queueUpdateInterval);
		}
	});
});
