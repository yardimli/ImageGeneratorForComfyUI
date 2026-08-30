document.addEventListener('DOMContentLoaded', () => {
    async function poll() {
        try {
            const response = await fetch(window.layerShowConfig.statusUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not check Layerize status.');
            if (data.status === 'ready') {
                window.location.reload();
                return;
            }
            if (data.status === 'failed') {
                document.getElementById('layerPending').classList.add('hidden');
                const failed = document.getElementById('layerFailed');
                failed.textContent = data.error || 'Layerize failed.';
                failed.classList.remove('hidden');
                return;
            }
        } catch (error) {
            console.error('Layerize status check failed:', error);
        }
        setTimeout(poll, 5000);
    }
    setTimeout(poll, 5000);
});
