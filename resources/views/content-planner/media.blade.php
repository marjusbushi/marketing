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

    /* Folder sidebar */
    .ml-layout { display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .ml-layout { grid-template-columns: 1fr; } }
    .ml-sidebar-heading { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; padding: 6px 10px; margin-top: 2px; }
    .folder-item { width: 100%; display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: none; background: transparent; cursor: pointer; border-radius: 6px; font-size: 13px; color: #334155; text-align: left; transition: background 0.1s; }
    .folder-item:hover { background: #f1f5f9; }
    .folder-item.active { background: #eef2ff; color: #4338ca; font-weight: 600; }
    .folder-icon { font-size: 15px; line-height: 1; width: 18px; display: inline-flex; justify-content: center; }
    .folder-label { flex: 1; }
    .folder-count { font-size: 11px; color: #94a3b8; font-weight: 500; }
    .folder-item.active .folder-count { color: #6366f1; }
    .folder-divider { height: 1px; background: #e2e8f0; margin: 6px 8px; }

    /* Stage dot on thumbnail */
    .stage-dot { position: absolute; bottom: 6px; left: 6px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.3); z-index: 3; cursor: help; }
    .stage-dot.raw { background: #EF4444; }
    .stage-dot.edited { background: #F59E0B; }
    .stage-dot.final { background: #10B981; }

    /* Stage filter */
    .stage-filter-item { width: 100%; display: flex; align-items: center; gap: 10px; padding: 6px 10px; border: none; background: transparent; cursor: pointer; border-radius: 6px; font-size: 12px; color: #334155; text-align: left; transition: background 0.1s; }
    .stage-filter-item:hover { background: #f1f5f9; }
    .stage-filter-item.active { background: #eef2ff; color: #4338ca; font-weight: 600; }
    .stage-filter-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .stage-filter-dot.all { background: linear-gradient(135deg, #EF4444 0%, #F59E0B 50%, #10B981 100%); }
    .stage-filter-dot.raw { background: #EF4444; }
    .stage-filter-dot.edited { background: #F59E0B; }
    .stage-filter-dot.final { background: #10B981; }

    /* Bulk select */
    .bulk-cb { position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,0.85); border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #94a3b8; z-index: 4; cursor: pointer; opacity: 0; transition: opacity 0.12s, background 0.12s; }
    .cp-media-card:hover .bulk-cb { opacity: 1; }
    .cp-media-card.bulk-selected { outline: 3px solid #6366f1; outline-offset: -3px; }
    .cp-media-card.bulk-selected .bulk-cb { opacity: 1; background: #6366f1; border-color: #6366f1; color: #fff; }
    body.bulk-mode .bulk-cb { opacity: 1; }

    /* When bulk-mode is on, video badge moves to bottom-right to not collide with checkbox */
    body.bulk-mode .cp-media-card .video-badge { top: auto; bottom: 6px; right: 6px; }

    #bulkToolbar { position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); z-index: 9993; background: #1e293b; color: #fff; border-radius: 12px; padding: 10px 14px; box-shadow: 0 10px 30px rgba(15,23,42,0.35); display: none; align-items: center; gap: 10px; font-size: 12px; }
    #bulkToolbar.active { display: flex; }
    #bulkToolbar .bulk-count { font-weight: 600; padding-right: 6px; border-right: 1px solid rgba(255,255,255,0.18); }
    #bulkToolbar select, #bulkToolbar button { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.15); border-radius: 6px; padding: 5px 10px; font-size: 12px; cursor: pointer; }
    #bulkToolbar select:hover, #bulkToolbar button:hover { background: rgba(255,255,255,0.14); }
    #bulkToolbar .btn-danger { background: #dc2626; border-color: #dc2626; }
    #bulkToolbar .btn-danger:hover { background: #b91c1c; }
    #bulkToolbar .btn-ghost { background: transparent; border-color: transparent; color: rgba(255,255,255,0.7); }
    #bulkToolbar .btn-ghost:hover { color: #fff; background: rgba(255,255,255,0.08); }

    /* Link badges (products + collections) on thumbnails */
    .link-badges { position: absolute; bottom: 6px; right: 6px; display: inline-flex; gap: 3px; align-items: center; z-index: 3; pointer-events: none; }
    .link-badges > * { pointer-events: auto; }
    .product-pill { display: inline-flex; align-items: center; height: 16px; padding: 0 5px; background: rgba(99,102,241,0.92); color: #fff; font-size: 9px; font-weight: 700; border-radius: 8px; letter-spacing: 0.03em; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.3); }
    .collection-dot { width: 10px; height: 10px; border-radius: 50%; border: 1.5px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.35); }
    .overflow-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 16px; height: 16px; padding: 0 4px; background: rgba(15,23,42,0.85); color: #fff; font-size: 9px; font-weight: 600; border-radius: 8px; }

    /* Drag-drop folder feedback */
    .folder-item.drop-target { background: #e0e7ff; outline: 2px dashed #6366f1; outline-offset: -2px; }
    .cp-media-card.dragging { opacity: 0.4; transform: scale(0.96); }

    /* Context menu */
    #ctxMenu { position: absolute; z-index: 9996; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 6px 20px rgba(15,23,42,0.14); padding: 4px; min-width: 180px; display: none; }
    #ctxMenu.active { display: block; }
    .ctx-header { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; padding: 6px 10px 4px; }
    .ctx-item { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; color: #334155; }
    .ctx-item:hover { background: #f1f5f9; }
    .ctx-item.active { background: #eef2ff; color: #4338ca; font-weight: 600; }
    .ctx-dot { width: 8px; height: 8px; border-radius: 50%; }

    /* Used-by badge */
    .used-by-badge { position: absolute; top: 6px; left: 6px; display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; background: rgba(15,23,42,0.85); color: #fff; font-size: 10px; font-weight: 600; border-radius: 999px; cursor: pointer; z-index: 3; border: none; transition: transform 0.08s; }
    .used-by-badge:hover { transform: scale(1.05); background: #6366f1; }

    /* Used-by popover */
    #usedByOverlay { position: fixed; inset: 0; z-index: 9994; background: transparent; display: none; }
    #usedByOverlay.active { display: block; }
    #usedByPopover { position: absolute; z-index: 9995; width: 320px; max-height: 400px; background: #fff; border-radius: 12px; box-shadow: 0 12px 32px rgba(15,23,42,0.18); border: 1px solid #e2e8f0; overflow: hidden; display: none; flex-direction: column; }
    #usedByPopover.active { display: flex; }
    .ubp-header { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 12px; font-weight: 600; color: #1e293b; display: flex; align-items: center; justify-content: space-between; }
    .ubp-close { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 16px; padding: 0; line-height: 1; }
    .ubp-close:hover { color: #1e293b; }
    .ubp-list { flex: 1; overflow-y: auto; padding: 6px; }
    .ubp-item { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 8px; cursor: pointer; transition: background 0.1s; text-decoration: none; color: inherit; }
    .ubp-item:hover { background: #f8fafc; }
    .ubp-thumb { width: 40px; height: 40px; border-radius: 6px; background: #f1f5f9; flex-shrink: 0; object-fit: cover; }
    .ubp-body { flex: 1; min-width: 0; }
    .ubp-caption { font-size: 12px; color: #334155; line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .ubp-meta { display: flex; align-items: center; gap: 6px; margin-top: 3px; font-size: 10px; color: #94a3b8; }
    .ubp-status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .ubp-empty { padding: 20px 14px; text-align: center; color: #94a3b8; font-size: 12px; }
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

    {{-- Upload linker row — pick products + collection BEFORE upload so new
         media auto-links (user can still skip). Kept in-between the nav and
         the drop zone to establish "scope" mental model. --}}
    <div class="bg-white rounded-xl border border-slate-200 p-3 flex flex-col sm:flex-row gap-3">
        <div class="flex-1 min-w-0">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide block mb-1.5">📦 Lidh me produkte (opsional)</label>
            <div id="uploadProductsWrap" class="relative">
                <div id="uploadProductsPills" class="flex flex-wrap items-center gap-1 px-2 py-1.5 border border-slate-200 rounded-md bg-white min-h-[32px] focus-within:border-primary-400 focus-within:ring-2 focus-within:ring-primary-500/20 cursor-text" onclick="document.getElementById('uploadProductsInput').focus()"></div>
                <input id="uploadProductsInput" type="text" placeholder="Kërko produkt..." class="hidden w-full h-[26px] px-1 text-xs outline-none bg-transparent" autocomplete="off" oninput="searchUploadProducts()" onfocus="showUploadProductsDropdown()" onkeydown="handleProductsInputKeydown(event)">
                <div id="uploadProductsDropdown" class="hidden absolute left-0 top-full mt-1 w-full max-h-[200px] overflow-y-auto bg-white border border-slate-200 rounded-md shadow-lg z-30"></div>
            </div>
        </div>
        <div class="flex-1 min-w-0">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide block mb-1.5">🎯 Lidh me koleksion (opsional)</label>
            <div id="uploadCollectionWrap" class="relative">
                <div id="uploadCollectionBox" class="flex items-center gap-2 px-2 py-1.5 border border-slate-200 rounded-md bg-white min-h-[32px] cursor-pointer" onclick="toggleUploadCollectionDropdown()">
                    <span id="uploadCollectionLabel" class="text-xs text-slate-400 flex-1 truncate">Zgjidh koleksion...</span>
                    <button id="uploadCollectionClear" type="button" onclick="event.stopPropagation(); clearUploadCollection()" class="hidden text-slate-400 hover:text-red-500" title="Hiq">&times;</button>
                </div>
                <div id="uploadCollectionDropdown" class="hidden absolute left-0 top-full mt-1 w-full max-h-[240px] overflow-y-auto bg-white border border-slate-200 rounded-md shadow-lg z-30"></div>
            </div>
        </div>
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
        <p class="text-[11px] text-slate-400 mt-1">Images up to 50MB · Videos up to 500MB · MP4, MOV, AVI, MKV, WEBM, M4V, HEIC, etj.</p>
        <input id="fileInput" type="file" accept="image/*,video/*" multiple class="hidden" onchange="handleFiles(this.files)">
    </div>

    {{-- Upload progress (multi-file, real %) --}}
    <div id="uploadProgress" class="hidden bg-white border border-slate-200 rounded-xl p-3 space-y-2">
        <div class="flex items-center justify-between">
            <span id="uploadSummary" class="text-xs font-semibold text-slate-700">Uploading…</span>
            <button type="button" onclick="dismissCompletedUploads()" class="text-[11px] text-slate-400 hover:text-slate-700">Pastro të kryera</button>
        </div>
        <div id="uploadList" class="space-y-1.5 max-h-[260px] overflow-y-auto"></div>
    </div>

    {{-- Layout: sidebar + main --}}
    <div class="ml-layout">

        {{-- Folder sidebar --}}
        <aside class="bg-white rounded-xl border border-slate-200 p-2 sticky top-4">
            <div class="ml-sidebar-heading">Folders</div>
            <ul id="folderList" style="list-style:none; padding:0; margin:0;">
                <li class="text-xs text-slate-400 px-3 py-2">Loading...</li>
            </ul>
            {{-- Stage filter section is injected by task #1336 below this divider --}}
            <div id="stageFilterSection"></div>
            {{-- Collections + Products (Media Library v3) --}}
            <div id="collectionsSection"></div>
            <div id="productsSection"></div>
        </aside>

        {{-- Main column --}}
        <div class="space-y-4 min-w-0">

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
                <div id="currentFolderLabel" class="ml-auto text-xs text-slate-500"></div>
            </div>

            {{-- Media grid --}}
            <div id="mediaGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:8px;"></div>

            {{-- Pagination --}}
            <div id="mediaPagination" class="flex justify-center gap-1 py-2"></div>
        </div>
    </div>
</div>

{{-- Picker bar --}}
<div id="pickerBar" class="hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-[9990] bg-primary-600 text-white rounded-xl px-5 py-2.5 text-sm font-semibold shadow-xl flex items-center gap-3">
    <span id="pickerCount">0 selected</span>
    <button onclick="addSelectedMedia()" class="px-4 py-1.5 bg-white text-primary-600 rounded-md text-xs font-semibold hover:bg-primary-50">Add Selected</button>
    <button onclick="clearPickerSelection()" class="px-3 py-1.5 text-white/70 text-xs hover:text-white hover:bg-white/10 rounded-md">Clear</button>
</div>

{{-- Bulk-select toolbar --}}
<div id="bulkToolbar">
    <span class="bulk-count" id="bulkCount">0 selected</span>
    <select id="bulkMoveFolder" onchange="bulkMoveSelected(this.value); this.value=''">
        <option value="">Move to…</option>
        <option value="reels">🎬 Reels</option>
        <option value="videos">📹 Videos</option>
        <option value="photos">📷 Photos</option>
        <option value="stories">📖 Stories</option>
        <option value="referenca">🔖 Referenca</option>
        <option value="imported">📥 Imported</option>
        <option value="__uncategorized">📂 Uncategorized</option>
    </select>
    <select id="bulkSetStage" onchange="bulkSetStageSelected(this.value); this.value=''">
        <option value="">Set stage…</option>
        <option value="raw">🔴 Raw</option>
        <option value="edited">🟡 Edited</option>
        <option value="final">🟢 Final</option>
    </select>
    <button onclick="openBulkLinkProducts()" title="Link selected media to a product">📦 Link product</button>
    <button onclick="openBulkLinkCollections()" title="Link selected media to a collection">🎯 Link collection</button>
    <button class="btn-danger" onclick="bulkDeleteSelected()">Delete</button>
    <button class="btn-ghost" onclick="bulkDeselect()">×</button>
</div>

{{-- Bulk link mini-modal (product / collection picker) --}}
<div id="bulkLinkOverlay" class="hidden fixed inset-0 bg-black/40 z-[9995]" onclick="closeBulkLink()"></div>
<div id="bulkLinkCard" class="hidden fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[9996] w-[420px] max-w-[94vw] bg-white rounded-xl shadow-2xl border border-slate-200 flex flex-col" style="max-height: 70vh;">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h3 id="bulkLinkTitle" class="text-sm font-semibold text-slate-800">Link to…</h3>
        <button onclick="closeBulkLink()" class="w-7 h-7 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center">&times;</button>
    </div>
    <div class="px-4 py-3 border-b border-slate-100">
        <input id="bulkLinkSearch" type="text" placeholder="Search…" class="w-full h-9 px-3 rounded-md border border-slate-200 text-sm outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-500/20" autocomplete="off" oninput="refreshBulkLinkResults()">
    </div>
    <div id="bulkLinkResults" class="flex-1 overflow-y-auto p-2"></div>
</div>

{{-- Preview lightbox --}}
<div id="previewOverlay" class="hidden fixed inset-0 bg-black/80 z-[9998] cursor-pointer" onclick="closePreview()"></div>
<div id="previewBox" class="hidden fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 max-w-[90vw] max-h-[90vh] z-[9999] rounded-lg overflow-hidden bg-black"></div>

{{-- Context menu (right-click on thumbnail) --}}
<div id="ctxMenu" role="menu">
    <div class="ctx-header">Set stage</div>
    <div class="ctx-item" data-stage="raw" onclick="ctxSetStage('raw')"><span class="ctx-dot" style="background:#EF4444"></span>Raw</div>
    <div class="ctx-item" data-stage="edited" onclick="ctxSetStage('edited')"><span class="ctx-dot" style="background:#F59E0B"></span>Edited</div>
    <div class="ctx-item" data-stage="final" onclick="ctxSetStage('final')"><span class="ctx-dot" style="background:#10B981"></span>Final</div>
</div>

{{-- Used-by popover --}}
<div id="usedByOverlay" onclick="closeUsedByPopover()"></div>
<div id="usedByPopover" role="dialog" aria-label="Posts that use this media">
    <div class="ubp-header">
        <span id="usedByTitle">Used in</span>
        <button type="button" class="ubp-close" onclick="closeUsedByPopover()" aria-label="Close">&times;</button>
    </div>
    <div id="usedByList" class="ubp-list"></div>
</div>

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
    let currentFolder = new URLSearchParams(window.location.search).get('folder') || '__all';
    const pickerSelected = new Map();
    const pickerMediaCache = {};

    function debounceRefresh() { clearTimeout(debounceTimer); debounceTimer = setTimeout(refreshMedia, 300); }

    function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // ── Folder sidebar ──
    async function refreshFolders() {
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.folders.index") }}');
            const { folders } = await res.json();
            renderFolders(folders);
        } catch (e) { console.error('folders failed', e); }
    }

    function renderFolders(folders) {
        const ul = document.getElementById('folderList');
        const allEntry = folders.find(f => f.key === '__all');
        const named = folders.filter(f => f.key !== '__all' && f.key !== '__uncategorized');
        const uncat = folders.find(f => f.key === '__uncategorized');

        const renderItem = f => {
            // __all is not a drop target (it's a filter reset). __uncategorized
            // means "set folder = null" on drop, which is valid.
            const isDrop = f.key !== '__all';
            const dropAttrs = isDrop
                ? `ondragover="onFolderDragOver(event, '${escHtml(f.key)}')" ondragleave="onFolderDragLeave(event, this)" ondrop="onFolderDrop(event, '${escHtml(f.key)}')"`
                : '';
            return `
            <li>
                <button type="button" onclick="selectFolder('${escHtml(f.key)}')" class="folder-item ${currentFolder === f.key ? 'active' : ''}" data-folder-key="${escHtml(f.key)}" ${dropAttrs}>
                    <span class="folder-icon">${escHtml(f.icon)}</span>
                    <span class="folder-label">${escHtml(f.label)}</span>
                    <span class="folder-count">${Number(f.count) || 0}</span>
                </button>
            </li>`;
        };

        let html = '';
        if (allEntry) html += renderItem(allEntry);
        if (named.length) html += '<li><div class="folder-divider"></div></li>' + named.map(renderItem).join('');
        if (uncat && uncat.count > 0) html += '<li><div class="folder-divider"></div></li>' + renderItem(uncat);
        ul.innerHTML = html;

        const label = document.getElementById('currentFolderLabel');
        const active = folders.find(f => f.key === currentFolder);
        label.textContent = active ? `Viewing: ${active.label}` : '';
    }

    function selectFolder(key) {
        currentFolder = key;
        const url = new URL(window.location);
        if (key && key !== '__all') url.searchParams.set('folder', key);
        else url.searchParams.delete('folder');
        window.history.replaceState({}, '', url);
        document.querySelectorAll('.folder-item').forEach(b => b.classList.toggle('active', b.dataset.folderKey === key));
        currentPage = 1;
        refreshMedia();
        const btn = document.querySelector(`.folder-item[data-folder-key="${CSS.escape(key)}"]`);
        if (btn) document.getElementById('currentFolderLabel').textContent = `Viewing: ${btn.querySelector('.folder-label').textContent}`;
    }

    function handleDrop(event) { event.preventDefault(); document.getElementById('uploadZone').classList.remove('border-primary-400','bg-primary-50/30'); handleFiles(event.dataTransfer.files); }

    // ─────────────────────────────────────────────────────────────
    // Upload pipeline: parallel pool of 3 XHRs with real-time progress
    // (% / MB-s / ETA / cancel). Server defers ffmpeg + thumbnail to a
    // queue job, so the upload-zone reply arrives as soon as R2 acks
    // the file. We refresh the grid + poll for ~30 s afterwards to pick
    // up the thumbnails as the worker finishes them.
    // ─────────────────────────────────────────────────────────────
    const UPLOAD_POOL = 3;
    const UPLOAD_URL = '{{ route("marketing.planner.api.media.upload") }}';
    const UPLOAD_CSRF = '{{ csrf_token() }}';
    let uploadQueue = [];
    let pollTimer = null;
    let pollDeadline = 0;

    function handleFiles(filesLike) {
        const incoming = Array.from(filesLike).map(f => ({
            id: 'u_' + Math.random().toString(36).slice(2, 9),
            file: f,
            name: f.name,
            size: f.size,
            loaded: 0,
            status: 'queued',
            startedAt: 0,
            xhr: null,
            error: null,
            attachedProducts: Array.from(uploadProducts.values()).map(p => p.id),
            attachedCollection: uploadCollection ? uploadCollection.id : null,
        }));
        if (!incoming.length) return;

        uploadQueue = uploadQueue.filter(x => x.status === 'uploading' || x.status === 'queued');
        uploadQueue.push(...incoming);
        document.getElementById('uploadProgress').classList.remove('hidden');
        renderUploadList();
        runUploadPool();
    }

    function dismissCompletedUploads() {
        uploadQueue = uploadQueue.filter(x => x.status === 'uploading' || x.status === 'queued' || x.status === 'failed');
        renderUploadList();
        renderSummary();
        if (!uploadQueue.length) document.getElementById('uploadProgress').classList.add('hidden');
    }

    function runUploadPool() {
        const inFlight = uploadQueue.filter(x => x.status === 'uploading').length;
        let toStart = Math.max(0, UPLOAD_POOL - inFlight);
        for (const item of uploadQueue) {
            if (toStart <= 0) break;
            if (item.status !== 'queued') continue;
            startUpload(item);
            toStart--;
        }
        renderSummary();
    }

    function startUpload(item) {
        item.status = 'uploading';
        item.startedAt = Date.now();
        renderRow(item);
        renderSummary();

        const xhr = new XMLHttpRequest();
        item.xhr = xhr;
        const fd = new FormData();
        fd.append('file', item.file);
        item.attachedProducts.forEach(id => fd.append('item_group_ids[]', id));
        if (item.attachedCollection) fd.append('distribution_week_id', item.attachedCollection);

        xhr.upload.onprogress = (e) => {
            if (!e.lengthComputable) return;
            item.loaded = e.loaded;
            item.size = e.total || item.size;
            renderRow(item);
            renderSummary();
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.status = 'done';
                item.loaded = item.size;
            } else {
                item.status = 'failed';
                item.error = parseUploadError(xhr) || ('Server returned ' + xhr.status);
            }
            renderRow(item);
            renderSummary();
            onUploadFinished();
        };
        xhr.onerror = () => {
            item.status = 'failed';
            item.error = 'Lidhja u ndërpre';
            renderRow(item);
            renderSummary();
            onUploadFinished();
        };
        xhr.onabort = () => {
            item.status = 'cancelled';
            renderRow(item);
            renderSummary();
            onUploadFinished();
        };

        xhr.open('POST', UPLOAD_URL);
        xhr.setRequestHeader('X-CSRF-TOKEN', UPLOAD_CSRF);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(fd);
    }

    function parseUploadError(xhr) {
        try {
            const r = JSON.parse(xhr.responseText);
            if (r && r.message) return r.message;
            if (r && r.errors) return Object.values(r.errors).flat().join(' ');
        } catch (e) {}
        return null;
    }

    function cancelUpload(id) {
        const item = uploadQueue.find(x => x.id === id);
        if (!item) return;
        if (item.status === 'uploading' && item.xhr) {
            item.xhr.abort();
        } else if (item.status === 'queued') {
            item.status = 'cancelled';
            renderRow(item);
            renderSummary();
            onUploadFinished();
        }
    }

    function onUploadFinished() {
        runUploadPool();
        const stillBusy = uploadQueue.some(x => x.status === 'uploading' || x.status === 'queued');
        if (stillBusy) return;

        refreshMedia();
        refreshFolders();
        startThumbPoll();

        setTimeout(() => {
            uploadQueue = uploadQueue.filter(x => x.status !== 'done' && x.status !== 'cancelled');
            renderUploadList();
            renderSummary();
            if (!uploadQueue.length) document.getElementById('uploadProgress').classList.add('hidden');
        }, 4000);
    }

    function startThumbPoll() {
        pollDeadline = Date.now() + 30000;
        if (pollTimer) return;
        pollTimer = setInterval(() => {
            if (Date.now() > pollDeadline) {
                clearInterval(pollTimer);
                pollTimer = null;
                return;
            }
            refreshMedia();
        }, 3000);
    }

    function renderUploadList() {
        const list = document.getElementById('uploadList');
        while (list.firstChild) list.removeChild(list.firstChild);
        for (const item of uploadQueue) {
            list.appendChild(buildRowEl(item));
        }
    }

    function renderRow(item) {
        const list = document.getElementById('uploadList');
        const existing = list.querySelector(`[data-uid="${item.id}"]`);
        const fresh = buildRowEl(item);
        if (existing) existing.replaceWith(fresh);
        else list.appendChild(fresh);
    }

    function renderSummary() {
        const total = uploadQueue.length;
        const done = uploadQueue.filter(x => x.status === 'done').length;
        const failed = uploadQueue.filter(x => x.status === 'failed').length;
        const inflight = uploadQueue.filter(x => x.status === 'uploading' || x.status === 'queued').length;
        const sumBytes = uploadQueue.reduce((acc, x) => acc + (x.size || 0), 0);
        const sumLoaded = uploadQueue.reduce((acc, x) => acc + (x.loaded || 0), 0);
        const pct = sumBytes > 0 ? Math.round((sumLoaded / sumBytes) * 100) : 0;
        const summary = document.getElementById('uploadSummary');
        let txt;
        if (inflight > 0) {
            txt = `Po ngarkohet ${done + 1}/${total} · ${pct}%${failed ? ' · ' + failed + ' dështuar' : ''}`;
        } else if (total > 0) {
            txt = `${done}/${total} u ngarkuan${failed ? ' · ' + failed + ' dështuar' : ''}`;
        } else {
            txt = 'Uploading…';
        }
        summary.textContent = txt;
    }

    function buildRowEl(item) {
        const row = document.createElement('div');
        row.className = 'upload-row';
        row.dataset.uid = item.id;

        const inner = document.createElement('div');
        inner.className = 'flex items-center gap-2';
        row.appendChild(inner);

        const main = document.createElement('div');
        main.className = 'flex-1 min-w-0';
        inner.appendChild(main);

        const header = document.createElement('div');
        header.className = 'flex items-center justify-between gap-2 mb-1';
        main.appendChild(header);

        const nameSpan = document.createElement('span');
        nameSpan.className = 'text-xs font-medium text-slate-700 truncate';
        nameSpan.title = item.name;
        nameSpan.textContent = item.name;
        header.appendChild(nameSpan);

        const statusSpan = document.createElement('span');
        statusSpan.className = 'text-[10px] text-slate-500 shrink-0 tabular-nums';
        statusSpan.textContent = uploadStatusText(item);
        header.appendChild(statusSpan);

        const barWrap = document.createElement('div');
        barWrap.className = 'h-1.5 bg-slate-200 rounded-full overflow-hidden';
        main.appendChild(barWrap);

        const pct = item.size > 0 ? Math.min(100, Math.round((item.loaded / item.size) * 100)) : 0;
        const barColor = item.status === 'failed' ? 'bg-red-500'
            : item.status === 'cancelled' ? 'bg-slate-400'
            : item.status === 'done' ? 'bg-emerald-500'
            : 'bg-primary-600';
        const bar = document.createElement('div');
        bar.className = `h-full ${barColor} rounded-full transition-all duration-150`;
        bar.style.width = pct + '%';
        barWrap.appendChild(bar);

        if (item.status === 'failed' && item.error) {
            const errDiv = document.createElement('div');
            errDiv.className = 'text-[10px] text-red-600 mt-1 truncate';
            errDiv.title = item.error;
            errDiv.textContent = item.error;
            main.appendChild(errDiv);
        }

        const cancelable = item.status === 'queued' || item.status === 'uploading';
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'shrink-0 w-6 h-6 flex items-center justify-center rounded ' + (cancelable ? 'text-slate-400 hover:text-red-500 hover:bg-red-50' : 'text-slate-300 cursor-default');
        if (cancelable) {
            cancelBtn.title = 'Anulo';
            cancelBtn.addEventListener('click', () => cancelUpload(item.id));
        } else {
            cancelBtn.disabled = true;
        }
        const xIcon = document.createElement('iconify-icon');
        xIcon.setAttribute('icon', 'heroicons-outline:x-mark');
        xIcon.setAttribute('width', '14');
        cancelBtn.appendChild(xIcon);
        inner.appendChild(cancelBtn);

        return row;
    }

    function uploadStatusText(item) {
        if (item.status === 'queued') return 'Në pritje…';
        if (item.status === 'cancelled') return 'Anuluar';
        if (item.status === 'failed') return 'Dështoi';
        if (item.status === 'done') return 'Përfundoi · ' + fmtBytes(item.size);
        const pct = item.size > 0 ? Math.round((item.loaded / item.size) * 100) : 0;
        const elapsed = (Date.now() - item.startedAt) / 1000;
        let speedTxt = '—';
        let etaTxt = '—';
        if (elapsed > 0.4 && item.loaded > 0) {
            const bps = item.loaded / elapsed;
            speedTxt = fmtRate(bps);
            const remaining = (item.size - item.loaded) / bps;
            etaTxt = fmtDuration(remaining);
        }
        return `${pct}% · ${fmtBytes(item.loaded)} / ${fmtBytes(item.size)} · ${speedTxt} · ${etaTxt}`;
    }

    function fmtBytes(b) {
        if (!b && b !== 0) return '—';
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
        if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
        return (b/1073741824).toFixed(2) + ' GB';
    }

    function fmtRate(bps) {
        if (bps < 1048576) return (bps/1024).toFixed(0) + ' KB/s';
        return (bps/1048576).toFixed(1) + ' MB/s';
    }

    function fmtDuration(s) {
        if (!isFinite(s) || s < 0) return '—';
        if (s < 60) return Math.ceil(s) + 's';
        if (s < 3600) return Math.floor(s/60) + 'm ' + Math.ceil(s%60) + 's';
        return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm';
    }

    // ── Upload-zone product + collection selectors ──
    const uploadProducts = new Map();   // id -> {id, code, name}
    let uploadCollection = null;         // {id, code, name, display_label?}
    let productSearchDebounce;

    function renderUploadProductsPills() {
        const wrap = document.getElementById('uploadProductsPills');
        const input = document.getElementById('uploadProductsInput');
        const pills = Array.from(uploadProducts.values()).map(p =>
            `<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full text-[11px] font-medium">
                ${escHtml(p.code || p.id)}
                <button type="button" onclick="removeUploadProduct(${p.id})" class="text-primary-400 hover:text-red-500" title="Hiq">&times;</button>
            </span>`
        ).join('');
        wrap.innerHTML = pills + '<input id="uploadProductsInput" type="text" placeholder="' + (uploadProducts.size ? '+ shto' : 'Kërko produkt...') + '" class="flex-1 min-w-[100px] h-[24px] px-1 text-xs outline-none bg-transparent" autocomplete="off" oninput="searchUploadProducts()" onfocus="showUploadProductsDropdown()" onkeydown="handleProductsInputKeydown(event)">';
    }

    function removeUploadProduct(id) {
        uploadProducts.delete(id);
        renderUploadProductsPills();
    }

    async function searchUploadProducts() {
        const input = document.getElementById('uploadProductsInput');
        const q = (input?.value || '').trim();
        clearTimeout(productSearchDebounce);
        productSearchDebounce = setTimeout(async () => {
            try {
                const res = await fetch('{{ route("marketing.planner.api.media.products.search") }}?q=' + encodeURIComponent(q));
                const { results } = await res.json();
                renderProductsDropdown(results || []);
            } catch (e) { console.error(e); }
        }, 250);
    }

    function showUploadProductsDropdown() {
        searchUploadProducts();
    }

    function renderProductsDropdown(results) {
        const dd = document.getElementById('uploadProductsDropdown');
        if (!results.length) {
            dd.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Nuk u gjetën rezultate</div>';
        } else {
            dd.innerHTML = results.slice(0, 20).map(r => {
                const id = r.id ?? r.item_group_id;
                const code = r.code ?? r.item_group_code ?? '';
                const name = r.name ?? r.title ?? '';
                const disabled = uploadProducts.has(id);
                return `<div class="px-3 py-2 text-xs cursor-pointer ${disabled ? 'opacity-40' : 'hover:bg-primary-50'}" ${disabled ? '' : `onclick="addUploadProduct(${id}, '${escHtml(code)}', '${escHtml((name || '').replace(/'/g, '&#39;'))}')"`}>
                    <span class="font-semibold text-slate-700">${escHtml(code)}</span>
                    <span class="text-slate-500"> ${escHtml(name)}</span>
                </div>`;
            }).join('');
        }
        dd.classList.remove('hidden');
    }

    function addUploadProduct(id, code, name) {
        uploadProducts.set(Number(id), { id: Number(id), code, name });
        renderUploadProductsPills();
        document.getElementById('uploadProductsInput').focus();
    }

    function handleProductsInputKeydown(e) {
        if (e.key === 'Escape') {
            document.getElementById('uploadProductsDropdown').classList.add('hidden');
            e.target.blur();
        }
        if (e.key === 'Backspace' && !e.target.value && uploadProducts.size) {
            const lastId = Array.from(uploadProducts.keys()).pop();
            removeUploadProduct(lastId);
        }
    }

    // Close products dropdown on outside click
    document.addEventListener('click', (e) => {
        const wrap = document.getElementById('uploadProductsWrap');
        const dd = document.getElementById('uploadProductsDropdown');
        if (wrap && !wrap.contains(e.target)) dd.classList.add('hidden');
    });

    // ── Upload collection (single-select) ──
    let recentCollectionsCache = null;

    async function fetchRecentCollections() {
        if (recentCollectionsCache) return recentCollectionsCache;
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.collections.recent") }}');
            const { collections } = await res.json();
            recentCollectionsCache = collections || [];
            return recentCollectionsCache;
        } catch (e) { return []; }
    }

    async function toggleUploadCollectionDropdown() {
        const dd = document.getElementById('uploadCollectionDropdown');
        if (!dd.classList.contains('hidden')) {
            dd.classList.add('hidden');
            return;
        }
        const collections = await fetchRecentCollections();
        if (!collections.length) {
            dd.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Nuk u gjetën koleksione</div>';
        } else {
            dd.innerHTML = collections.slice(0, 40).map(c => {
                const id = c.id;
                const label = c.display_label || c.label || c.name || ('Week #' + id);
                const start = c.start_date || c.starts_at || '';
                return `<div class="px-3 py-2 text-xs cursor-pointer hover:bg-primary-50" onclick="setUploadCollection(${id}, '${escHtml(label.replace(/'/g, '&#39;'))}')">
                    <div class="font-medium text-slate-700">${escHtml(label)}</div>
                    ${start ? `<div class="text-[10px] text-slate-400">${escHtml(String(start).slice(0, 10))}</div>` : ''}
                </div>`;
            }).join('');
        }
        dd.classList.remove('hidden');
    }

    function setUploadCollection(id, label) {
        uploadCollection = { id: Number(id), label };
        const lab = document.getElementById('uploadCollectionLabel');
        lab.textContent = label;
        lab.classList.remove('text-slate-400');
        lab.classList.add('text-slate-700', 'font-medium');
        document.getElementById('uploadCollectionClear').classList.remove('hidden');
        document.getElementById('uploadCollectionDropdown').classList.add('hidden');
    }

    function clearUploadCollection() {
        uploadCollection = null;
        const lab = document.getElementById('uploadCollectionLabel');
        lab.textContent = 'Zgjidh koleksion...';
        lab.classList.remove('text-slate-700', 'font-medium');
        lab.classList.add('text-slate-400');
        document.getElementById('uploadCollectionClear').classList.add('hidden');
    }

    document.addEventListener('click', (e) => {
        const wrap = document.getElementById('uploadCollectionWrap');
        const dd = document.getElementById('uploadCollectionDropdown');
        if (wrap && !wrap.contains(e.target)) dd.classList.add('hidden');
    });

    async function refreshMedia(page) {
        if (page) currentPage = page;
        const params = new URLSearchParams({ page:currentPage, per_page:30 });
        const search = document.getElementById('filterSearch').value;
        const type = document.getElementById('filterType').value;
        const usage = document.getElementById('filterUsage').value;
        if (search) params.set('search',search); if (type) params.set('type',type); if (usage) params.set('usage',usage);
        if (currentFolder && currentFolder !== '__all') params.set('folder', currentFolder);
        if (typeof currentStage !== 'undefined' && currentStage) params.set('stage', currentStage);
        if (typeof currentProduct !== 'undefined' && currentProduct) params.set('product', currentProduct);
        if (typeof currentCollection !== 'undefined' && currentCollection) params.set('collection', currentCollection);
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
            const postsCount = Number(m.posts_count) || 0;
            const usedByBadge = (!isPickerMode && postsCount > 0)
                ? `<button type="button" class="used-by-badge" onclick="event.stopPropagation(); openUsedByPopover(${m.id}, this, ${postsCount})" title="Used in ${postsCount} post${postsCount!==1?'s':''}"><iconify-icon icon="heroicons-outline:document-text" width="10"></iconify-icon>${postsCount} Post${postsCount!==1?'s':''}</button>`
                : '';
            const stage = m.stage || 'raw';
            const stageLabel = stage.charAt(0).toUpperCase() + stage.slice(1);
            const stageDot = `<span class="stage-dot ${escHtml(stage)}" title="${escHtml(stageLabel)}"></span>`;
            const bulkSelectedClass = bulkSelected.has(m.id) ? ' bulk-selected' : '';
            const bulkCheckbox = !isPickerMode
                ? `<div class="bulk-cb" onclick="event.stopPropagation(); toggleBulkSelect(${m.id}, event)">${bulkSelected.has(m.id) ? '&#10003;' : ''}</div>`
                : '';
            const linkBadges = buildLinkBadges(m);
            return `<div class="cp-media-card${selectedClass}${bulkSelectedClass}" id="picker-card-${m.id}" onclick="${clickAction}" oncontextmenu="openCtxMenu(event, ${m.id}, '${escHtml(stage)}')" ${!isPickerMode ? `draggable="true" ondragstart="onMediaDragStart(event, ${m.id})" ondragend="onMediaDragEnd(event)"` : ''} style="position:relative;aspect-ratio:1;overflow:hidden;border-radius:8px;cursor:pointer;background:#f1f5f9;">
                ${isPickerMode ? '<span class="cp-picker-check">&#10003;</span>' : ''}
                ${bulkCheckbox}
                ${usedByBadge}
                ${stageDot}
                ${linkBadges}
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
        // Windowed pager: « ‹ 1 … (cur-1) [cur] (cur+1) … last › »  +  "Faqe X / Y"
        // Used on the Media grid where a folder can hold thousands of items
        // (e.g. /imported has ~1900) — rendering one button per page would
        // print 60+ buttons.
        const el = document.getElementById('mediaPagination');
        while (el.firstChild) el.removeChild(el.firstChild);
        const last = data.last_page || 1;
        if (last <= 1) return;
        const cur = data.current_page || 1;

        const inactive = 'px-2.5 py-1 border rounded-md text-xs border-slate-200 bg-white text-slate-500 hover:border-primary-500 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:border-slate-200';
        const active = 'px-2.5 py-1 border rounded-md text-xs bg-primary-600 text-white border-primary-600';

        function navBtn(label, page, disabled, title) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = inactive;
            b.textContent = label;
            if (title) b.title = title;
            if (disabled) b.disabled = true;
            else b.addEventListener('click', () => refreshMedia(page));
            return b;
        }
        function pageBtn(page) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = page === cur ? active : inactive;
            b.textContent = String(page);
            if (page !== cur) b.addEventListener('click', () => refreshMedia(page));
            return b;
        }
        function ellipsis() {
            const s = document.createElement('span');
            s.className = 'px-1 py-1 text-xs text-slate-400 select-none';
            s.textContent = '…';
            return s;
        }

        el.appendChild(navBtn('«', 1, cur <= 1, 'Faqja e parë'));
        el.appendChild(navBtn('‹', cur - 1, cur <= 1, 'Faqja e mëparshme'));

        // Build a small window of pages around `cur`, always including 1 and last.
        const window = new Set([1, last]);
        for (let i = cur - 1; i <= cur + 1; i++) {
            if (i >= 1 && i <= last) window.add(i);
        }
        const pages = Array.from(window).sort((a, b) => a - b);
        let prev = 0;
        for (const p of pages) {
            if (p - prev > 1) el.appendChild(ellipsis());
            el.appendChild(pageBtn(p));
            prev = p;
        }

        el.appendChild(navBtn('›', cur + 1, cur >= last, 'Faqja tjetër'));
        el.appendChild(navBtn('»', last, cur >= last, 'Faqja e fundit'));

        const tag = document.createElement('span');
        tag.className = 'ml-2 text-xs text-slate-500 self-center tabular-nums';
        tag.textContent = `Faqe ${cur} / ${last}`;
        el.appendChild(tag);
    }

    async function deleteMedia(id) {
        if (!confirm('Delete this file?')) return;
        await fetch(`/marketing/planner/api/media/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'} });
        refreshMedia();
        refreshFolders();
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

    // ── Bulk select ──
    const bulkSelected = new Set();
    let bulkLastClickedId = null;

    function toggleBulkSelect(id, event) {
        // Shift+click → range select from last clicked to current
        if (event && event.shiftKey && bulkLastClickedId !== null) {
            const ids = state_media_ids();
            const from = ids.indexOf(bulkLastClickedId);
            const to = ids.indexOf(id);
            if (from >= 0 && to >= 0) {
                const [a, b] = from < to ? [from, to] : [to, from];
                for (let i = a; i <= b; i++) bulkSelected.add(ids[i]);
            } else {
                bulkSelected.add(id);
            }
        } else {
            if (bulkSelected.has(id)) bulkSelected.delete(id);
            else bulkSelected.add(id);
        }
        bulkLastClickedId = id;
        updateBulkUI();
    }

    function state_media_ids() {
        // Fallback helper — reads data-id from cards currently rendered so the
        // range select honours the visible order (which may be filtered).
        return Array.from(document.querySelectorAll('.cp-media-card')).map(el => Number(el.id.replace('picker-card-','')));
    }

    function updateBulkUI() {
        const count = bulkSelected.size;
        const toolbar = document.getElementById('bulkToolbar');
        document.getElementById('bulkCount').textContent = count + ' selected';
        toolbar.classList.toggle('active', count > 0);
        document.body.classList.toggle('bulk-mode', count > 0);
        // Live-update card classes + checkbox glyphs without a full re-render
        document.querySelectorAll('.cp-media-card').forEach(el => {
            const id = Number(el.id.replace('picker-card-',''));
            const on = bulkSelected.has(id);
            el.classList.toggle('bulk-selected', on);
            const cb = el.querySelector('.bulk-cb');
            if (cb) cb.innerHTML = on ? '&#10003;' : '';
        });
    }

    function bulkDeselect() {
        bulkSelected.clear();
        bulkLastClickedId = null;
        updateBulkUI();
    }

    async function bulkMoveSelected(folderKey) {
        if (!bulkSelected.size || !folderKey) return;
        const folder = folderKey === '__uncategorized' ? null : folderKey;
        try {
            const res = await fetch('/marketing/planner/api/media/bulk-move', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ ids: Array.from(bulkSelected), folder }),
            });
            if (!res.ok) throw new Error((await res.json()).error || res.statusText);
            const data = await res.json();
            bulkDeselect();
            refreshMedia();
            refreshFolders();
            toast(`${data.updated || 0} media moved`);
        } catch (e) { alert('Move failed: ' + e.message); }
    }

    async function bulkSetStageSelected(stage) {
        if (!bulkSelected.size || !stage) return;
        try {
            const res = await fetch('/marketing/planner/api/media/bulk-stage', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ ids: Array.from(bulkSelected), stage }),
            });
            if (!res.ok) throw new Error((await res.json()).error || res.statusText);
            const data = await res.json();
            bulkDeselect();
            refreshMedia();
            toast(`${data.updated || 0} media updated`);
        } catch (e) { alert('Stage change failed: ' + e.message); }
    }

    // ── Bulk link to product / collection ──
    let bulkLinkMode = null;   // 'product' | 'collection'
    let bulkLinkDebounce;

    function openBulkLinkProducts() {
        if (!bulkSelected.size) return;
        bulkLinkMode = 'product';
        document.getElementById('bulkLinkTitle').textContent = `Link ${bulkSelected.size} media to product`;
        document.getElementById('bulkLinkSearch').value = '';
        document.getElementById('bulkLinkSearch').placeholder = 'Search product…';
        document.getElementById('bulkLinkOverlay').classList.remove('hidden');
        document.getElementById('bulkLinkCard').classList.remove('hidden');
        setTimeout(() => document.getElementById('bulkLinkSearch').focus(), 40);
        refreshBulkLinkResults();
    }

    function openBulkLinkCollections() {
        if (!bulkSelected.size) return;
        bulkLinkMode = 'collection';
        document.getElementById('bulkLinkTitle').textContent = `Link ${bulkSelected.size} media to collection`;
        document.getElementById('bulkLinkSearch').value = '';
        document.getElementById('bulkLinkSearch').placeholder = 'Search collection…';
        document.getElementById('bulkLinkOverlay').classList.remove('hidden');
        document.getElementById('bulkLinkCard').classList.remove('hidden');
        setTimeout(() => document.getElementById('bulkLinkSearch').focus(), 40);
        refreshBulkLinkResults();
    }

    function closeBulkLink() {
        document.getElementById('bulkLinkOverlay').classList.add('hidden');
        document.getElementById('bulkLinkCard').classList.add('hidden');
        bulkLinkMode = null;
    }

    async function refreshBulkLinkResults() {
        clearTimeout(bulkLinkDebounce);
        bulkLinkDebounce = setTimeout(async () => {
            const q = document.getElementById('bulkLinkSearch').value.trim();
            const out = document.getElementById('bulkLinkResults');
            out.innerHTML = '<div class="text-xs text-slate-400 p-3">Loading…</div>';
            try {
                if (bulkLinkMode === 'product') {
                    const res = await fetch('{{ route("marketing.planner.api.media.products.search") }}?q=' + encodeURIComponent(q));
                    const { results } = await res.json();
                    out.innerHTML = (results || []).slice(0, 30).map(r => {
                        const id = r.id ?? r.item_group_id;
                        const code = r.code ?? r.item_group_code ?? '';
                        const name = r.name ?? r.title ?? '';
                        return `<div class="px-3 py-2 rounded-md hover:bg-primary-50 cursor-pointer text-sm" onclick="applyBulkLinkProduct(${id})">
                            <span class="font-semibold text-slate-700">${escHtml(code)}</span>
                            <span class="text-slate-500"> ${escHtml(name)}</span>
                        </div>`;
                    }).join('') || '<div class="text-xs text-slate-400 p-3">Nuk u gjet asgje.</div>';
                } else {
                    const res = await fetch('{{ route("marketing.planner.api.media.collections.recent") }}');
                    const { collections } = await res.json();
                    const filtered = (collections || []).filter(c => !q || String(c.label || c.name || c.display_label || '').toLowerCase().includes(q.toLowerCase())).slice(0, 40);
                    out.innerHTML = filtered.map(c => {
                        const id = c.id;
                        const label = c.display_label || c.label || c.name || ('Week #' + id);
                        const start = c.start_date || c.starts_at || '';
                        return `<div class="px-3 py-2 rounded-md hover:bg-primary-50 cursor-pointer text-sm" onclick="applyBulkLinkCollection(${id})">
                            <div class="font-medium text-slate-700">${escHtml(label)}</div>
                            ${start ? `<div class="text-[10px] text-slate-400">${escHtml(String(start).slice(0, 10))}</div>` : ''}
                        </div>`;
                    }).join('') || '<div class="text-xs text-slate-400 p-3">Nuk u gjet asgje.</div>';
                }
            } catch (e) {
                out.innerHTML = '<div class="text-xs text-red-500 p-3">Ngarkimi dështoi.</div>';
            }
        }, 200);
    }

    async function applyBulkLinkProduct(productId) {
        const ids = Array.from(bulkSelected);
        try {
            const res = await fetch('/marketing/planner/api/media/bulk-products', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ ids, item_group_ids: [Number(productId)] }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            closeBulkLink();
            bulkDeselect();
            refreshMedia();
            refreshProductsSection();
            toast(`${data.inserted || 0} link(s) added`);
        } catch (e) { alert('Link failed: ' + e.message); }
    }

    async function applyBulkLinkCollection(collectionId) {
        const ids = Array.from(bulkSelected);
        try {
            const res = await fetch('/marketing/planner/api/media/bulk-collections', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ ids, distribution_week_ids: [Number(collectionId)] }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            closeBulkLink();
            bulkDeselect();
            refreshMedia();
            refreshCollectionsSection();
            toast(`${data.inserted || 0} link(s) added`);
        } catch (e) { alert('Link failed: ' + e.message); }
    }

    async function bulkDeleteSelected() {
        if (!bulkSelected.size) return;
        if (!confirm(`Delete ${bulkSelected.size} file(s)? This cannot be undone.`)) return;
        const ids = Array.from(bulkSelected);
        let ok = 0;
        for (const id of ids) {
            try {
                const res = await fetch(`/marketing/planner/api/media/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                if (res.ok) ok++;
            } catch (e) { /* continue */ }
        }
        bulkDeselect();
        refreshMedia();
        refreshFolders();
        toast(`${ok} media deleted`);
    }

    // Cmd/Ctrl+A → select all visible media
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'a' || e.key === 'A') && (e.ctrlKey || e.metaKey)) {
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            e.preventDefault();
            state_media_ids().forEach(id => bulkSelected.add(id));
            updateBulkUI();
        }
        if (e.key === 'Escape' && bulkSelected.size) bulkDeselect();
    });

    // Lightweight toast
    function toast(msg) {
        let t = document.getElementById('mediaToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'mediaToast';
            t.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;background:#111827;color:#fff;padding:10px 18px;border-radius:8px;font-size:12px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);opacity:0;transition:opacity 0.2s;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.opacity = '1';
        clearTimeout(t._to);
        t._to = setTimeout(() => { t.style.opacity = '0'; }, 2200);
    }

    // ── Drag-drop media → folder ──
    let dragMediaId = null;

    function onMediaDragStart(event, mediaId) {
        dragMediaId = mediaId;
        event.dataTransfer.effectAllowed = 'move';
        // If this media is part of a bulk selection (task #1339), drag moves
        // all selected items. The bulk handler in task #1339 owns the
        // `bulkSelected` Set; fall back to single id when it's not populated.
        const selected = (typeof bulkSelected !== 'undefined' && bulkSelected.has(mediaId))
            ? Array.from(bulkSelected)
            : [mediaId];
        event.dataTransfer.setData('application/x-media-ids', JSON.stringify(selected));
        event.currentTarget.classList.add('dragging');
    }

    function onMediaDragEnd(event) {
        event.currentTarget.classList.remove('dragging');
        dragMediaId = null;
        document.querySelectorAll('.folder-item.drop-target').forEach(el => el.classList.remove('drop-target'));
    }

    function onFolderDragOver(event, folderKey) {
        if (dragMediaId === null) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.classList.add('drop-target');
    }

    function onFolderDragLeave(event, el) {
        el.classList.remove('drop-target');
    }

    async function onFolderDrop(event, folderKey) {
        event.preventDefault();
        event.currentTarget.classList.remove('drop-target');

        let ids = [];
        try {
            const raw = event.dataTransfer.getData('application/x-media-ids');
            if (raw) ids = JSON.parse(raw);
        } catch (e) { /* ignore */ }
        if (!ids.length && dragMediaId !== null) ids = [dragMediaId];
        if (!ids.length) return;

        // '__uncategorized' maps to null (no folder), named keys pass-through.
        const folder = folderKey === '__uncategorized' ? null : folderKey;

        try {
            if (ids.length === 1) {
                const res = await fetch(`/marketing/planner/api/media/${ids[0]}/folder`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ folder }),
                });
                if (!res.ok) throw new Error((await res.json()).error || res.statusText);
            } else {
                const res = await fetch('/marketing/planner/api/media/bulk-move', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ ids, folder }),
                });
                if (!res.ok) throw new Error((await res.json()).error || res.statusText);
            }
            refreshMedia();
            refreshFolders();
        } catch (e) {
            alert('Move failed: ' + e.message);
        }
    }

    // ── Link badges (products + collections) on thumbnails ──
    function collectionColor(id) {
        // Deterministic hue from id so the same collection always renders
        // with the same dot color. Avoid very-light hues so the dot stays
        // readable on white thumbs.
        const h = (Number(id) * 37) % 360;
        return `hsl(${h}, 65%, 50%)`;
    }

    function buildLinkBadges(m) {
        const productIds = Array.isArray(m.item_group_ids) ? m.item_group_ids : [];
        const collectionIds = Array.isArray(m.distribution_week_ids) ? m.distribution_week_ids : [];
        if (!productIds.length && !collectionIds.length) return '';

        const pills = [];

        // Show at most 2 product pills (first two), then '+N' for the rest.
        const shownProducts = productIds.slice(0, 2);
        const extraProducts = productIds.length - shownProducts.length;
        shownProducts.forEach(pid => {
            const label = productLabelCache.get(Number(pid));
            const short = label ? String(label).split(' ')[0] : ('#' + pid);
            const title = label || ('Product #' + pid);
            pills.push(`<span class="product-pill" title="${escHtml(title)}">${escHtml(short.slice(0, 7))}</span>`);
        });
        if (extraProducts > 0) {
            pills.push(`<span class="overflow-pill" title="${extraProducts} produkte te tjera">+${extraProducts}</span>`);
        }

        // Show at most 2 collection dots; '+N' for the rest.
        const shownCollections = collectionIds.slice(0, 2);
        const extraCollections = collectionIds.length - shownCollections.length;
        shownCollections.forEach(cid => {
            const label = collectionLabelCache.get(Number(cid)) || ('Collection #' + cid);
            pills.push(`<span class="collection-dot" style="background:${collectionColor(cid)}" title="${escHtml(label)}"></span>`);
        });
        if (extraCollections > 0) {
            pills.push(`<span class="overflow-pill" title="${extraCollections} koleksione te tjera">+${extraCollections}</span>`);
        }

        return `<div class="link-badges">${pills.join('')}</div>`;
    }

    // ── Collections + Products filters (Media Library v3) ──
    let currentProduct = Number(new URLSearchParams(window.location.search).get('product')) || null;
    let currentCollection = Number(new URLSearchParams(window.location.search).get('collection')) || null;
    const productLabelCache = new Map();     // id -> "FMP-001 Fund me pala"
    const collectionLabelCache = new Map();  // id -> "Week 17 — Summer"

    async function refreshCollectionsSection() {
        const section = document.getElementById('collectionsSection');
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.collections.recent") }}');
            const { collections } = await res.json();
            const filtered = (collections || []).filter(c => (c.media_count || 0) > 0).slice(0, 10);

            // Cache labels for use elsewhere (badges, pills)
            filtered.forEach(c => {
                const label = c.display_label || c.label || c.name || ('Week #' + c.id);
                collectionLabelCache.set(Number(c.id), label);
            });

            if (!filtered.length) {
                section.innerHTML = '<div class="folder-divider"></div><div class="ml-sidebar-heading">Collections</div><div class="text-[11px] text-slate-400 px-3 py-1">Ende asnjë.</div>';
                return;
            }

            const html = '<div class="folder-divider"></div><div class="ml-sidebar-heading">Collections</div><ul style="list-style:none;padding:0;margin:0;">' +
                filtered.map(c => {
                    const id = Number(c.id);
                    const label = c.display_label || c.label || c.name || ('Week #' + id);
                    const count = Number(c.media_count) || 0;
                    const active = currentCollection === id;
                    return `<li><button type="button" onclick="selectCollection(${id})" class="folder-item ${active ? 'active' : ''}" data-collection-id="${id}">
                        <span class="folder-icon">🎯</span>
                        <span class="folder-label">${escHtml(label)}</span>
                        <span class="folder-count">${count}</span>
                    </button></li>`;
                }).join('') + '</ul>';
            section.innerHTML = html;
        } catch (e) { console.error('collections section', e); }
    }

    async function refreshProductsSection() {
        const section = document.getElementById('productsSection');
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.products.top") }}');
            const { products } = await res.json();
            const list = (products || []).slice(0, 20);

            list.forEach(p => {
                const label = p.code ? (p.code + (p.name ? ' ' + p.name : '')) : ('#' + p.id);
                productLabelCache.set(Number(p.id), label);
            });

            if (!list.length) {
                section.innerHTML = '<div class="folder-divider"></div><div class="ml-sidebar-heading">Products</div><div class="text-[11px] text-slate-400 px-3 py-1">Ende asnjë.</div>';
                return;
            }

            const html = '<div class="folder-divider"></div><div class="ml-sidebar-heading">Products</div><ul style="list-style:none;padding:0;margin:0;">' +
                list.map(p => {
                    const id = Number(p.id);
                    const code = p.code || ('#' + id);
                    const name = p.name || '';
                    const count = Number(p.count) || 0;
                    const active = currentProduct === id;
                    return `<li><button type="button" onclick="selectProduct(${id})" class="folder-item ${active ? 'active' : ''}" data-product-id="${id}" title="${escHtml(name)}">
                        <span class="folder-icon">📦</span>
                        <span class="folder-label" style="font-size:11px;">${escHtml(code)}</span>
                        <span class="folder-count">${count}</span>
                    </button></li>`;
                }).join('') + '</ul>';
            section.innerHTML = html;
        } catch (e) { console.error('products section', e); }
    }

    function selectCollection(id) {
        currentCollection = currentCollection === id ? null : id;
        const url = new URL(window.location);
        if (currentCollection) url.searchParams.set('collection', currentCollection);
        else url.searchParams.delete('collection');
        window.history.replaceState({}, '', url);
        currentPage = 1;
        refreshMedia();
        refreshCollectionsSection();
    }

    function selectProduct(id) {
        currentProduct = currentProduct === id ? null : id;
        const url = new URL(window.location);
        if (currentProduct) url.searchParams.set('product', currentProduct);
        else url.searchParams.delete('product');
        window.history.replaceState({}, '', url);
        currentPage = 1;
        refreshMedia();
        refreshProductsSection();
    }

    // ── Stage filter ──
    let currentStage = new URLSearchParams(window.location.search).get('stage') || '';

    function renderStageFilter() {
        const section = document.getElementById('stageFilterSection');
        const items = [
            { key: '', label: 'All', cls: 'all' },
            { key: 'raw', label: 'Raw', cls: 'raw' },
            { key: 'edited', label: 'Edited', cls: 'edited' },
            { key: 'final', label: 'Final', cls: 'final' },
        ];
        section.innerHTML = `
            <div class="folder-divider"></div>
            <div class="ml-sidebar-heading">Stage</div>
            <ul style="list-style:none; padding:0; margin:0;">
                ${items.map(i => `
                    <li>
                        <button type="button" onclick="selectStage('${i.key}')" class="stage-filter-item ${currentStage === i.key ? 'active' : ''}" data-stage="${i.key}">
                            <span class="stage-filter-dot ${i.cls}"></span>
                            <span>${i.label}</span>
                        </button>
                    </li>
                `).join('')}
            </ul>`;
    }

    function selectStage(stage) {
        currentStage = stage;
        const url = new URL(window.location);
        if (stage) url.searchParams.set('stage', stage);
        else url.searchParams.delete('stage');
        window.history.replaceState({}, '', url);
        document.querySelectorAll('.stage-filter-item').forEach(b => b.classList.toggle('active', b.dataset.stage === stage));
        currentPage = 1;
        refreshMedia();
    }

    // ── Context menu (right-click on thumb → change stage) ──
    let ctxMediaId = null;

    function openCtxMenu(event, mediaId, currentStageValue) {
        event.preventDefault();
        ctxMediaId = mediaId;
        const menu = document.getElementById('ctxMenu');
        menu.querySelectorAll('.ctx-item').forEach(el => el.classList.toggle('active', el.dataset.stage === currentStageValue));
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const menuW = 200;
        const menuH = 140;
        let left = event.pageX;
        let top = event.pageY;
        if (left + menuW > vw - 12) left = vw - menuW - 12;
        if (top + menuH > vh - 12) top = vh - menuH - 12;
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.classList.add('active');
    }

    function closeCtxMenu() {
        document.getElementById('ctxMenu').classList.remove('active');
        ctxMediaId = null;
    }

    async function ctxSetStage(stage) {
        if (!ctxMediaId) return;
        const id = ctxMediaId;
        closeCtxMenu();
        try {
            const res = await fetch(`/marketing/planner/api/media/${id}/stage`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ stage }),
            });
            if (!res.ok) { alert('Stage change failed'); return; }
            refreshMedia();
        } catch (e) { alert('Stage change failed: ' + e.message); }
    }

    document.addEventListener('click', (e) => {
        const menu = document.getElementById('ctxMenu');
        if (menu.classList.contains('active') && !menu.contains(e.target)) {
            closeCtxMenu();
        }
    });

    // ── Used-by popover ──
    async function openUsedByPopover(mediaId, anchorEl, expectedCount) {
        const popover = document.getElementById('usedByPopover');
        const overlay = document.getElementById('usedByOverlay');
        const title = document.getElementById('usedByTitle');
        const list = document.getElementById('usedByList');

        title.textContent = `Used in ${expectedCount} post${expectedCount !== 1 ? 's' : ''}`;
        list.innerHTML = '<div class="ubp-empty">Loading…</div>';

        // Position popover smartly relative to anchor (badge)
        const rect = anchorEl.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const popW = 320;
        const popH = 400;
        let left = rect.left;
        let top = rect.bottom + 6;
        if (left + popW > vw - 12) left = vw - popW - 12;
        if (left < 12) left = 12;
        if (top + popH > vh - 12) top = Math.max(12, rect.top - popH - 6);
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';

        overlay.classList.add('active');
        popover.classList.add('active');

        try {
            const res = await fetch(`/marketing/planner/api/media/${mediaId}/used-by`);
            const { posts } = await res.json();
            if (!posts || !posts.length) {
                list.innerHTML = '<div class="ubp-empty">No posts found.</div>';
                return;
            }
            list.innerHTML = posts.map(p => {
                const date = p.scheduled_at ? new Date(p.scheduled_at).toLocaleDateString('sq-AL', {day:'2-digit', month:'short', year:'numeric'}) : 'No date';
                const thumb = p.thumbnail_url || '';
                const href = `/marketing/planner/list?highlight=${p.id}`;
                return `<a class="ubp-item" href="${escHtml(href)}" target="_blank" rel="noopener">
                    ${thumb ? `<img class="ubp-thumb" src="${escHtml(thumb)}" alt="" loading="lazy">` : '<div class="ubp-thumb"></div>'}
                    <div class="ubp-body">
                        <div class="ubp-caption">${escHtml(p.content_preview || '(no caption)')}</div>
                        <div class="ubp-meta">
                            <span class="ubp-status-dot" style="background:${escHtml(p.status_color || '#94a3b8')}"></span>
                            <span>${escHtml(p.status_label || '')}</span>
                            <span>·</span>
                            <span>${escHtml(date)}</span>
                        </div>
                    </div>
                </a>`;
            }).join('');
        } catch (e) {
            list.innerHTML = '<div class="ubp-empty">Failed to load.</div>';
        }
    }

    function closeUsedByPopover() {
        document.getElementById('usedByOverlay').classList.remove('active');
        document.getElementById('usedByPopover').classList.remove('active');
    }

    document.addEventListener('keydown',e=>{
        if(e.key==='Escape'){
            if(document.body.classList.contains('fie-editor-open'))closeImageEditor();
            else if(document.getElementById('usedByPopover').classList.contains('active'))closeUsedByPopover();
            else closePreview();
        }
    });
    document.addEventListener('DOMContentLoaded', () => { renderStageFilter(); refreshFolders(); refreshMedia(); renderUploadProductsPills(); refreshCollectionsSection(); refreshProductsSection(); });
</script>
@endsection
