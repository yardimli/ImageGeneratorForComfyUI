import Moveable from 'moveable';
import '../css/photoshop.css';

const root = document.getElementById('photoshopApp');
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const state = { projects: new Map(), tabs: [], activeProjectId: null, activeLayerId: null, tool: 'move', zoom: 1, selection: null, clipboard: null, moveable: null, panel: 'layers', histories: new Map() };
const sizes = [
    ['1MP · 1:1',1024,1024],['1MP · 3:2 Landscape',1216,832],['1MP · 4:3 Landscape',1152,896],['1MP · 16:9 Landscape',1344,768],['1MP · 21:9 Landscape',1536,640],
    ['1MP · 2:3 Portrait',832,1216],['1MP · 3:4 Portrait',896,1152],['1MP · 9:16 Portrait',768,1344],['1MP · 9:21 Portrait',640,1536],
    ['2MP · 1:1',1408,1408],['2MP · 3:2 Landscape',1728,1152],['2MP · 4:3 Landscape',1664,1216],['2MP · 16:9 Landscape',1920,1088],['2MP · 21:9 Landscape',2176,960],
    ['2MP · 2:3 Portrait',1152,1728],['2MP · 3:4 Portrait',1216,1664],['2MP · 9:16 Portrait',1088,1920],['2MP · 9:21 Portrait',960,2176],
];
const tools = [
    ['move','↖','Move tool (V)','V'],['marquee','▭','Rectangular marquee (M)','M'],['lasso','♧','Lasso (later)','L'],['magic','✣','Object selection (later)','W'],
    ['crop','⌑','Crop (later)','C'],['eyedropper','⚗','Eyedropper (later)','I'],['healing','▰','Healing (later)','J'],['brush','🖌','Brush (later)','B'],
    ['stamp','♟','Clone stamp (later)','S'],['eraser','▱','Eraser (later)','E'],['gradient','◩','Gradient (later)','G'],['blur','●','Blur (later)','R'],
    ['pen','♢','Pen (later)','P'],['type','T','Type (later)','T'],['path','◁','Path selection (later)','A'],['hand','✋','Hand (later)','H'],['zoom','⌕','Zoom','Z']
];
const actions = {
    newProject: () => document.getElementById('newProjectDialog').showModal(),
    openProject: openProjectLibrary,
    openLocal: () => document.getElementById('localFileInput').click(),
    closeProject: () => closeTab(state.activeProjectId),
    saveProject: () => toast('All project and layer changes are already saved.'),
    exit: () => window.close(),
    selectAll: () => selectArea({x:0,y:0,width:activeProject()?.width ?? 0,height:activeProject()?.height ?? 0}),
    deselect: clearSelection,
    copy: copySelection,
    paste: () => pasteClipboard(false),
    pasteInPlace: () => pasteClipboard(true),
    newBlankLayer: createBlankLayer,
    duplicateLayer: duplicateLayer,
    renameLayer,
    zoomIn: () => setZoom(state.zoom * 1.2),
    zoomOut: () => setZoom(state.zoom / 1.2),
    fitScreen,
    actualPixels: () => setZoom(1),
    help: () => toast('Use Move to transform a layer, Marquee to select an area, and Ctrl+C / Ctrl+V to copy between tabs.'),
};

async function api(url, options = {}) {
    const response = await fetch(url, { ...options, headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf, ...(options.headers || {}) } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'The request failed.');
    return data;
}

function activeProject() { return state.projects.get(Number(state.activeProjectId)); }
function activeLayer() { return activeProject()?.layers.find(layer => Number(layer.id) === Number(state.activeLayerId)); }
function layerUrl(project) { return `${root.dataset.projectBaseUrl}/${project.id}/layers`; }
function toast(message) { const el = document.getElementById('toast'); el.textContent = message; el.hidden = false; clearTimeout(toast.timer); toast.timer = setTimeout(() => el.hidden = true, 3200); }
function saving(active) { document.getElementById('saveStatus').textContent = active ? 'Saving…' : 'All changes saved'; }
function recordHistory(label, icon = '▤') { const project=activeProject(); if(!project)return; const history=state.histories.get(project.id) || []; history.push({label,icon}); state.histories.set(project.id,history.slice(-100)); renderHistory(); }
function switchPanel(panel) { state.panel=panel; document.querySelectorAll('[data-panel-tab]').forEach(button=>button.classList.toggle('active',button.dataset.panelTab===panel)); document.querySelectorAll('[data-panel-view]').forEach(view=>view.classList.toggle('active',view.dataset.panelView===panel)); if(panel==='properties')renderProperties(); if(panel==='history')renderHistory(); }
function renderProperties() { const panel=document.getElementById('propertiesPanel'),layer=activeLayer(); if(!layer){panel.innerHTML='<div class="ps-panel-empty">Select a layer to view its properties.</div>';return;} panel.innerHTML=`<div class="ps-property-identity"><img class="ps-layer-thumb" src="${layer.image_url}" alt=""><strong>${escapeHtml(layer.name)}<small>Pixel Layer</small></strong></div><section class="ps-property-section"><h3>⌄ &nbsp; Transform</h3><div class="ps-property-grid"><label>W<input data-layer-property="width" type="number" min="1" value="${Math.round(layer.width)}"></label><label>X<input data-layer-property="x" type="number" value="${Math.round(layer.x)}"></label><label>H<input data-layer-property="height" type="number" min="1" value="${Math.round(layer.height)}"></label><label>Y<input data-layer-property="y" type="number" value="${Math.round(layer.y)}"></label><label>∠<input data-layer-property="rotation" type="number" step="0.1" value="${Number(layer.rotation).toFixed(1)}"></label><label>%<input data-layer-property="opacity" type="number" min="0" max="100" value="${layer.opacity}"></label></div></section><section class="ps-property-section"><h3>⌄ &nbsp; Align and Distribute</h3><div class="ps-align-buttons"><button type="button" data-align-layer="left">Left</button><button type="button" data-align-layer="center">Center</button><button type="button" data-align-layer="right">Right</button></div></section>`; }
function renderHistory() { const panel=document.getElementById('historyPanel'),project=activeProject(); if(!project){panel.innerHTML='<div class="ps-panel-empty">Open a project to see its history.</div>';return;} const history=state.histories.get(project.id) || []; panel.innerHTML=`<div class="ps-history-document"><span class="ps-history-icon">▧</span><strong>${escapeHtml(project.name)}</strong></div><ol class="ps-history-list">${history.map((entry,index)=>`<li class="ps-history-entry${index===history.length-1?' current':''}"><span class="ps-history-icon">${entry.icon}</span><span>${escapeHtml(entry.label)}</span></li>`).join('')}</ol>`; }
function alignActiveLayer(mode) { const project=activeProject(),layer=activeLayer(); if(!project||!layer)return; const x=mode==='left'?0:mode==='right'?project.width-layer.width:(project.width-layer.width)/2; updateLayer(layer,{x},'Align Layer').then(render); }

async function buildMenus() {
    const menus = await fetch('/photoshop-menus.json').then(r => r.json());
    const bar = document.getElementById('menuBar');
    menus.filter(menu => menu.visible).forEach(menu => {
        const wrap = document.createElement('div'); wrap.className = 'ps-menu';
        const button = document.createElement('button'); button.className = 'ps-menu-button'; button.textContent = menu.label; button.type = 'button';
        const panel = document.createElement('div'); panel.className = 'ps-menu-panel'; panel.hidden = true;
        menu.items.filter(item => item.visible).forEach(item => panel.append(buildMenuItem(item)));
        button.addEventListener('click', event => { event.stopPropagation(); const opening = panel.hidden; closeMenus(); panel.hidden = !opening; wrap.classList.toggle('open', opening); });
        wrap.append(button, panel); bar.append(wrap);
    });
}
function buildMenuItem(item) {
    if (item.separator) { const line = document.createElement('div'); line.className = 'ps-menu-separator'; return line; }
    const button = document.createElement('button'); button.type = 'button'; button.className = 'ps-menu-item'; button.disabled = item.enabled === false;
    const label = document.createElement('span'); label.textContent = item.label;
    const suffix = document.createElement('span'); suffix.className = 'ps-menu-shortcut'; suffix.textContent = item.children ? '▶' : (item.shortcut || ''); button.append(label, suffix);
    if (item.children) { const sub = document.createElement('span'); sub.className = 'ps-submenu'; item.children.filter(child => child.visible).forEach(child => sub.append(buildMenuItem(child))); button.append(sub); }
    if (item.action) button.addEventListener('click', event => { event.stopPropagation(); closeMenus(); actions[item.action]?.(); });
    return button;
}
function closeMenus() { document.querySelectorAll('.ps-menu-panel').forEach(panel => panel.hidden = true); document.querySelectorAll('.ps-menu').forEach(menu => menu.classList.remove('open')); }

function buildTools() {
    const panel = document.getElementById('toolsPanel');
    tools.forEach(([id,icon,title,key]) => { const button = document.createElement('button'); button.type = 'button'; button.className = `ps-tool${id === 'move' ? ' active' : ''}`; button.dataset.tool = id; button.title = title; button.innerHTML = `<span>${icon}</span><kbd>${key}</kbd>`; panel.append(button); });
}
function selectTool(tool) {
    if (!['move','marquee','zoom'].includes(tool)) { toast(`${tools.find(t => t[0] === tool)?.[2] || 'This tool'} will be added in a later version.`); return; }
    state.tool = tool; document.querySelectorAll('.ps-tool').forEach(button => button.classList.toggle('active', button.dataset.tool === tool));
    const meta = tools.find(t => t[0] === tool); document.getElementById('activeToolIcon').textContent = meta[1]; document.getElementById('activeToolName').textContent = meta[2].split(' (')[0];
    updateMoveable();
}

function normalizeProject(project) { project.id = Number(project.id); project.width = Number(project.width); project.height = Number(project.height); project.layers = (project.layers || []).map(layer => ({...layer,id:Number(layer.id),x:Number(layer.x),y:Number(layer.y),width:Number(layer.width),height:Number(layer.height),rotation:Number(layer.rotation),opacity:Number(layer.opacity),z_index:Number(layer.z_index),visible:layer.visible!==false&&layer.visible!==0&&layer.visible!=='0'})); return project; }
function openTab(project, historyLabel = 'Open') { project = normalizeProject(project); state.projects.set(project.id, project); if (!state.tabs.includes(project.id)) state.tabs.push(project.id); state.activeProjectId = project.id; state.activeLayerId = project.layers.at(-1)?.id ?? null; if(!state.histories.has(project.id))state.histories.set(project.id,[]); recordHistory(historyLabel,historyLabel==='New'?'▤':'▧'); render(); requestAnimationFrame(fitScreen); }
function closeTab(id) { if (!id) return; const index = state.tabs.indexOf(Number(id)); state.tabs = state.tabs.filter(tab => tab !== Number(id)); if (state.activeProjectId === Number(id)) state.activeProjectId = state.tabs[Math.max(0,index-1)] ?? state.tabs[0] ?? null; state.activeLayerId = activeProject()?.layers.at(-1)?.id ?? null; clearSelection(); render(); }
function render() { renderTabs(); renderCanvas(); renderLayers(); renderProperties(); renderHistory(); }
function renderTabs() {
    const tabs = document.getElementById('documentTabs'); tabs.replaceChildren();
    state.tabs.forEach(id => { const project = state.projects.get(id); const tab = document.createElement('button'); tab.type='button'; tab.className=`ps-tab${id === state.activeProjectId ? ' active' : ''}`; tab.innerHTML=`<span class="ps-tab-name">${escapeHtml(project.name)} @ ${Math.round(state.zoom*100)}% (RGB/8)</span><span class="ps-tab-close" aria-label="Close">×</span>`; tab.addEventListener('click', e => { if (e.target.closest('.ps-tab-close')) closeTab(id); else { state.activeProjectId=id; state.activeLayerId=project.layers.at(-1)?.id ?? null; clearSelection(); render(); requestAnimationFrame(fitScreen); } }); tabs.append(tab); });
}
function renderCanvas() {
    state.moveable?.destroy(); state.moveable = null;
    const project = activeProject(), canvas = document.getElementById('canvas'), shell = document.getElementById('canvasShell'), empty = document.getElementById('emptyState'); canvas.querySelectorAll('.ps-canvas-layer').forEach(el => el.remove());
    empty.hidden = !!project; shell.hidden = !project; if (!project) return;
    canvas.style.width = `${project.width * state.zoom}px`; canvas.style.height = `${project.height * state.zoom}px`;
    [...project.layers].sort((a,b) => a.z_index-b.z_index).forEach(layer => { const img = new Image(); img.className='ps-canvas-layer'; img.dataset.layerId=layer.id; img.src=layer.image_url; img.alt=layer.name; img.draggable=false; Object.assign(img.style,{left:`${layer.x*state.zoom}px`,top:`${layer.y*state.zoom}px`,width:`${layer.width*state.zoom}px`,height:`${layer.height*state.zoom}px`,opacity:String(layer.opacity/100),display:layer.visible?'block':'none',zIndex:String(layer.z_index),transform:`rotate(${layer.rotation}deg)`}); img.addEventListener('pointerdown', () => { if (state.tool === 'move' && document.getElementById('autoSelect').checked) { state.activeLayerId=layer.id; renderLayers(); updateMoveable(); } }); canvas.append(img); });
    renderSelection(); requestAnimationFrame(updateMoveable);
}
function renderLayers() {
    const panel=document.getElementById('layersPanel'), project=activeProject(); panel.replaceChildren(); if (!project) return;
    [...project.layers].sort((a,b)=>b.z_index-a.z_index).forEach(layer => { const row=document.createElement('div'); row.className=`ps-layer-row${layer.id===state.activeLayerId?' active':''}`; row.innerHTML=`<button type="button" class="ps-layer-eye" title="Toggle visibility">${layer.visible?'◉':'○'}</button><img class="ps-layer-thumb" src="${layer.image_url}" alt=""><span class="ps-layer-name">${escapeHtml(layer.name)}</span>`; row.addEventListener('click', e => { if (e.target.closest('.ps-layer-eye')) { layer.visible=!layer.visible; updateLayer(layer,{visible:layer.visible},layer.visible?'Show Layer':'Hide Layer'); render(); } else { state.activeLayerId=layer.id; renderLayers(); renderProperties(); updateMoveable(); document.getElementById('layerOpacity').value=layer.opacity; } }); panel.append(row); });
}
function updateMoveable() {
    state.moveable?.destroy(); state.moveable=null; const project=activeProject(), layer=activeLayer(); if (!project || !layer || state.tool!=='move' || !layer.visible) return;
    const canvas=document.getElementById('canvas'), target=canvas.querySelector(`[data-layer-id="${layer.id}"]`); if (!target) return;
    const otherLayers=[...canvas.querySelectorAll('.ps-canvas-layer')].filter(el=>el!==target && el.style.display!=='none');
    state.moveable=new Moveable(canvas,{target,draggable:true,resizable:true,rotatable:true,snappable:true,snapThreshold:7,snapGap:true,isDisplaySnapDigit:true,verticalGuidelines:[0,project.width*state.zoom/2,project.width*state.zoom],horizontalGuidelines:[0,project.height*state.zoom/2,project.height*state.zoom],elementGuidelines:otherLayers,origin:false,keepRatio:false,renderDirections:['nw','n','ne','w','e','sw','s','se']});
    state.moveable.on('drag',e=>{ target.style.left=`${e.left}px`; target.style.top=`${e.top}px`; }).on('dragEnd',()=>{ layer.x=parseFloat(target.style.left)/state.zoom; layer.y=parseFloat(target.style.top)/state.zoom; updateLayer(layer,{x:layer.x,y:layer.y},'Move'); })
      .on('resize',e=>{ target.style.width=`${e.width}px`; target.style.height=`${e.height}px`; target.style.transform=e.drag.transform.replace(/translate\([^)]*\)/,'')+` rotate(${layer.rotation}deg)`; target.style.left=`${e.drag.left}px`; target.style.top=`${e.drag.top}px`; }).on('resizeEnd',()=>{ layer.x=parseFloat(target.style.left)/state.zoom; layer.y=parseFloat(target.style.top)/state.zoom; layer.width=parseFloat(target.style.width)/state.zoom; layer.height=parseFloat(target.style.height)/state.zoom; target.style.transform=`rotate(${layer.rotation}deg)`; updateLayer(layer,{x:layer.x,y:layer.y,width:layer.width,height:layer.height},'Transform'); })
      .on('rotate',e=>{ target.style.transform=e.transform; }).on('rotateEnd',e=>{ layer.rotation=e.lastEvent?.beforeRotate ?? layer.rotation; target.style.transform=`rotate(${layer.rotation}deg)`; updateLayer(layer,{rotation:layer.rotation},'Rotate'); });
}
async function updateLayer(layer, changes, historyLabel = null) { saving(true); Object.assign(layer,changes); if(historyLabel)recordHistory(historyLabel,historyLabel==='Move'?'✥':'▤'); renderProperties(); try { await api(`${root.dataset.layerBaseUrl}/${layer.id}`,{method:'PATCH',body:JSON.stringify(changes)}); } catch(error) { toast(error.message); } finally { saving(false); } }

async function createProject(payload) { saving(true); try { const data=await api(root.dataset.projectStoreUrl,{method:'POST',body:JSON.stringify(payload)}); openTab(data.project,'New'); return activeProject(); } finally { saving(false); } }
async function refreshProjects() { const data=await api(root.dataset.projectsUrl); data.projects.forEach(project=>state.projects.set(Number(project.id),normalizeProject(project))); return data.projects; }
async function openProjectLibrary() { const dialog=document.getElementById('openProjectDialog'), library=document.getElementById('projectLibrary'); library.innerHTML='<p>Loading projects…</p>'; dialog.showModal(); try { const projects=await refreshProjects(); library.replaceChildren(); if (!projects.length) library.innerHTML='<p>No saved projects yet.</p>'; projects.forEach(project=>{ const button=document.createElement('button'); button.type='button'; button.className='ps-project-card'; button.innerHTML=`<strong>${escapeHtml(project.name)}</strong><span>${project.layers.length} layer${project.layers.length===1?'':'s'}</span><small>${project.width} × ${project.height}px</small><small>${new Date(project.updated_at).toLocaleString()}</small>`; button.addEventListener('click',()=>{openTab(project);dialog.close();}); library.append(button); }); } catch(error){ library.innerHTML=`<p>${escapeHtml(error.message)}</p>`; } }

async function imageFileToPng(file) { const bitmap=await createImageBitmap(file); if (bitmap.width>8192||bitmap.height>8192) throw new Error('Images are limited to 8192 × 8192 pixels.'); const canvas=document.createElement('canvas'); canvas.width=bitmap.width; canvas.height=bitmap.height; canvas.getContext('2d').drawImage(bitmap,0,0); bitmap.close(); return {dataUrl:canvas.toDataURL('image/png'),width:canvas.width,height:canvas.height}; }
async function addLayer(project, dataUrl, width, height, name, x=0, y=0, historyLabel='New Layer') { saving(true); try { const data=await api(layerUrl(project),{method:'POST',body:JSON.stringify({name,image:dataUrl,width,height,x,y})}); project.layers.push({...data.layer,id:Number(data.layer.id),x:Number(data.layer.x),y:Number(data.layer.y),width:Number(data.layer.width),height:Number(data.layer.height),rotation:Number(data.layer.rotation),opacity:Number(data.layer.opacity),z_index:Number(data.layer.z_index),visible:true}); state.activeLayerId=Number(data.layer.id); recordHistory(historyLabel,historyLabel==='Paste'?'▤':'▧'); render(); return data.layer; } finally { saving(false); } }
async function createBlankLayer() { const project=activeProject(); if(!project){toast('Open a project first.');return;} const canvas=document.createElement('canvas'); canvas.width=project.width; canvas.height=project.height; await addLayer(project,canvas.toDataURL('image/png'),project.width,project.height,`Layer ${project.layers.length+1}`,0,0,'New Layer'); }
async function duplicateLayer() { const project=activeProject(),layer=activeLayer(); if(!project||!layer){toast('Select a layer first.');return;} const image=await loadImage(layer.image_url); const canvas=document.createElement('canvas'); canvas.width=image.naturalWidth;canvas.height=image.naturalHeight;canvas.getContext('2d').drawImage(image,0,0); await addLayer(project,canvas.toDataURL('image/png'),layer.width,layer.height,`${layer.name} copy`,layer.x+10,layer.y+10,'Duplicate Layer'); }
function renameLayer(){const layer=activeLayer();if(!layer){toast('Select a layer first.');return;}const name=prompt('Layer name',layer.name);if(name?.trim()){layer.name=name.trim();updateLayer(layer,{name:layer.name},'Rename Layer');renderLayers();}}

function selectArea(area) { if(!activeProject()) return; state.selection=area; renderSelection(); }
function clearSelection(){state.selection=null;renderSelection();}
function renderSelection(){const box=document.getElementById('selectionBox');if(!state.selection||!activeProject()){box.hidden=true;return;}box.hidden=false;Object.assign(box.style,{left:`${state.selection.x*state.zoom}px`,top:`${state.selection.y*state.zoom}px`,width:`${state.selection.width*state.zoom}px`,height:`${state.selection.height*state.zoom}px`});}
async function renderComposite(project) { const canvas=document.createElement('canvas');canvas.width=project.width;canvas.height=project.height;const ctx=canvas.getContext('2d'); for(const layer of [...project.layers].sort((a,b)=>a.z_index-b.z_index)){if(!layer.visible)continue;const image=await loadImage(layer.image_url);ctx.save();ctx.globalAlpha=layer.opacity/100;ctx.translate(layer.x+layer.width/2,layer.y+layer.height/2);ctx.rotate(layer.rotation*Math.PI/180);ctx.drawImage(image,-layer.width/2,-layer.height/2,layer.width,layer.height);ctx.restore();}return canvas;}
async function copySelection(){const project=activeProject();if(!project){toast('Open a project first.');return;}const area=state.selection||{x:0,y:0,width:project.width,height:project.height};if(area.width<1||area.height<1)return;const composite=await renderComposite(project);const out=document.createElement('canvas');out.width=Math.round(area.width);out.height=Math.round(area.height);out.getContext('2d').drawImage(composite,area.x,area.y,area.width,area.height,0,0,out.width,out.height);state.clipboard={dataUrl:out.toDataURL('image/png'),width:out.width,height:out.height,x:area.x,y:area.y};try{const blob=await new Promise(resolve=>out.toBlob(resolve,'image/png'));await navigator.clipboard?.write([new ClipboardItem({'image/png':blob})]);}catch{}toast(`Copied ${out.width} × ${out.height}px.`);}
async function pasteClipboard(inPlace=false){const project=activeProject();if(!project){toast('Open a destination project first.');return;}let clip=state.clipboard;if(!clip){try{const item=(await navigator.clipboard.read()).find(i=>i.types.includes('image/png'));if(item){const blob=await item.getType('image/png');const file=await imageFileToPng(blob);clip={...file,x:0,y:0};}}catch{}}if(!clip){toast('Nothing copied yet.');return;}const x=inPlace?clip.x:Math.max(0,(project.width-clip.width)/2),y=inPlace?clip.y:Math.max(0,(project.height-clip.height)/2);await addLayer(project,clip.dataUrl,clip.width,clip.height,`Pasted layer ${project.layers.length+1}`,x,y,'Paste');}
function loadImage(src){return new Promise((resolve,reject)=>{const image=new Image();image.crossOrigin='anonymous';image.onload=()=>resolve(image);image.onerror=reject;image.src=src;});}

function setZoom(value){state.zoom=Math.min(4,Math.max(.05,value));renderCanvas();renderTabs();}
function fitScreen(){const project=activeProject(),viewport=document.getElementById('stageViewport');if(!project)return;setZoom(Math.min(1,(viewport.clientWidth-100)/project.width,(viewport.clientHeight-150)/project.height));}
function escapeHtml(value){const div=document.createElement('div');div.textContent=String(value);return div.innerHTML;}

document.addEventListener('click',event=>{if(!event.target.closest('.ps-menu'))closeMenus();const action=event.target.closest('[data-action]')?.dataset.action;if(action)actions[action]?.();const tool=event.target.closest('[data-tool]')?.dataset.tool;if(tool)selectTool(tool);const panel=event.target.closest('[data-panel-tab]')?.dataset.panelTab;if(panel)switchPanel(panel);const align=event.target.closest('[data-align-layer]')?.dataset.alignLayer;if(align)alignActiveLayer(align);});
document.addEventListener('keydown',event=>{if(['INPUT','SELECT','TEXTAREA'].includes(event.target.tagName))return;const mod=event.ctrlKey||event.metaKey;let action=null;if(mod&&event.key.toLowerCase()==='n')action='newProject';if(mod&&event.key.toLowerCase()==='o')action='openProject';if(mod&&event.key.toLowerCase()==='a')action='selectAll';if(mod&&event.key.toLowerCase()==='c')action='copy';if(mod&&event.key.toLowerCase()==='v')action='paste';if(mod&&event.key.toLowerCase()==='d')action='deselect';if(mod&&event.key==='0')action='fitScreen';if(mod&&event.key==='1')action='actualPixels';if(action){event.preventDefault();actions[action]?.();}if(!mod&&['v','m','z'].includes(event.key.toLowerCase()))selectTool(event.key.toLowerCase()==='m'?'marquee':event.key.toLowerCase()==='v'?'move':'zoom');});
document.getElementById('newProjectForm').addEventListener('submit',async event=>{event.preventDefault();const button=event.submitter;if(button?.value==='cancel'){document.getElementById('newProjectDialog').close();return;}button.disabled=true;try{await createProject({name:document.getElementById('newProjectName').value,width:Number(document.getElementById('projectWidth').value),height:Number(document.getElementById('projectHeight').value),blank_layer:true});document.getElementById('newProjectDialog').close();}catch(error){toast(error.message);}finally{button.disabled=false;}});
document.getElementById('sizePreset').addEventListener('change',event=>{const preset=sizes[event.target.value];document.getElementById('projectWidth').value=preset[1];document.getElementById('projectHeight').value=preset[2];});
document.getElementById('localFileInput').addEventListener('change',async event=>{const file=event.target.files[0];event.target.value='';if(!file)return;try{const png=await imageFileToPng(file);const project=await createProject({name:file.name.replace(/\.[^.]+$/,''),width:png.width,height:png.height,blank_layer:false});await addLayer(project,png.dataUrl,png.width,png.height,file.name,0,0,'Open from Local');}catch(error){toast(error.message);}});
document.getElementById('layerOpacity').addEventListener('change',event=>{const layer=activeLayer();if(layer)updateLayer(layer,{opacity:Number(event.target.value)},'Opacity Change').then(renderCanvas);});
document.getElementById('propertiesPanel').addEventListener('change',event=>{const input=event.target.closest('[data-layer-property]'),layer=activeLayer();if(!input||!layer)return;const property=input.dataset.layerProperty,value=Number(input.value);if(!Number.isFinite(value))return;const changes={[property]:value};updateLayer(layer,changes,property==='opacity'?'Opacity Change':'Transform').then(render);});
document.getElementById('canvas').addEventListener('pointerdown',event=>{if(state.tool==='zoom'){setZoom(state.zoom*(event.altKey ? .8 : 1.25));return;}if(state.tool!=='marquee')return;const canvas=event.currentTarget,rect=canvas.getBoundingClientRect(),start={x:(event.clientX-rect.left)/state.zoom,y:(event.clientY-rect.top)/state.zoom};canvas.setPointerCapture(event.pointerId);const move=e=>{const x=(e.clientX-rect.left)/state.zoom,y=(e.clientY-rect.top)/state.zoom;selectArea({x:Math.max(0,Math.min(start.x,x)),y:Math.max(0,Math.min(start.y,y)),width:Math.min(activeProject().width,Math.abs(x-start.x)),height:Math.min(activeProject().height,Math.abs(y-start.y))});};const up=()=>{canvas.removeEventListener('pointermove',move);canvas.removeEventListener('pointerup',up);};canvas.addEventListener('pointermove',move);canvas.addEventListener('pointerup',up);});
window.addEventListener('resize',()=>{if(activeProject())fitScreen();});

async function init(){const select=document.getElementById('sizePreset');sizes.forEach((size,index)=>{const option=document.createElement('option');option.value=index;option.textContent=`${size[0]} (${size[1]} × ${size[2]})`;select.append(option);});buildTools();await buildMenus();try{await refreshProjects();}catch(error){toast(error.message);}}
init();
