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
<div id="photoshopApp" class="ps-app" data-layerize-store-url="{{ route('layers.store') }}" data-layerize-history-url="{{ route('layers.history') }}">
    <header class="ps-menubar">
        <a class="ps-brand" href="{{ route('home') }}" title="Back to DreamCover"><img src="{{ asset('images/favicon-32x32.png') }}" alt="DreamCover"></a>
        <nav id="menuBar" aria-label="Application menus"></nav>
        <div class="ps-save-control"><span id="saveStatus" class="ps-save-status">No document</span><button id="saveButton" type="button" data-action="saveProject" hidden>Save PSD</button></div>
    </header>

    <section class="ps-options" aria-label="Tool options">
        <span id="activeToolIcon" class="ps-options-tool"><img class="ps-icon" src="{{ asset('ps-icons/tools-move.png') }}" alt=""></span><span id="activeToolName">Move Tool</span><span id="layerizeProgress" class="ps-layerize-progress" hidden><span class="ps-layerize-spinner"></span><span>Layerizing selected layers…</span></span>
        <span class="ps-divider"></span>
        <label><input id="autoSelect" type="checkbox" checked> Auto-select</label>

        <label><input id="transformControls" type="checkbox" checked> Show transform controls</label>
        <span class="ps-divider"></span>
        <div class="ps-toolbar-align"><span><button type="button" data-align-layer="left" title="Align Left Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-h0.png') }}" alt=""></button><button type="button" data-align-layer="hcenter" title="Center Horizontally"><img class="ps-icon" src="{{ asset('ps-icons/align-h1.png') }}" alt=""></button><button type="button" data-align-layer="right" title="Align Right Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-h2.png') }}" alt=""></button><button type="button" data-align-layer="hgap" title="Distribute Horizontal Gaps"><img class="ps-icon" src="{{ asset('ps-icons/align-hG.png') }}" alt=""></button></span><span><button type="button" data-align-layer="top" title="Align Top Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-v0.png') }}" alt=""></button><button type="button" data-align-layer="vcenter" title="Center Vertically"><img class="ps-icon" src="{{ asset('ps-icons/align-v1.png') }}" alt=""></button><button type="button" data-align-layer="bottom" title="Align Bottom Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-v2.png') }}" alt=""></button><button type="button" data-align-layer="vgap" title="Distribute Vertical Gaps"><img class="ps-icon" src="{{ asset('ps-icons/align-vG.png') }}" alt=""></button></span><button type="button" class="ps-align-more" title="More alignment options">…</button><button type="button" class="ps-align-grid" data-align-layer="grid" title="Align to a Grid" aria-label="Align to a Grid">▦</button></div>
    </section>

    <section id="documentTabs" class="ps-tabs" aria-label="Open projects"></section>

    <main class="ps-workspace">
        <aside id="toolsPanel" class="ps-tools" aria-label="Tools"></aside>
        <section id="stageViewport" class="ps-stage-viewport">
            <div class="ps-ruler ps-ruler-x"></div><div class="ps-ruler ps-ruler-y"></div>
            <div id="emptyState" class="ps-empty-state"><div class="ps-empty-mark">Ps</div><h1>Your local image workspace</h1><p>Create a document or open a PSD or image from your computer.</p><button type="button" data-action="newProject">New document</button><button type="button" data-action="openLocal">Open PSD or image</button></div>
            <div id="canvasShell" class="ps-canvas-shell" hidden><div id="canvas" class="ps-canvas"><div id="selectionBox" class="ps-selection" hidden></div></div></div>
        </section>
        <aside class="ps-panels">
            <div class="ps-panel-tabs" role="tablist"><button type="button" class="active" data-panel-tab="layers"><img class="ps-icon" src="{{ asset('ps-icons/panels-layers.png') }}" alt="">Layers</button><button type="button" data-panel-tab="properties"><img class="ps-icon" src="{{ asset('ps-icons/panels-properties.png') }}" alt="">Properties</button><button type="button" data-panel-tab="history"><img class="ps-icon" src="{{ asset('ps-icons/panels-history.png') }}" alt="">History</button></div>
            <section class="ps-panel-view active" data-panel-view="layers">
                <div class="ps-layer-filter"><img class="ps-icon" src="{{ asset('ps-icons/tools-zoom.png') }}" alt=""><span>Kind</span><span class="ps-layer-filter-icons"><img class="ps-icon" src="{{ asset('ps-icons/pix_layer.png') }}" alt="Pixel layer"><img class="ps-icon" src="{{ asset('ps-icons/lrs-adj.png') }}" alt="Adjustment layer"><img class="ps-icon" src="{{ asset('ps-icons/tools-htype.png') }}" alt="Type layer"><img class="ps-icon" src="{{ asset('ps-icons/shape_layer.png') }}" alt="Shape layer"></span></div>
                <div class="ps-layer-controls">
                    <select id="layerBlendMode" aria-label="Blend mode">
                        <option value="normal">Normal</option><option value="dissolve">Dissolve</option><option disabled></option>
                        <option value="darken">Darken</option><option value="multiply">Multiply</option><option value="color burn">Color Burn</option><option value="linear burn">Linear Burn</option><option value="darker color">Darker Color</option><option disabled></option>
                        <option value="lighten">Lighten</option><option value="screen">Screen</option><option value="color dodge">Color Dodge</option><option value="linear dodge">Linear Dodge</option><option value="lighter color">Lighter Color</option><option disabled></option>
                        <option value="overlay">Overlay</option><option value="soft light">Soft Light</option><option value="hard light">Hard Light</option><option value="vivid light">Vivid Light</option><option value="linear light">Linear Light</option><option value="pin light">Pin Light</option><option value="hard mix">Hard Mix</option><option disabled></option>
                        <option value="difference">Difference</option><option value="exclusion">Exclusion</option><option value="subtract">Subtract</option><option value="divide">Divide</option><option disabled></option>
                        <option value="hue">Hue</option><option value="saturation">Saturation</option><option value="color">Color</option><option value="luminosity">Luminosity</option>
                    </select>
                    <div id="opacityControl" class="ps-opacity-control"><button id="opacityTrigger" type="button">Opacity:</button><input id="layerOpacity" type="number" min="0" max="100" value="100"><span>%</span><div id="opacityPopover" class="ps-opacity-popover" hidden><input id="layerOpacitySlider" type="range" min="0" max="100" value="100" aria-label="Layer opacity"></div></div>
                </div>
                <div class="ps-lock-row">Lock: <img class="ps-icon" src="{{ asset('ps-icons/lrs-lock.png') }}" alt="Lock layer"></div>
                <div id="layersPanel" class="ps-layer-list"></div>
                <div class="ps-panel-footer"><button type="button" title="Layer effects"><img class="ps-icon" src="{{ asset('ps-icons/lrs-fx.png') }}" alt=""></button><button type="button" title="Add mask"><img class="ps-icon" src="{{ asset('ps-icons/lrs-mask.png') }}" alt=""></button><button type="button" data-action="newLayerFolder" title="New layer group"><img class="ps-icon" src="{{ asset('ps-icons/lrs-folder.png') }}" alt=""></button><button type="button" data-action="newBlankLayer" title="New layer"><img class="ps-icon" src="{{ asset('ps-icons/lrs-newlayer.png') }}" alt=""></button><button type="button" data-action="deleteLayers" title="Delete selected layers"><img class="ps-icon" src="{{ asset('ps-icons/lrs-bin.png') }}" alt=""></button></div>
            </section>
            <section class="ps-panel-view" data-panel-view="properties"><div id="propertiesPanel" class="ps-properties-panel"></div></section>
            <section class="ps-panel-view" data-panel-view="history"><div id="historyPanel" class="ps-history-panel"></div></section>
        </aside>
    </main>

    <input id="localFileInput" type="file" accept=".psd,image/*" hidden>
    <input id="placeImageInput" type="file" accept="image/*" hidden>
    <div id="toast" class="ps-toast" role="status" hidden></div>

    <dialog id="layerizeHistoryDialog" class="ps-dialog ps-layerize-dialog">
        <header><h2>Layerize history</h2><button type="button" data-close-layerize-history aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
        <div id="layerizeHistoryList" class="ps-layerize-history"><div class="ps-panel-empty">Loading Layerize history…</div></div>
    </dialog>
    <dialog id="deleteLayersDialog" class="ps-dialog ps-confirm-dialog">
        <form method="dialog" id="deleteLayersForm">
            <header><h2>Delete layers?</h2><button value="cancel" aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
            <div class="ps-dialog-body"><p id="deleteLayersMessage" class="ps-dialog-note">Delete the selected layers?</p></div>
            <footer><button value="cancel" class="secondary">Cancel</button><button value="delete" class="danger">Delete</button></footer>
        </form>
    </dialog>
    <dialog id="imageSizeDialog" class="ps-dialog ps-size-dialog">
        <form method="dialog" id="imageSizeForm">
            <header><h2>Image Size</h2><button value="cancel" aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
            <div class="ps-size-dialog-layout">
                <section class="ps-size-preview"><img id="imageSizePreview" alt="Document preview"><strong id="imageSizePercent">Preview</strong></section>
                <section class="ps-size-fields">
                    <p id="imageSizeSummary" class="ps-size-summary"></p>
                    <div class="ps-size-dimensions"><span>Dimensions:</span><strong id="imageSizeDimensions"></strong></div>
                    <label>Width <span><input id="imageSizeWidth" type="number" min="1" max="8192" required><select aria-label="Width unit"><option>Pixels</option></select></span></label>
                    <label class="ps-aspect-lock"><input id="imageSizeLock" type="checkbox" checked><img class="ps-icon" src="{{ asset('ps-icons/lrs-chain.png') }}" alt=""><span>Constrain proportions</span></label>
                    <label>Height <span><input id="imageSizeHeight" type="number" min="1" max="8192" required><select aria-label="Height unit"><option>Pixels</option></select></span></label>
                    <label>Resolution <span><input type="number" value="72" disabled><select aria-label="Resolution unit"><option>Pixels/Inch</option></select></span></label>
                    <label class="ps-resample"><input type="checkbox" checked disabled> Resample: Automatic</label>
                </section>
            </div>
            <footer><button value="confirm">OK</button><button value="cancel" class="secondary">Cancel</button></footer>
        </form>
    </dialog>

    <dialog id="canvasSizeDialog" class="ps-dialog ps-canvas-size-dialog">
        <form method="dialog" id="canvasSizeForm">
            <header><h2>Canvas Size</h2><button value="cancel" aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
            <div class="ps-dialog-body">
                <section class="ps-current-size"><strong>Current Size</strong><span>Width <b id="canvasCurrentWidth"></b> px</span><span>Height <b id="canvasCurrentHeight"></b> px</span></section>
                <section class="ps-new-canvas-size"><strong>New Size</strong><label>Width <span><input id="canvasSizeWidth" type="number" required><select aria-label="Canvas width unit"><option>Pixels</option></select></span></label><label>Height <span><input id="canvasSizeHeight" type="number" required><select aria-label="Canvas height unit"><option>Pixels</option></select></span></label><label class="ps-relative-size"><input id="canvasSizeRelative" type="checkbox"> Relative to current dimensions</label></section>
                <section class="ps-anchor-section"><span>Anchor</span><div id="canvasAnchorGrid" class="ps-anchor-grid" role="radiogroup" aria-label="Canvas anchor"><button type="button" data-canvas-anchor="top-left" title="Top left">↖</button><button type="button" data-canvas-anchor="top" title="Top">↑</button><button type="button" data-canvas-anchor="top-right" title="Top right">↗</button><button type="button" data-canvas-anchor="left" title="Left">←</button><button type="button" data-canvas-anchor="center" title="Center" class="active">●</button><button type="button" data-canvas-anchor="right" title="Right">→</button><button type="button" data-canvas-anchor="bottom-left" title="Bottom left">↙</button><button type="button" data-canvas-anchor="bottom" title="Bottom">↓</button><button type="button" data-canvas-anchor="bottom-right" title="Bottom right">↘</button></div></section>
            </div>
            <footer><button value="confirm">OK</button><button value="cancel" class="secondary">Cancel</button></footer>
        </form>
    </dialog>
    <dialog id="newProjectDialog" class="ps-dialog ps-new-dialog">
        <form method="dialog" id="newProjectForm">
            <header><h2>New document</h2><button value="cancel" aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
            <div class="ps-new-layout">
                <section class="ps-template-browser" aria-label="Document templates">
                    <nav id="templateCategories" class="ps-template-categories" aria-label="Template categories"></nav>
                    <div id="templateGrid" class="ps-template-grid"></div>
                </section>
                <aside class="ps-document-settings">
                    <label>Name<input id="newProjectName" value="New Project" maxlength="120" required></label>
                    <div class="ps-dimension-grid">
                        <label>Width<input id="projectWidth" type="number" min="0.01" step="any" value="1280" required></label>
                        <button id="swapDimensions" type="button" title="Swap width and height" aria-label="Swap width and height"><img class="ps-icon" src="{{ asset('ps-icons/split-vh.png') }}" alt=""></button>
                        <label>Height<input id="projectHeight" type="number" min="0.01" step="any" value="720" required></label>
                        <label class="ps-unit-label"><span class="sr-only">Units</span><select id="documentUnit" aria-label="Document units"><option value="px">Pixels</option><option value="in">Inches</option><option value="mm">Millimeters</option></select></label>
                    </div>
                    <div class="ps-dpi-grid"><label>DPI<input id="documentDpi" type="number" min="1" max="2400" value="72" required></label><label><span class="sr-only">DPI units</span><select aria-label="DPI units"><option>Pixels / Inch</option></select></label></div>
                    <label>Background<select id="documentBackground"><option value="transparent">Transparent</option></select></label>
                    <div class="ps-color-grid"><label>Mode<select><option>RGB</option></select></label><label><span class="sr-only">Bit depth</span><select aria-label="Bit depth"><option>8 bit</option></select></label><label>Profile<select><option>sRGB</option></select></label></div>
                    <button id="createProjectButton" type="submit" value="default" class="ps-create-document">Create</button>
                </aside>
            </div>
        </form>
    </dialog>

</div>
</body>
</html>
