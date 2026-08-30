@extends('layouts.app')

@section('title', 'Layerize')

@section('styles')
<link rel="stylesheet" href="{{ asset('vendor/cropperjs/1.6.1/cropper.min.css') }}">
@endsection

@section('content')
<div class="container py-4">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Layerize</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">Separate one image into a base image and independently downloadable layers.</p>
    </div>

    <section class="card">
        <div class="card-header">New Layerize job</div>
        <div class="card-body space-y-4">
            @include('partials.single-image-picker')
            <p class="text-sm text-slate-500">Uses <code>bytedance/seedream/v5/pro/layerize</code>. No prompt is required.</p>
            <button type="button" id="startLayerize" class="btn btn-success" disabled>Layerize image</button>
            <p id="layerizeStatus" class="text-sm text-slate-500" aria-live="polite"></p>
        </div>
    </section>

    <section class="card mt-6">
        <div class="card-header">Layerize history</div>
        <div class="card-body">
            @if($layers->isEmpty())
                <p class="py-8 text-center text-slate-500">No Layerize jobs yet.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($layers as $layer)
                        @php($preview = data_get($layer->images, '0.url', $layer->input_image))
                        <a href="{{ route('layers.show', $layer) }}" class="card group">
                            <img src="{{ $preview }}" alt="Layerize job {{ $layer->id }}" class="h-52 w-full object-cover">
                            <div class="card-body">
                                <div class="flex items-center justify-between gap-2">
                                    <strong>Job #{{ $layer->id }}</strong>
                                    <span class="badge {{ $layer->status === 2 ? 'bg-success' : ($layer->status === 4 ? 'bg-danger' : 'bg-warning') }}">
                                        {{ match($layer->status) { 2 => 'Ready', 4 => 'Failed', 1 => 'Processing', default => 'Queued' } }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">{{ $layer->created_at->format('Y-m-d H:i') }}</p>
                                @if($layer->status === 2)
                                    <p class="mt-1 text-sm">{{ count($layer->layers ?? []) }} downloadable files</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-6">{{ $layers->links() }}</div>
            @endif
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
window.layerizeConfig = {
    storeUrl: @json(route('layers.store')),
    csrfToken: @json(csrf_token()),
};
</script>
<script src="{{ asset('vendor/cropperjs/1.6.1/cropper.min.js') }}"></script>
<script src="{{ asset('js/single-image-picker.js') }}?v={{ filemtime(public_path('js/single-image-picker.js')) }}"></script>
<script src="{{ asset('js/layerize.js') }}?v={{ filemtime(public_path('js/layerize.js')) }}"></script>
@endsection
