@extends('layouts.app')

@section('title', 'Layerize Job #'.$layer->id)

@section('content')
<div class="container py-4">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold">Layerize Job #{{ $layer->id }}</h1>
            <p class="mt-1 text-slate-500">{{ $layer->created_at->format('Y-m-d H:i') }}</p>
        </div>
        <a href="{{ route('layers.index') }}" class="btn btn-outline-secondary">Back to Layerize</a>
    </div>

    <div id="layerPending" class="alert alert-info {{ in_array($layer->status, [0, 1], true) ? '' : 'hidden' }}">
        Layerize is queued or processing. This page will update automatically.
    </div>
    <div id="layerFailed" class="alert alert-danger {{ $layer->status === 4 ? '' : 'hidden' }}">
        {{ $layer->error ?: 'Layerize failed.' }}
    </div>

    <section id="layerResults" class="{{ $layer->status === 2 ? '' : 'hidden' }}">
        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <a href="{{ route('layers.download-all', $layer) }}" class="btn btn-primary">Download all as ZIP</a>
            <button id="downloadLayerPsd" type="button" class="btn btn-outline-primary">Download as PSD</button>
            <p id="layerExportStatus" class="text-sm text-slate-500" aria-live="polite"></p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($layer->layers ?? [] as $index => $item)
                @php($image = data_get($item, 'image', []))
                <article class="card">
                    <div class="flex min-h-64 items-center justify-center bg-[linear-gradient(45deg,#e2e8f0_25%,transparent_25%),linear-gradient(-45deg,#e2e8f0_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#e2e8f0_75%),linear-gradient(-45deg,transparent_75%,#e2e8f0_75%)] bg-[length:20px_20px]">
                        <img src="{{ data_get($image, 'url') }}" alt="{{ data_get($item, 'name', $index === 0 ? 'Base image' : 'Layer '.$index) }}" class="max-h-80 w-full object-contain">
                    </div>
                    <div class="card-body">
                        <h2 class="font-bold">{{ data_get($item, 'name', $index === 0 ? 'Base image' : 'Layer '.$index) }}</h2>
                        @if(data_get($item, 'description'))
                            <p class="mt-2 text-sm text-slate-500">{{ data_get($item, 'description') }}</p>
                        @endif
                        <div class="mt-2 text-xs text-slate-500">
                            Z-index {{ data_get($item, 'z_index', $index) }}
                            @if(data_get($item, 'bounding_box.absolute'))
                                · Bounds {{ implode(', ', data_get($item, 'bounding_box.absolute')) }}
                            @endif
                        </div>
                        <a href="{{ route('layers.download', [$layer, $index]) }}" class="btn btn-primary mt-4 w-full">Download layer</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection

@section('scripts')
@if($layer->status === 2)
<script>
window.layerExportConfig = {
    jobId: {{ $layer->id }},
    layers: @json($exportLayers),
};
</script>
@vite('resources/js/layerize-export.js')
@endif
@if(in_array($layer->status, [0, 1], true))
<script>
window.layerShowConfig = {
    statusUrl: @json(route('layers.status', $layer)),
};
</script>
<script src="{{ asset('js/layerize-show.js') }}?v={{ filemtime(public_path('js/layerize-show.js')) }}"></script>
@endif
@endsection
