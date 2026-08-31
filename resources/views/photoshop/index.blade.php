<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Photoshop · {{ config('app.name', 'DreamCover') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32x32.png') }}">
    @vite('resources/js/photoshop.js')
</head>
<body class="ps-body">
<div id="photoshopApp" class="ps-app" data-projects-url="{{ route('photoshop.projects') }}" data-project-store-url="{{ route('photoshop.projects.store') }}" data-project-base-url="{{ url('/photoshop/projects') }}" data-layer-base-url="{{ url('/photoshop/layers') }}">
    <header class="ps-menubar">
        <a class="ps-brand" href="{{ route('home') }}" title="Back to DreamCover"><img src="{{ asset('images/favicon-32x32.png') }}" alt="DreamCover"></a>
        <nav id="menuBar" aria-label="Application menus"></nav>
        <span id="saveStatus" class="ps-save-status">All changes saved</span>
    </header>

    <section class="ps-options" aria-label="Tool options">
        <span id="activeToolIcon" class="ps-options-tool">↖</span><span id="activeToolName">Move</span>
        <span class="ps-divider"></span>
        <label><input id="autoSelect" type="checkbox" checked> Auto-select</label>
        <select aria-label="Auto select target"><option>Layer</option></select>
        <label><input id="transformControls" type="checkbox" checked> Show transform controls</label>
        <span class="ps-divider"></span>
        <button type="button" title="Align left">◧</button><button type="button" title="Align center">▣</button><button type="button" title="Align right">◨</button>
    </section>

    <section id="documentTabs" class="ps-tabs" aria-label="Open projects"></section>

    <main class="ps-workspace">
        <aside id="toolsPanel" class="ps-tools" aria-label="Tools"></aside>
        <section id="stageViewport" class="ps-stage-viewport">
            <div class="ps-ruler ps-ruler-x"></div><div class="ps-ruler ps-ruler-y"></div>
            <div id="emptyState" class="ps-empty-state"><div class="ps-empty-mark">Ps</div><h1>Your image workspace</h1><p>Create a project or open an image to begin.</p><button type="button" data-action="newProject">New project</button><button type="button" data-action="openLocal">Open from local</button></div>
            <div id="canvasShell" class="ps-canvas-shell" hidden><div id="canvas" class="ps-canvas"><div id="selectionBox" class="ps-selection" hidden></div></div></div>
        </section>
        <aside class="ps-panels">
            <div class="ps-panel-tabs" role="tablist"><button type="button" class="active" data-panel-tab="layers">Layers</button><button type="button" data-panel-tab="properties">Properties</button><button type="button" data-panel-tab="history">History</button></div>
            <section class="ps-panel-view active" data-panel-view="layers">
                <div class="ps-layer-filter">⌕ <span>Kind</span><span class="ps-layer-filter-icons">▧ ◐ T ▣</span></div>
                <div class="ps-layer-controls"><select aria-label="Blend mode"><option>Normal</option></select><label>Opacity: <input id="layerOpacity" type="number" min="0" max="100" value="100">%</label></div>
                <div class="ps-lock-row">Lock: ◫ ◩ ✥ ▣ 🔒</div>
                <div id="layersPanel" class="ps-layer-list"></div>
                <div class="ps-panel-footer"><button type="button" title="Layer effects">fx</button><button type="button" title="Add mask">▣</button><button type="button" data-action="newBlankLayer" title="New layer">＋</button></div>
            </section>
            <section class="ps-panel-view" data-panel-view="properties"><div id="propertiesPanel" class="ps-properties-panel"></div></section>
            <section class="ps-panel-view" data-panel-view="history"><div id="historyPanel" class="ps-history-panel"></div></section>
        </aside>
    </main>

    <input id="localFileInput" type="file" accept="image/*" hidden>
    <div id="toast" class="ps-toast" role="status" hidden></div>

    <dialog id="newProjectDialog" class="ps-dialog">
        <form method="dialog" id="newProjectForm">
            <header><h2>New document</h2><button value="cancel" aria-label="Close">×</button></header>
            <div class="ps-dialog-body">
                <label>Name<input id="newProjectName" value="Untitled-1" maxlength="120" required></label>
                <label>Preset<select id="sizePreset"></select></label>
                <div class="ps-size-grid"><label>Width<input id="projectWidth" type="number" min="1" max="8192" value="1024" required></label><label>Height<input id="projectHeight" type="number" min="1" max="8192" value="1024" required></label></div>
                <p class="ps-dialog-note">Pixels · RGB color · transparent background</p>
            </div>
            <footer><button value="cancel" class="secondary">Cancel</button><button id="createProjectButton" value="default">Create</button></footer>
        </form>
    </dialog>

    <dialog id="openProjectDialog" class="ps-dialog ps-open-dialog">
        <form method="dialog"><header><h2>Open project</h2><button value="cancel" aria-label="Close">×</button></header><div id="projectLibrary" class="ps-project-library"></div><footer><button value="cancel" class="secondary">Close</button></footer></form>
    </dialog>
</div>
</body>
</html>
