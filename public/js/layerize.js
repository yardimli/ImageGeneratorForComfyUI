document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('startLayerize');
    const status = document.getElementById('layerizeStatus');
    let selectedImage = '';

    window.addEventListener('dreamcover:image-selected', (event) => {
        selectedImage = event.detail.path;
        button.disabled = !selectedImage;
    });

    button.addEventListener('click', async () => {
        if (!selectedImage) return;
        button.disabled = true;
        status.textContent = 'Queueing Layerize…';
        try {
            const response = await fetch(window.layerizeConfig.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': window.layerizeConfig.csrfToken,
                },
                body: JSON.stringify({ image: selectedImage }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Could not queue Layerize.');
            window.location.assign(data.url);
        } catch (error) {
            status.textContent = error.message;
            button.disabled = false;
        }
    });
});
