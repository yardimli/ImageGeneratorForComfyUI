<div id="singleImagePickerPanel" class="rounded-2xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
    <div id="singleImagePreview" class="hidden">
        <img id="singleImagePreviewImage" src="" alt="Selected input" class="max-h-72 rounded-xl object-contain">
        <button type="button" id="removeSingleImage" class="btn btn-outline-danger btn-sm mt-3">Remove image</button>
    </div>
    <input type="hidden" id="singleImagePath">
    <button type="button" id="selectSingleImage" class="btn btn-primary">Select or upload image</button>
</div>

@include('story.partials.cropper-modal')
@include('story.partials.history-modal')
