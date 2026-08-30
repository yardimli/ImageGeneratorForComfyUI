import { writePsd } from 'ag-psd';

document.addEventListener('DOMContentLoaded', () => {
    const config = window.layerExportConfig;
    const button = document.getElementById('downloadLayerPsd');
    const status = document.getElementById('layerExportStatus');

    if (!config || !button || !status) return;

    const createCanvas = (width, height) => {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        return canvas;
    };

    const loadBitmap = async (url) => {
        const response = await fetch(url, { headers: { Accept: 'image/*' } });
        if (!response.ok) throw new Error(`Could not download a layer (HTTP ${response.status}).`);
        const blob = await response.blob();

        if ('createImageBitmap' in window) return createImageBitmap(blob);

        return new Promise((resolve, reject) => {
            const image = new Image();
            const objectUrl = URL.createObjectURL(blob);
            image.onload = () => {
                URL.revokeObjectURL(objectUrl);
                resolve(image);
            };
            image.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('A layer image could not be decoded.'));
            };
            image.src = objectUrl;
        });
    };

    const validBounds = (bounds) => Array.isArray(bounds)
        && bounds.length === 4
        && bounds.every(Number.isFinite)
        && bounds[2] > bounds[0]
        && bounds[3] > bounds[1];

    const saveBlob = (blob, filename) => {
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        status.textContent = 'Downloading layers…';

        try {
            const sourceLayers = [...config.layers].sort((a, b) => a.zIndex - b.zIndex);
            const loaded = [];

            for (let index = 0; index < sourceLayers.length; index++) {
                status.textContent = `Downloading layer ${index + 1} of ${sourceLayers.length}…`;
                loaded.push({ ...sourceLayers[index], bitmap: await loadBitmap(sourceLayers[index].url) });
            }

            const base = loaded.find((layer) => layer.zIndex === 0) ?? loaded[0];
            if (!base) throw new Error('No layer images are available.');

            const documentWidth = Number(base.width) || base.bitmap.width;
            const documentHeight = Number(base.height) || base.bitmap.height;
            if (!documentWidth || !documentHeight) throw new Error('Could not determine the PSD dimensions.');

            status.textContent = 'Building PSD layers…';
            const psdLayers = loaded.map((layer) => {
                const bounds = validBounds(layer.bounds) ? layer.bounds.map(Number) : null;
                const isFullDocument = layer.bitmap.width === documentWidth && layer.bitmap.height === documentHeight;
                let left = 0;
                let top = 0;
                let width = documentWidth;
                let height = documentHeight;

                if (layer.zIndex !== 0 && bounds && !isFullDocument) {
                    [left, top] = bounds;
                    width = bounds[2] - bounds[0];
                    height = bounds[3] - bounds[1];
                }

                const canvas = createCanvas(width, height);
                canvas.getContext('2d').drawImage(layer.bitmap, 0, 0, width, height);

                return {
                    zIndex: layer.zIndex,
                    left,
                    top,
                    canvas,
                    name: `z${layer.zIndex} · ${layer.name || `Layer ${layer.index}`}`,
                };
            });

            const composite = createCanvas(documentWidth, documentHeight);
            const compositeContext = composite.getContext('2d');
            [...psdLayers]
                .sort((a, b) => a.zIndex - b.zIndex)
                .forEach((layer) => compositeContext.drawImage(layer.canvas, layer.left, layer.top));

            const children = [...psdLayers]
                .sort((a, b) => b.zIndex - a.zIndex)
                .map(({ zIndex, ...layer }) => layer);

            status.textContent = 'Writing PSD file…';
            const buffer = writePsd({
                width: documentWidth,
                height: documentHeight,
                canvas: composite,
                children,
            }, {
                generateThumbnail: true,
                noBackground: true,
            });

            saveBlob(new Blob([buffer], { type: 'application/vnd.adobe.photoshop' }), `layerize-${config.jobId}.psd`);
            status.textContent = 'PSD downloaded.';
        } catch (error) {
            console.error('PSD export failed:', error);
            status.textContent = error.message || 'PSD export failed.';
        } finally {
            button.disabled = false;
        }
    });
});
