@extends('_layouts.app', [
    'title'     => 'Content Planner — Media Library',
    'pageTitle' => 'Content Planner',
])

@section('styles')
<style>
    .cp-media-card.picker-selected { outline: 3px solid #6366f1; outline-offset: -3px; }
    .cp-media-card.picker-selected .cp-picker-check { display: flex; }
    .cp-picker-check { display: none; position: absolute; top: 6px; left: 6px; width: 22px; height: 22px; border-radius: 50%; background: #6366f1; color: #fff; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; z-index: 2; }
    #imageEditorContainer { display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.85); }
    body.fie-editor-open #imageEditorContainer { display: block; }
    body.fie-editor-open .SfxModal-Wrapper { z-index: 10001 !important; }
    body.fie-editor-open #SfxPopper { position: relative !important; z-index: 10002 !important; }
    #fie-close-btn { display: none; position: fixed; top: 12px; right: 16px; z-index: 10003; }
    body.fie-editor-open #fie-close-btn { display: block; }
</style>
@endsection

@section('content')
<div class="space-y-4">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:photo" width="20" class="text-primary-500"></iconify-icon>
                Media Library
            </h2>
            <p class="text-sm text-slate-500 mt-0.5">Upload and manage images and videos</p>
        </div>
        <span id="mediaCount" class="text-xs text-slate-400"></span>
    </div>

    {{-- Upload zone --}}
    <div id="uploadZone"
         class="border-2 border-dashed border-slate-200 rounded-xl p-10 text-center cursor-pointer bg-white hover:border-primary-400 hover:bg-primary-50/30 transition-all"
         ondragover="event.preventDefault(); this.classList.add('border-primary-400','bg-primary-50/30')"
         ondragleave="this.classList.remove('border-primary-400','bg-primary-50/30')"
         ondrop="handleDrop(event)"
         onclick="document.getElementById('fileInput').click()">
        <iconify-icon icon="heroicons-outline:cloud-arrow-up" width="36" class="text-slate-300 mx-auto block"></iconify-icon>
        <p class="text-sm text-slate-600 mt-3 font-medium">Drop files here or <span class="text-primary-600 font-semibold">browse</span></p>
        <p class="text-[11px] text-slate-400 mt-1">JPG, PNG, GIF, WEBP, MP4, MOV — Max 50MB</p>
        <input id="fileInput" type="file" accept="image/*,video/*" multiple class="hidden" onchange="handleFiles(this.files)">
    </div>

    {{-- Upload progress --}}
    <div id="uploadProgress" class="hidden">
        <div class="bg-primary-50 rounded-lg px-4 py-3 flex items-center gap-3">
            <div class="w-5 h-5 border-[3px] border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
            <span id="uploadProgressText" class="text-xs font-medium text-primary-700">Uploading...</span>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-slate-200 p-3 flex items-center gap-2 flex-wrap">
        <div class="relative flex-1 min-w-[160px] max-w-[220px]">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="filterSearch" placeholder="Search files..." oninput="debounceRefresh()"
                   class="w-full h-[30px] pl-7 pr-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-700 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-primary-500/20">
        </div>
        <select id="filterType" class="h-[30px] rounded-md border border-slate-200 bg-white px-2 text-xs text-slate-600 outline-none" onchange="refreshMedia()">
            <option value="">All Types</option>
            <option value="image">Images</option>
            <option value="video">Videos</option>
        </select>
        <select id="filterUsage" class="h-[30px] rounded-md border border-slate-200 bg-white px-2 text-xs text-slate-600 outline-none" onchange="refreshMedia()">
            <option value="">All Files</option>
            <option value="used">Used in Posts</option>
            <option value="unused">Unused</option>
        </select>
    </div>

    {{-- Media grid --}}
    <div id="mediaGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:8px;"></div>

    {{-- Pagination --}}
    <div id="mediaPagination" class="flex justify-center gap-1 py-2"></div>
</div>

{{-- Picker bar --}}
<div id="pickerBar" class="hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-[9990] bg-primary-600 text-white rounded-xl px-5 py-2.5 text-sm font-semibold shadow-xl flex items-center gap-3">
    <span id="pickerCount">0 selected</span>
    <button onclick="addSelectedMedia()" class="px-4 py-1.5 bg-white text-primary-600 rounded-md text-xs font-semibold hover:bg-primary-50">Add Selected</button>
    <button onclick="clearPickerSelection()" class="px-3 py-1.5 text-white/70 text-xs hover:text-white hover:bg-white/10 rounded-md">Clear</button>
</div>

{{-- Preview lightbox --}}
<div id="previewOverlay" class="hidden fixed inset-0 bg-black/80 z-[9998] cursor-pointer" onclick="closePreview()"></div>
<div id="previewBox" class="hidden fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 max-w-[90vw] max-h-[90vh] z-[9999] rounded-lg overflow-hidden bg-black"></div>

{{-- Image Editor --}}
<div id="imageEditorContainer"></div>
<button id="fie-close-btn" onclick="closeImageEditor()" class="px-3 py-1.5 bg-red-500 text-white rounded-md text-xs font-semibold hover:bg-red-600">Close Editor</button>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&family=Open+Sans:wght@400;600&family=Lato:wght@400;700&family=Montserrat:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<script src="https://scaleflex.cloudimg.io/v7/plugins/filerobot-image-editor/latest/filerobot-image-editor.min.js"></script>
<script>
    const isPickerMode = new URLSearchParams(window.location.search).get('picker') === '1';
    let imageEditorInstance = null;
    let debounceTimer;
    let currentPage = 1;
    const pickerSelected = new Map();
    const pickerMediaCache = {};

    function debounceRefresh() { clearTimeout(debounceTimer); debounceTimer = setTimeout(refreshMedia, 300); }

    function handleDrop(event) { event.preventDefault(); document.getElementById('uploadZone').classList.remove('border-primary-400','bg-primary-50/30'); handleFiles(event.dataTransfer.files); }

    async function handleFiles(files) {
        const progress = document.getElementById('uploadProgress');
        const progressText = document.getElementById('uploadProgressText');
        progress.classList.remove('hidden');
        for (let i = 0; i < files.length; i++) {
            progressText.textContent = `Uploading ${i+1}/${files.length}: ${files[i].name}`;
            const formData = new FormData();
            formData.append('file', files[i]);
            try {
                const res = await fetch('{{ route("marketing.planner.api.media.upload") }}', { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}, body:formData });
                if (!res.ok) { const err = await res.text(); alert('Upload failed for '+files[i].name); }
            } catch(e) { alert('Upload failed: '+e.message); }
        }
        progress.classList.add('hidden');
        refreshMedia();
    }

    async function refreshMedia(page) {
        if (page) currentPage = page;
        const params = new URLSearchParams({ page:currentPage, per_page:30 });
        const search = document.getElementById('filterSearch').value;
        const type = document.getElementById('filterType').value;
        const usage = document.getElementById('filterUsage').value;
        if (search) params.set('search',search); if (type) params.set('type',type); if (usage) params.set('usage',usage);
        try {
            const res = await fetch(`{{ route('marketing.planner.api.media.index') }}?${params}`);
            const data = await res.json();
            renderMediaGrid(data.data);
            renderPagination(data);
            document.getElementById('mediaCount').textContent = data.total + ' file' + (data.total!==1?'s':'');
        } catch(e) { console.error(e); }
    }

    function renderMediaGrid(items) {
        const grid = document.getElementById('mediaGrid');
        if (!items.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:48px 0;color:#94a3b8;font-size:13px;"><iconify-icon icon="heroicons-outline:photo" width="32" style="display:block;margin:0 auto 8px;color:#cbd5e1;"></iconify-icon>No media files yet. Upload some!</div>';
            return;
        }
        grid.innerHTML = items.map(m => {
            const isVideo = m.is_video;
            const clickAction = isPickerMode ? `togglePickerSelect(${m.id})` : `previewMedia('${m.url}',${isVideo})`;
            const selectedClass = isPickerMode && pickerSelected.has(m.id) ? ' picker-selected' : '';
            if (isPickerMode) pickerMediaCache[m.id] = m;
            const escapedUrl = (m.url||'').replace(/'/g, "\\'");
            const escapedThumb = (m.thumbnail_url||m.url||'').replace(/'/g, "\\'");
            return `<div class="cp-media-card${selectedClass}" id="picker-card-${m.id}" onclick="${clickAction}" style="position:relative;aspect-ratio:1;overflow:hidden;border-radius:8px;cursor:pointer;background:#f1f5f9;">
                ${isPickerMode ? '<span class="cp-picker-check">&#10003;</span>' : ''}
                ${isVideo
                    ? `<video src="${m.url}" muted autoplay loop playsinline style="width:100%;height:100%;object-fit:cover;display:block;"></video>`
                    : `<img src="${escapedThumb}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" loading="lazy">`}
                ${isVideo ? `<span style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.6);color:#fff;font-size:9px;padding:2px 6px;border-radius:4px;display:flex;align-items:center;gap:3px;"><iconify-icon icon="heroicons-solid:play" width="8"></iconify-icon>Video</span>` : ''}
                <div style="position:absolute;inset:0;background:linear-gradient(transparent 50%,rgba(0,0,0,0.6));opacity:0;transition:opacity 0.15s;display:flex;flex-direction:column;justify-content:flex-end;padding:8px;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                    <div style="font-size:11px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${m.original_filename||''}</div>
                    <div style="font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;">${m.human_size||''}${m.width ? ' · '+m.width+'×'+m.height : ''}</div>
                    <div style="display:flex;gap:4px;margin-top:6px;">
                        ${!isVideo ? `<button onclick="event.stopPropagation();openImageEditor(${m.id},'${escapedUrl}')" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;" title="Edit"><iconify-icon icon="heroicons-outline:pencil-square" width="13" style="color:#fff;"></iconify-icon></button>` : ''}
                        <button onclick="event.stopPropagation();deleteMedia(${m.id})" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;" title="Delete"><iconify-icon icon="heroicons-outline:trash" width="13" style="color:#fff;"></iconify-icon></button>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function renderPagination(data) {
        const el = document.getElementById('mediaPagination');
        if (data.last_page<=1) { el.innerHTML=''; return; }
        let html = `<button class="px-2.5 py-1 border border-slate-200 rounded-md bg-white text-xs text-slate-500 hover:border-primary-500" ${data.current_page<=1?'disabled':''} onclick="refreshMedia(${data.current_page-1})">Prev</button>`;
        for (let i=1;i<=data.last_page;i++) html+=`<button class="px-2.5 py-1 border rounded-md text-xs ${i===data.current_page?'bg-primary-600 text-white border-primary-600':'border-slate-200 bg-white text-slate-500 hover:border-primary-500'}" onclick="refreshMedia(${i})">${i}</button>`;
        html+=`<button class="px-2.5 py-1 border border-slate-200 rounded-md bg-white text-xs text-slate-500 hover:border-primary-500" ${data.current_page>=data.last_page?'disabled':''} onclick="refreshMedia(${data.current_page+1})">Next</button>`;
        el.innerHTML=html;
    }

    async function deleteMedia(id) {
        if (!confirm('Delete this file?')) return;
        await fetch(`/marketing/planner/api/media/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'} });
        refreshMedia();
    }

    function previewMedia(url,isVideo) {
        document.getElementById('previewOverlay').classList.remove('hidden');
        const box = document.getElementById('previewBox');
        box.classList.remove('hidden');
        box.innerHTML = isVideo ? `<video src="${url}" controls autoplay muted playsinline class="max-w-[90vw] max-h-[90vh]"></video>` : `<img src="${url}" class="max-w-[90vw] max-h-[90vh]">`;
    }

    function closePreview() {
        document.getElementById('previewOverlay').classList.add('hidden');
        const box = document.getElementById('previewBox');
        box.classList.add('hidden');
        box.innerHTML='';
    }

    function togglePickerSelect(id) {
        const card = document.getElementById('picker-card-'+id);
        if (pickerSelected.has(id)) { pickerSelected.delete(id); card?.classList.remove('picker-selected'); }
        else { const media = pickerMediaCache[id]; if(media) { pickerSelected.set(id,media); card?.classList.add('picker-selected'); } }
        updatePickerBar();
    }
    function updatePickerBar() {
        const bar = document.getElementById('pickerBar');
        const count = pickerSelected.size;
        if (count>0) { bar.classList.remove('hidden'); bar.style.display='flex'; document.getElementById('pickerCount').textContent=count+' selected'; }
        else { bar.classList.add('hidden'); bar.style.display=''; }
    }
    function clearPickerSelection() { pickerSelected.forEach((_,id)=>document.getElementById('picker-card-'+id)?.classList.remove('picker-selected')); pickerSelected.clear(); updatePickerBar(); }
    function addSelectedMedia() {
        if (!pickerSelected.size) return;
        if (window.opener && !window.opener.closed) { window.opener.postMessage({type:'media-batch-selected',mediaList:Array.from(pickerSelected.values())},'*'); window.close(); }
        else alert('Composer window not found.');
    }

    let editingMediaId = null;
    function openImageEditor(mediaId, imageUrl) {
        editingMediaId = mediaId;
        document.body.classList.add('fie-editor-open');
        const container = document.getElementById('imageEditorContainer');
        const FIE = window.FilerobotImageEditor;
        if (!FIE) { alert('Image editor failed to load.'); closeImageEditor(); return; }
        const TABS = FIE.TABS||{}, TOOLS = FIE.TOOLS||{};
        imageEditorInstance = new FIE(container, {
            source:imageUrl, onBeforeSave:()=>false,
            onSave:(imageData)=>saveEditedImage(imageData),
            onClose:()=>closeImageEditor(),
            annotationsCommon:{fill:'#000',stroke:'#000',strokeWidth:0,opacity:1},
            Text:{text:'Add text...',fontFamily:'Inter',fonts:[{label:'Inter',value:'Inter'},{label:'Roboto',value:'Roboto'},{label:'Open Sans',value:'Open Sans'},{label:'Montserrat',value:'Montserrat'},{label:'Poppins',value:'Poppins'}],fontSize:28},
            Rotate:{componentType:'slider'},
            theme:{palette:{'bg-primary-active':'#EEF2FF','accent-primary':'#6366f1'},typography:{fontFamily:'Inter, Roboto, Arial'}},
            tabsIds:[TABS.ADJUST||'Adjust',TABS.ANNOTATE||'Annotate',TABS.FILTERS||'Filters',TABS.FINETUNE||'Finetune',TABS.RESIZE||'Resize'],
            defaultTabId:TABS.ADJUST||'Adjust', defaultToolId:TOOLS.CROP||'Crop', savingPixelRatio:4, previewPixelRatio:2,
        });
        imageEditorInstance.render();
    }
    function closeImageEditor() { document.body.classList.remove('fie-editor-open'); if(imageEditorInstance){imageEditorInstance.terminate();imageEditorInstance=null;} editingMediaId=null; }
    async function saveEditedImage(imageData) {
        if (!editingMediaId) return;
        const base64=imageData.imageBase64;
        if (!base64) { const canvas=imageData.imageCanvas; if(canvas){canvas.toBlob(async(blob)=>{if(blob) await uploadEditedBlob(blob,imageData.fullName||'edited.png');},'image/png');return;} alert('Could not get edited image.'); return; }
        try { const blob=await fetch(base64).then(r=>r.blob()); await uploadEditedBlob(blob,imageData.fullName||'edited.png'); } catch(e){ alert('Failed: '+e.message); }
    }
    async function uploadEditedBlob(blob,fileName) {
        const formData = new FormData();
        formData.append('file', new File([blob],fileName,{type:blob.type||'image/png'}));
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.upload") }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},body:formData});
            if(res.ok){closeImageEditor();refreshMedia();} else alert('Failed to save.');
        } catch(e){ alert('Failed: '+e.message); }
    }

    document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(document.body.classList.contains('fie-editor-open'))closeImageEditor();else closePreview();}});
    document.addEventListener('DOMContentLoaded', refreshMedia);
</script>
@endsection
