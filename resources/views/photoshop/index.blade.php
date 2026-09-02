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
<div id="photoshopApp" class="ps-app" data-layerize-store-url="{{ route('layers.store') }}" data-layerize-history-url="{{ route('layers.history') }}" data-gen-ai-store-url="{{ route('photoshop.gen-ai.store') }}" data-gen-ai-history-url="{{ route('photoshop.gen-ai.history') }}" data-image-to-image-store-url="{{ route('photoshop.image-to-image.store') }}" data-image-to-image-history-url="{{ route('photoshop.image-to-image.history') }}">
    <header class="ps-menubar">
        <a class="ps-brand" href="{{ route('home') }}" title="Back to DreamCover"><img src="{{ asset('images/favicon-32x32.png') }}" alt="DreamCover"></a>
        <nav id="menuBar" aria-label="Application menus"></nav>
        <div class="ps-save-control"><span id="saveStatus" class="ps-save-status">No document</span><button id="saveButton" type="button" data-action="saveProject" hidden>Save PSD</button></div>
    </header>

    <section class="ps-options" aria-label="Tool options">
        <span id="activeToolIcon" class="ps-options-tool"><img class="ps-icon" src="{{ asset('ps-icons/tools-move.png') }}" alt=""></span><span id="activeToolName">Move Tool</span><span id="layerizeProgress" class="ps-layerize-progress" hidden><span class="ps-layerize-spinner"></span><span>Layerizing selected layers…</span></span><span id="genAiProgress" class="ps-layerize-progress" hidden><span class="ps-layerize-spinner"></span><span>Generating selected edits…</span></span><span id="imageToImageProgress" class="ps-layerize-progress" hidden><span class="ps-layerize-spinner"></span><span>Generating image to image…</span></span>
        <span class="ps-divider"></span>
        <label><input id="autoSelect" type="checkbox" checked> Auto-select</label>

        <label><input id="transformControls" type="checkbox" checked> Show transform controls</label>
        <span class="ps-divider"></span>
        <div class="ps-toolbar-align"><span><button type="button" data-align-layer="left" title="Align Left Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-h0.png') }}" alt=""></button><button type="button" data-align-layer="hcenter" title="Center Horizontally"><img class="ps-icon" src="{{ asset('ps-icons/align-h1.png') }}" alt=""></button><button type="button" data-align-layer="right" title="Align Right Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-h2.png') }}" alt=""></button><button type="button" data-align-layer="hgap" title="Distribute Horizontal Gaps"><img class="ps-icon" src="{{ asset('ps-icons/align-hG.png') }}" alt=""></button></span><span><button type="button" data-align-layer="top" title="Align Top Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-v0.png') }}" alt=""></button><button type="button" data-align-layer="vcenter" title="Center Vertically"><img class="ps-icon" src="{{ asset('ps-icons/align-v1.png') }}" alt=""></button><button type="button" data-align-layer="bottom" title="Align Bottom Edges"><img class="ps-icon" src="{{ asset('ps-icons/align-v2.png') }}" alt=""></button><button type="button" data-align-layer="vgap" title="Distribute Vertical Gaps"><img class="ps-icon" src="{{ asset('ps-icons/align-vG.png') }}" alt=""></button></span><button type="button" class="ps-align-more" title="More alignment options">…</button><button type="button" class="ps-align-grid" data-align-layer="grid" title="Align to a Grid" aria-label="Align to a Grid">▦</button></div>
        <div id="genAiRegionOptions" class="ps-gen-ai-region-options" hidden><strong>Gen AI regions</strong><span id="genAiRegionCount">0 rectangles</span><select id="genAiRegionSelect" aria-label="Selected edit rectangle"></select><button id="deleteGenAiRegion" type="button" title="Delete selected rectangle">Delete</button><button id="confirmGenAiRegions" class="confirm" type="button" title="Continue with these regions" aria-label="Continue">✓</button><button id="cancelGenAiRegions" type="button" title="Cancel Gen AI selection" aria-label="Cancel">✕</button></div>
    </section>

    <section id="documentTabs" class="ps-tabs" aria-label="Open projects"></section>

    <main class="ps-workspace">
        <aside id="toolsPanel" class="ps-tools" aria-label="Tools"></aside>
        <div class="ps-stage-column">
            <div class="ps-stage-frame">
                <section id="stageViewport" class="ps-stage-viewport">
                    <div id="emptyState" class="ps-empty-state"><div class="ps-empty-mark">Ps</div><h1>Your local image workspace</h1><p>Create a document or open a PSD or image from your computer.</p><button type="button" data-action="newProject">New document</button><button type="button" data-action="openLocal">Open PSD or image</button></div>
                    <div id="canvasShell" class="ps-canvas-shell" hidden><div id="canvas" class="ps-canvas"><div id="selectionBox" class="ps-selection" hidden></div><svg id="lassoSelection" class="ps-lasso-selection" hidden aria-hidden="true"><path class="ps-lasso-outline"></path><path class="ps-lasso-ants"></path></svg></div></div>
                    <div id="selectionDimensionsTooltip" class="ps-selection-dimensions" hidden><span>W: <strong data-selection-width>0 px</strong></span><span>H: <strong data-selection-height>0 px</strong></span></div>
                </section>
                <div class="ps-ruler ps-ruler-x"></div><div class="ps-ruler ps-ruler-y"></div>
            </div>
            <footer class="ps-stage-status"><label><input id="zoomStatusInput" value="100.00" inputmode="decimal" aria-label="Zoom percentage"><span>%</span></label><span id="documentStatusSize">No document</span></footer>
        </div>
        <aside class="ps-panels ps-right-dock">
            <div id="panelStack" class="ps-panel-stack">
                <section class="ps-dock-panel ps-history-dock">
                    <header class="ps-dock-header"><strong>History</strong><button type="button" title="History panel menu" aria-label="History panel menu">☰</button></header>
                    <div id="historyPanel" class="ps-history-panel"></div>
                </section>
                <section class="ps-dock-panel ps-layers-dock">
                    <header class="ps-dock-header"><strong>Layers</strong><button type="button" title="Layers panel menu" aria-label="Layers panel menu">☰</button></header>
                    <div class="ps-panel-view active" data-panel-view="layers">
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
                    </div>
                </section>
            </div>
            <section id="inspectorDock" class="ps-inspector-dock" hidden>
                <section class="ps-inspector-view" data-inspector-view="info">
                    <header class="ps-inspector-header">Info</header>
                    <div id="infoPanel" class="ps-info-panel">
                        <div class="ps-info-colors"><span>R: <strong data-info="r">0</strong></span><span>C: <strong data-info="c">0%</strong></span><span>G: <strong data-info="g">0</strong></span><span>M: <strong data-info="m">0%</strong></span><span>B: <strong data-info="b">0</strong></span><span>Y: <strong data-info="yellow">0%</strong></span><span>A: <strong data-info="a">0</strong></span><span>K: <strong data-info="k">0%</strong></span></div>
                        <div class="ps-info-grid"><span>X: <strong data-info="x">0</strong></span><span>W: <strong data-info="w">0</strong></span><span>Y: <strong data-info="y">0</strong></span><span>H: <strong data-info="h">0</strong></span></div>
                        <div class="ps-info-hsb"><span>H: <strong data-info="hue">0°</strong></span><span>S: <strong data-info="saturation">0%</strong></span><span>B: <strong data-info="brightness">0%</strong></span></div>
                    </div>
                </section>
                <section class="ps-inspector-view" data-inspector-view="properties"><header class="ps-inspector-header">Properties</header><div id="propertiesPanel" class="ps-properties-panel"></div></section>
                <section class="ps-inspector-view" data-inspector-view="brush"><header class="ps-inspector-header">Brush</header><div class="ps-brush-panel"><div class="ps-brush-sections"><label class="active">Tip Shape</label><label><input type="checkbox"> Tip Dynamics</label><label><input type="checkbox"> Scatter</label><label><input type="checkbox"> Texture</label><label><input type="checkbox"> Color Dynamics</label><label><input type="checkbox"> Transfer</label></div><div class="ps-brush-controls"><div class="ps-brush-presets"><span class="soft"></span><span class="hard"></span><span class="dot"></span><span class="round"></span><small>Soft</small><small>Hard</small><small>12</small><small>24</small></div><label>Size: <input type="number" value="15"> px</label><input type="range" min="1" max="200" value="15"><label>Angle: <input type="number" value="0">°</label><input type="range" min="-180" max="180" value="0"><label>Roundness: <input type="number" value="100">%</label><input type="range" min="1" max="100" value="100"><label>Hardness: <input type="number" value="100">%</label><input type="range" min="0" max="100" value="100"><label>Spacing: <input type="number" value="25">%</label><input type="range" min="1" max="100" value="25"><div class="ps-brush-preview"></div></div></div></section>
                <section class="ps-inspector-view" data-inspector-view="character"><header class="ps-inspector-header">Character</header><div class="ps-type-panel"><div class="ps-control-pair"><select><option>DejaVu Sans</option><option>Arial</option><option>Georgia</option></select><select><option>Book</option><option>Bold</option><option>Italic</option></select></div><div class="ps-control-pair"><label>Size: <input value="24 px"></label><label>Tracking: <input value="0%"></label></div><label>Leading: <input value="24 px"> <input type="checkbox" checked> Auto</label><div class="ps-control-pair"><input value="↕ 100%"><input value="↔ 100%"></div><label>Baseline shift: <input value="0 px"> <input class="ps-color-chip" type="color" value="#000000"></label><div class="ps-type-options">P <em>P</em> Pᴾ Pₚ <u>P</u> <s>P</s> ﬂ œ</div><label>Digits: <select><option>LTR Arabic - 123</option></select></label></div></section>
                <section class="ps-inspector-view" data-inspector-view="paragraph"><header class="ps-inspector-header">Paragraph</header><div class="ps-paragraph-panel"><div class="ps-paragraph-align"><button class="active">☰</button><button>☷</button><button>≡</button><button>☰</button><button>≡</button><button>☷</button></div><div class="ps-control-pair"><label>Left <input value="0 px"></label><label>Right <input value="0 px"></label></div><div class="ps-control-pair"><label>First line <input value="0 px"></label><label>Space before <input value="0 px"></label></div><div class="ps-control-pair"><label>Space after <input value="0 px"></label><label>Leading <input value="120%"></label></div><label>Direction: <select><option>Left to right</option><option>Right to left</option></select></label></div></section>
                <section class="ps-inspector-view" data-inspector-view="gallery"><header class="ps-inspector-header">Gallery</header><div class="ps-gallery-controls"><button type="button" title="Gallery help">?</button><label>Keywords: <input id="galleryKeywords"></label><label><input id="galleryIsolated" type="checkbox"> Isolated</label></div><div id="galleryPanel" class="ps-gallery-panel"></div></section>
            </section>
            <nav class="ps-panel-rail" aria-label="Panel tools">
                <button type="button" data-inspector-panel="info" title="Info"><img class="ps-icon" src="{{ asset('ps-icons/panels-info.png') }}" alt=""></button>
                <button type="button" data-inspector-panel="properties" title="Properties"><img class="ps-icon" src="{{ asset('ps-icons/panels-properties.png') }}" alt=""></button>
                <button type="button" data-inspector-panel="brush" title="Brush"><img class="ps-icon" src="{{ asset('ps-icons/panels-brush.png') }}" alt=""></button>
                <button type="button" data-inspector-panel="character" title="Character"><img class="ps-icon" src="{{ asset('ps-icons/panels-character.png') }}" alt=""></button>
                <button type="button" data-inspector-panel="paragraph" title="Paragraph"><img class="ps-icon" src="{{ asset('ps-icons/panels-paragraph.png') }}" alt=""></button>
                <button type="button" data-inspector-panel="gallery" title="Gallery"><img class="ps-icon" src="{{ asset('ps-icons/panels-navigator.png') }}" alt=""></button>
            </nav>
        </aside>
    </main>

    <input id="localFileInput" type="file" accept=".psd,image/*" hidden>
    <input id="placeImageInput" type="file" accept="image/*" hidden>
    <div id="layerAssetStore" hidden aria-hidden="true"></div>
    <div id="toast" class="ps-toast" role="status" hidden></div>

    <dialog id="layerizeHistoryDialog" class="ps-dialog ps-layerize-dialog">
        <header><h2>Layerize history</h2><button type="button" data-close-layerize-history aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
        <div id="layerizeHistoryList" class="ps-layerize-history"><div class="ps-panel-empty">Loading Layerize history…</div></div>
    </dialog>
    <dialog id="imageToImageDialog" class="ps-dialog ps-gen-ai-dialog">
        <form method="dialog" id="imageToImageForm">
            <header><h2>Image to Image</h2><button value="cancel" aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
            <div class="ps-dialog-body">
                <p id="imageToImageLayerHint" class="ps-dialog-note"></p>
                <label>Prompt<textarea id="imageToImagePrompt" rows="5" required placeholder="Describe the image you want to create…"></textarea></label>
                <label>Model
                    <input id="imageToImageModel" type="search" list="imageToImageModelList" autocomplete="off" required placeholder="Type to filter image-to-image models…">
                    <datalist id="imageToImageModelList">
                        @foreach ($imageModels as $model)
                            @php($metadata = $model['metadata'] ?? [])
                            <option value="{{ $model['endpoint_id'] }}">{{ ($metadata['display_name'] ?? $model['endpoint_id']).' — '.$model['endpoint_id'].(!empty($metadata['category']) ? ' · '.$metadata['category'] : '') }}</option>
                        @endforeach
                    </datalist>
                </label>
                <label>Output resolution
                    <select id="imageToImageResolution">
                        <optgroup label="1MP">
                            <option value="1:1-1024" data-width="1024" data-height="1024">1:1 (1024 × 1024)</option>
                            <option value="3:2-1024" data-width="1216" data-height="832">3:2 (1216 × 832) Landscape</option>
                            <option value="4:3-1024" data-width="1152" data-height="896">4:3 (1152 × 896) Landscape</option>
                            <option value="16:9-1024" data-width="1344" data-height="768">16:9 (1344 × 768) Landscape</option>
                            <option value="21:9-1024" data-width="1536" data-height="640">21:9 (1536 × 640) Landscape</option>
                            <option value="2:3-1024" data-width="832" data-height="1216">2:3 (832 × 1216) Portrait</option>
                            <option value="3:4-1024" data-width="896" data-height="1152">3:4 (896 × 1152) Portrait</option>
                            <option value="9:16-1024" data-width="768" data-height="1344">9:16 (768 × 1344) Portrait</option>
                            <option value="9:21-1024" data-width="640" data-height="1536">9:21 (640 × 1536) Portrait</option>
                        </optgroup>
                        <optgroup label="2MP">
                            <option value="1:1-1408" data-width="1408" data-height="1408">1:1 (1408 × 1408)</option>
                            <option value="3:2-1408" data-width="1728" data-height="1152">3:2 (1728 × 1152) Landscape</option>
                            <option value="4:3-1408" data-width="1664" data-height="1216">4:3 (1664 × 1216) Landscape</option>
                            <option value="16:9-1408" data-width="1920" data-height="1088">16:9 (1920 × 1088) Landscape</option>
                            <option value="21:9-1408" data-width="2176" data-height="960">21:9 (2176 × 960) Landscape</option>
                            <option value="2:3-1408" data-width="1152" data-height="1728">2:3 (1152 × 1728) Portrait</option>
                            <option value="3:4-1408" data-width="1216" data-height="1664">3:4 (1216 × 1664) Portrait</option>
                            <option value="9:16-1408" data-width="1088" data-height="1920">9:16 (1088 × 1920) Portrait</option>
                            <option value="9:21-1408" data-width="960" data-height="2176">9:21 (960 × 2176) Portrait</option>
                        </optgroup>
                    </select>
                </label>
                <label class="ps-resolution-checkbox"><input id="imageToImageSameSize" type="checkbox"> Send output resolution same as picture</label>
                <p id="imageToImageResolutionHint" class="ps-dialog-note"></p>
            </div>
            <footer><button value="generate">Generate</button><button value="cancel" class="secondary">Cancel</button></footer>
        </form>
    </dialog>
    <dialog id="imageToImageHistoryDialog" class="ps-dialog ps-layerize-dialog">
        <header><h2>Image to Image history</h2><button type="button" data-close-image-to-image-history aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
        <div id="imageToImageHistoryList" class="ps-layerize-history"><div class="ps-panel-empty">Loading Image to Image history…</div></div>
    </dialog>    <dialog id="genAiPromptDialog" class="ps-dialog ps-gen-ai-dialog">
        <form method="dialog" id="genAiPromptForm">
            <header class="ps-draggable-dialog-handle"><h2>Generate AI edit</h2><button value="cancel" aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
            <div class="ps-dialog-body"><p id="genAiRegionHint" class="ps-gen-ai-region-hint"></p><label>Instructions<textarea id="genAiPrompt" rows="7" required placeholder="Describe all edits for the colored regions…"></textarea></label><p class="ps-dialog-note">The flattened selected layers and colored rectangles will be sent to fal.ai.</p></div>
            <footer><button value="generate">Generate</button><button value="cancel" class="secondary">Cancel</button></footer>
        </form>
    </dialog>
    <dialog id="genAiHistoryDialog" class="ps-dialog ps-layerize-dialog">
        <header><h2>Gen AI edit history</h2><button type="button" data-close-gen-ai-history aria-label="Close"><img class="ps-icon" src="{{ asset('ps-icons/cross.png') }}" alt=""></button></header>
        <div id="genAiHistoryList" class="ps-layerize-history"><div class="ps-panel-empty">Loading edit history…</div></div>
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
                    <button id="clipboardDocumentCard" type="button" class="ps-clipboard-document-card" hidden>
                        <canvas id="clipboardDocumentPreview" aria-label="Clipboard image preview"></canvas>
                        <span><strong>Create from Clipboard</strong><small id="clipboardDocumentDimensions"></small></span>
                    </button>
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
