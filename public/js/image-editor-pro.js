document.addEventListener('DOMContentLoaded', () => {
    const config = window.seedreamProConfig;
    const canvas = document.getElementById('proEditCanvas');
    const context = canvas.getContext('2d');
    const canvasPanel = document.getElementById('proCanvasPanel');
    const addAreaButton = document.getElementById('addAreaButton');
    const clearAreasButton = document.getElementById('clearAreasButton');
    const promptsContainer = document.getElementById('areaPrompts');
    const generateButton = document.getElementById('generateProEdit');
    const status = document.getElementById('proEditStatus');
    const positionSwitch = document.getElementById('generalPromptAtEnd');
    const colors = [
        { name: 'red', stroke: '#ef4444' },
        { name: 'green', stroke: '#22c55e' },
        { name: 'yellow', stroke: '#facc15' },
        { name: 'blue', stroke: '#3b82f6' },
        { name: 'purple', stroke: '#a855f7' },
    ];
    const sourceImage = new Image();
    let selectedPath = '';
    let areas = [];
    let drawing = false;
    let start = null;
    let draft = null;

    async function canvasSafeUrl(path) {
        const url = new URL(path, window.location.origin);
        if (url.origin === window.location.origin) return url.href;
        const response = await fetch(config.proxyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
            body: JSON.stringify({ url: path }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Could not prepare the selected image.');
        return data.local_url;
    }

    async function loadSource(path) {
        selectedPath = path;
        areas = [];
        renderAreaInputs();
        if (!path) {
            canvasPanel.classList.add('hidden');
            generateButton.disabled = true;
            return;
        }
        status.textContent = 'Preparing image…';
        try {
            sourceImage.src = await canvasSafeUrl(path);
            await sourceImage.decode();
            const scale = Math.min(1, 2048 / Math.max(sourceImage.naturalWidth, sourceImage.naturalHeight));
            canvas.width = Math.round(sourceImage.naturalWidth * scale);
            canvas.height = Math.round(sourceImage.naturalHeight * scale);
            canvasPanel.classList.remove('hidden');
            generateButton.disabled = false;
            redraw();
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message;
        }
    }

    function redraw() {
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(sourceImage, 0, 0, canvas.width, canvas.height);
        [...areas, ...(draft ? [draft] : [])].forEach((area) => {
            context.save();
            context.strokeStyle = area.stroke;
            context.lineWidth = Math.max(5, canvas.width / 180);
            context.strokeRect(area.x, area.y, area.width, area.height);
            context.restore();
        });
    }

    function point(event) {
        const bounds = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - bounds.left) * canvas.width / bounds.width,
            y: (event.clientY - bounds.top) * canvas.height / bounds.height,
        };
    }

    function renderAreaInputs() {
        promptsContainer.innerHTML = '';
        areas.forEach((area, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'rounded-xl border border-slate-200 p-3 dark:border-slate-700';
            const header = document.createElement('div');
            header.className = 'mb-2 flex items-center justify-between';
            header.innerHTML = '<strong class="capitalize" style="color:' + area.stroke + '">' + area.color + ' frame</strong>';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'text-xs font-semibold text-rose-600';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => {
                areas.splice(index, 1);
                renderAreaInputs();
                redraw();
            });
            header.appendChild(remove);
            const input = document.createElement('textarea');
            input.className = 'form-control';
            input.rows = 2;
            input.placeholder = 'Describe what belongs inside this frame…';
            input.value = area.prompt || '';
            input.addEventListener('input', () => { area.prompt = input.value; });
            wrapper.append(header, input);
            promptsContainer.appendChild(wrapper);
        });
        addAreaButton.disabled = areas.length >= colors.length;
    }

    addAreaButton.addEventListener('click', () => {
        if (!selectedPath || areas.length >= colors.length) return;
        drawing = true;
        start = null;
        draft = null;
        status.textContent = 'Drag on the image to draw the ' + colors[areas.length].name + ' frame.';
    });
    clearAreasButton.addEventListener('click', () => {
        areas = [];
        drawing = false;
        draft = null;
        renderAreaInputs();
        redraw();
    });
    canvas.addEventListener('pointerdown', (event) => {
        if (!drawing) return;
        start = point(event);
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!drawing || !start) return;
        const current = point(event);
        const color = colors[areas.length];
        draft = {
            color: color.name,
            stroke: color.stroke,
            x: Math.min(start.x, current.x),
            y: Math.min(start.y, current.y),
            width: Math.abs(current.x - start.x),
            height: Math.abs(current.y - start.y),
            prompt: '',
        };
        redraw();
    });
    canvas.addEventListener('pointerup', () => {
        if (!draft || draft.width < 10 || draft.height < 10) return;
        areas.push(draft);
        draft = null;
        drawing = false;
        start = null;
        status.textContent = '';
        renderAreaInputs();
        redraw();
    });
    positionSwitch.addEventListener('change', () => {
        document.getElementById('generalPositionLabel').textContent = positionSwitch.checked ? 'End' : 'Beginning';
    });
    window.addEventListener('dreamcover:image-selected', (event) => loadSource(event.detail.path));

    async function uploadAnnotatedImage() {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.94));
        const formData = new FormData();
        formData.append('image', blob, 'seedream-frames.jpg');
        const response = await fetch(config.uploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
            body: formData,
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || data.error || 'Could not upload the annotated image.');
        return data.source_path || data.path;
    }

    generateButton.addEventListener('click', async () => {
        if (!selectedPath) return alert('Select one image first.');
        const areaPayload = areas.map((area) => ({ color: area.color, prompt: area.prompt.trim() }));
        if (areaPayload.some((area) => !area.prompt)) return alert('Enter a prompt for every colored frame.');
        if (!document.getElementById('generalPrompt').value.trim() && areaPayload.length === 0) {
            return alert('Enter a general prompt or add an area.');
        }
        generateButton.disabled = true;
        status.textContent = 'Uploading the annotated image…';
        try {
            const response = await fetch(config.generateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: JSON.stringify({
                    general_prompt: document.getElementById('generalPrompt').value,
                    general_position: positionSwitch.checked ? 'end' : 'beginning',
                    image: await uploadAnnotatedImage(),
                    width: canvas.width,
                    height: canvas.height,
                    areas: areaPayload,
                }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Could not queue the edit.');
            status.textContent = 'Queued. The global render worker is processing this edit.';
            poll(data.prompt_id);
        } catch (error) {
            status.textContent = error.message;
            generateButton.disabled = false;
        }
    });

    function poll(id) {
        setTimeout(async () => {
            try {
                const response = await fetch(config.statusBaseUrl + '/' + id, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (data.status === 'ready') {
                    document.getElementById('proEditResult').src = data.preview_url;
                    document.getElementById('proEditResultCard').classList.remove('hidden');
                    status.textContent = 'Edit complete and saved to the gallery.';
                    generateButton.disabled = false;
                    return;
                }
                if (data.status === 'failed') throw new Error('The Seedream edit failed.');
                poll(id);
            } catch (error) {
                status.textContent = error.message;
                generateButton.disabled = false;
            }
        }, 5000);
    }
});
