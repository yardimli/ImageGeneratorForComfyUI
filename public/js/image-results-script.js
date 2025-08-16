let imageModal;


function openImageModal(filename) {
	const modalImage = document.getElementById('modalImage');
	modalImage.src = `${filename}`;
	imageModal.show();
}

document.addEventListener('DOMContentLoaded', function () {
	imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
});
