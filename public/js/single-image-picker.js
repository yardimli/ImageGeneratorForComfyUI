document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const cropperModalElement = document.getElementById('cropperModal');
    const historyModalElement = document.getElementById('historyModal');
    const imageToCrop = document.getElementById('imageToCrop');
    const pathInput = document.getElementById('singleImagePath');
    const preview = document.getElementById('singleImagePreview');
    const previewImage = document.getElementById('singleImagePreviewImage');
    const selectButton = document.getElementById('selectSingleImage');
    const cropperModal = new DreamModal(cropperModalElement);
    const historyModal = new DreamModal(historyModalElement);
    let cropper = null;

    function selectImage(path) {
        pathInput.value = path;
        previewImage.src = path;
        preview.classList.remove('hidden');
        selectButton.textContent = 'Replace image';
        window.dispatchEvent(new CustomEvent('dreamcover:image-selected', { detail: { path } }));
    }

    function openCropper(url) {
        imageToCrop.src = url;
        cropperModal.show();
    }

    function dataUrlToBlob(dataUrl) {
        const [header, encoded] = dataUrl.split(',');
        const mime = header.match(/:(.*?);/)[1];
        const bytes = atob(encoded);
        const output = new Uint8Array(bytes.length);
        for (let index = 0; index < bytes.length; index++) output[index] = bytes.charCodeAt(index);
        return new Blob([output], { type: mime });
    }

    async function uploadBlob(blob, filename) {
        const formData = new FormData();
        formData.append('image', blob, filename);
        const response = await fetch('/image-uploads', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: formData,
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || data.error || 'Image upload failed.');
        return data.source_path || data.path;
    }

    cropperModalElement.addEventListener('shown.dream.modal', () => {
        cropper?.destroy();
        cropper = new Cropper(imageToCrop, { viewMode: 1, background: false });
    });

    cropperModalElement.addEventListener('hidden.dream.modal', () => {
        cropper?.destroy();
        cropper = null;
    });

    document.getElementById('useFullImageBtn').addEventListener('click', async () => {
        const button = document.getElementById('useFullImageBtn');
        button.disabled = true;
        try {
            const path = imageToCrop.src.startsWith('data:image')
                ? await uploadBlob(dataUrlToBlob(imageToCrop.src), 'full-image.png')
                : imageToCrop.src;
            selectImage(path);
            cropperModal.hide();
        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('confirmCropBtn').addEventListener('click', () => {
        if (!cropper) return;
        const button = document.getElementById('confirmCropBtn');
        button.disabled = true;
        cropper.getCroppedCanvas({ maxWidth: 2048, maxHeight: 2048 }).toBlob(async (blob) => {
            try {
                selectImage(await uploadBlob(blob, 'cropped-image.png'));
                cropperModal.hide();
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        }, 'image/png');
    });

    async function loadHistory(page = 1) {
        const source = document.getElementById('historySource').value;
        const sort = document.getElementById('historySort').value;
        const perPage = document.getElementById('historyPerPage').value;
        const container = document.getElementById('historyImagesContainer');
        const endpoint = source === 'uploads'
            ? '/image-uploads?page=' + page + '&sort=' + sort + '&perPage=' + perPage
            : '/kontext-basic/render-history?page=' + page + '&sort=' + sort + '&perPage=' + perPage;
        container.innerHTML = '<div class="p-5 text-center">Loading images…</div>';
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const images = source === 'uploads' ? data.images : data.data;
        container.innerHTML = '';
        images.forEach((item) => {
            const path = source === 'uploads' ? item.path : item.image_url;
            const thumbnail = source === 'uploads' ? item.path : item.thumbnail_url;
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'history-image-card rounded-xl border-2 border-transparent p-1';
            card.dataset.path = path;
            const image = document.createElement('img');
            image.src = thumbnail;
            image.alt = source === 'uploads' ? item.name : item.generated_prompt;
            image.className = 'h-36 w-full rounded-lg object-cover';
            card.appendChild(image);
            const column = document.createElement('div');
            column.className = 'col-lg-2 col-md-3 col-sm-4 mb-3';
            column.appendChild(card);
            container.appendChild(column);
        });
        renderPagination(source === 'uploads' ? data.pagination : {
            current_page: data.current_page,
            total_pages: data.last_page,
        });
    }

    function renderPagination(data) {
        const container = document.getElementById('historyPagination');
        container.innerHTML = '';
        if (!data || data.total_pages <= 1) return;
        for (let page = 1; page <= data.total_pages; page++) {
            const item = document.createElement('li');
            item.className = 'page-item ' + (page === data.current_page ? 'active' : '');
            item.innerHTML = '<a class="page-link" href="#" data-page="' + page + '">' + page + '</a>';
            container.appendChild(item);
        }
    }

    selectButton.addEventListener('click', () => {
        loadHistory();
        historyModal.show();
    });
    document.getElementById('removeSingleImage').addEventListener('click', () => {
        pathInput.value = '';
        previewImage.src = '';
        preview.classList.add('hidden');
        selectButton.textContent = 'Select or upload image';
        window.dispatchEvent(new CustomEvent('dreamcover:image-selected', { detail: { path: '' } }));
    });
    document.getElementById('uploadNewImageBtn').addEventListener('click', () => document.getElementById('newImageUploadInput').click());
    document.getElementById('newImageUploadInput').addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            historyModal.hide();
            openCropper(reader.result);
        };
        reader.readAsDataURL(file);
        event.target.value = '';
    });
    document.getElementById('historyImagesContainer').addEventListener('click', (event) => {
        const card = event.target.closest('.history-image-card');
        if (!card) return;
        document.querySelectorAll('.history-image-card.selected').forEach((item) => item.classList.remove('selected'));
        card.classList.add('selected');
    });
    document.getElementById('addSelectedHistoryImageBtn').addEventListener('click', () => {
        const selected = document.querySelector('.history-image-card.selected');
        if (!selected) return alert('Please select an image.');
        historyModal.hide();
        openCropper(selected.dataset.path);
    });
    document.getElementById('historyPagination').addEventListener('click', (event) => {
        const link = event.target.closest('[data-page]');
        if (!link) return;
        event.preventDefault();
        loadHistory(Number(link.dataset.page));
    });
    ['historySource', 'historySort', 'historyPerPage'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => loadHistory());
    });
});
