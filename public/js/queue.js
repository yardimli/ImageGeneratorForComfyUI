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

// START MODIFICATION
/**
 * Fetches and updates the upscale queue count on the page.
 */
function updateUpscaleQueueCount() {
	fetch('/api/prompts/upscale-queue-count')
		.then(response => response.json())
		.then(data => {
			// Update all upscale queue count elements on the page
			document.querySelectorAll('#navUpscaleQueueCount').forEach(element => {
				if (element) {
					element.textContent = data.count;
					// Hide badge if count is 0, otherwise show it with a warning color.
					if (data.count > 0) {
						element.className = 'badge bg-warning';
					} else {
						element.className = 'badge bg-warning d-none';
					}
				}
			});
		})
		.catch(error => console.error('Error fetching upscale queue count:', error));
}
// END MODIFICATION

document.addEventListener('DOMContentLoaded', function () {
	queueUpdateInterval = setInterval(updateQueueCount, 5000);
	updateQueueCount();
	
	// START MODIFICATION
	upscaleQueueUpdateInterval = setInterval(updateUpscaleQueueCount, 5000);
	updateUpscaleQueueCount();
	// END MODIFICATION
	
	window.addEventListener('beforeunload', () => {
		if (queueUpdateInterval) {
			clearInterval(queueUpdateInterval);
		}
		// START MODIFICATION
		if (upscaleQueueUpdateInterval) {
			clearInterval(upscaleQueueUpdateInterval);
		}
		// END MODIFICATION
	});
});
