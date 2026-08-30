@extends('layouts.app')

@section('title', 'Image Editor Pro')

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/cropperjs/1.6.1/cropper.min.css') }}">
@endsection

@section('content')
<div class="container py-4">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Image Editor Pro</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">Interactive editing — Seedream 5.0 Pro now live on fal.</p>
    </div>

    <div id="proEditorGrid" class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(22rem,.65fr)]">
        <section id="proImageSection" class="card">
            <div class="card-header">1. Choose one image and mark edit areas</div>
            <div class="card-body space-y-4">
                @include('partials.single-image-picker')
                <div id="proCanvasPanel" class="hidden space-y-3">
                    <p class="text-sm text-slate-500">Click “Add area”, then drag a rectangle over the image. Each area receives its own color prompt.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="addAreaButton" class="btn btn-primary">Add area</button>
                        <button type="button" id="clearAreasButton" class="btn btn-outline-danger">Clear areas</button>
                        <button type="button" id="replaceProImage" class="btn btn-outline-primary">Replace image</button>
                        <button type="button" id="removeProImage" class="btn btn-outline-danger">Remove image</button>
                    </div>
                    <div class="overflow-auto rounded-2xl bg-slate-950/90 p-3">
                        <canvas id="proEditCanvas" class="mx-auto max-h-[65vh] max-w-full cursor-crosshair"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section id="proPromptSection" class="card self-start lg:sticky lg:top-24">
            <div class="card-header">2. Describe the edits</div>
            <div class="card-body space-y-5">
                <div>
                    <label for="generalPrompt" class="form-label">General prompt</label>
                    <textarea id="generalPrompt" class="form-control" rows="4" placeholder="Overall style, lighting, or instructions…"></textarea>
                </div>
                <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <span>
                        <strong class="block text-sm">General prompt position</strong>
                        <span id="generalPositionLabel" class="text-xs text-slate-500">Beginning</span>
                    </span>
                    <input id="generalPromptAtEnd" type="checkbox" class="form-check-input">
                </label>
                <div id="areaPrompts" class="space-y-3"></div>
                <button type="button" id="generateProEdit" class="btn btn-success w-full" disabled>
                    Generate with Seedream Pro
                </button>
                <p id="proEditStatus" class="text-sm text-slate-500" aria-live="polite"></p>
            </div>
        </section>
    </div>

    <section id="proEditResultCard" class="card mt-6 hidden">
        <div class="card-header">Result</div>
        <div class="card-body text-center">
            <img id="proEditResult" src="" alt="Seedream edited result" class="mx-auto max-h-[75vh] rounded-xl">
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
window.seedreamProConfig = {
    generateUrl: @json(route('image-editor-pro.generate')),
    statusBaseUrl: @json(url('/image-editor-pro/status')),
    proxyUrl: @json(route('image-editor.proxy')),
    uploadUrl: @json(route('image-uploads.store')),
    csrfToken: @json(csrf_token()),
};
</script>
<script src="{{ asset('vendor/cropperjs/1.6.1/cropper.min.js') }}"></script>
<script src="{{ asset('js/single-image-picker.js') }}?v={{ filemtime(public_path('js/single-image-picker.js')) }}"></script>
<script src="{{ asset('js/image-editor-pro.js') }}?v={{ filemtime(public_path('js/image-editor-pro.js')) }}"></script>
@endsection
