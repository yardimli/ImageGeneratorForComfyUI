window.attachClipboardImagePicker = function (modalElement, onImage) {
    const button = modalElement.querySelector('#pasteClipboardImageBtn');
    if (!button) return;
    let busy = false;
    let session = 0;
    modalElement.addEventListener('hidden.dream.modal', () => { session++; });
    const isOpen = () => modalElement.getAttribute('aria-hidden') !== 'true';

    async function importImage(readImage) {
        if (busy || !isOpen()) return;
        busy = true;
        button.disabled = true;
        const currentSession = session;
        try {
            const blob = await readImage();
            if (!blob) throw new Error('No image found in the clipboard. Copy an image first, then try again.');
            const dataUrl = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('Could not read the clipboard image. Please try copying it again.'));
                reader.readAsDataURL(blob);
            });
            if (isOpen() && session === currentSession) onImage(dataUrl);
        } catch (error) {
            if (isOpen() && session === currentSession) {
                alert(error.name === 'NotAllowedError' || error.name === 'SecurityError'
                    ? 'Clipboard access was blocked. Allow clipboard access, or press Ctrl+V (Cmd+V on Mac) in this dialog.'
                    : error.message);
            }
        } finally {
            busy = false;
            button.disabled = false;
        }
    }

    button.addEventListener('click', () => importImage(async () => {
        if (!navigator.clipboard?.read) {
            throw new Error('This browser cannot read the clipboard directly. Press Ctrl+V (Cmd+V on Mac) in this dialog, or upload an image.');
        }
        const items = await navigator.clipboard.read();
        for (const item of items) {
            const type = item.types.find((type) => type.startsWith('image/'));
            if (type) return item.getType(type);
        }
        return null;
    }));

    document.addEventListener('paste', (event) => {
        if (!isOpen()) return;
        const item = Array.from(event.clipboardData?.items || [])
            .find((item) => item.kind === 'file' && item.type.startsWith('image/'));
        if (!item) return;
        event.preventDefault();
        const file = item.getAsFile();
        importImage(() => file);
    });
};
