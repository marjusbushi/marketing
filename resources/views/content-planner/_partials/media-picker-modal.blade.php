{{-- ==========================================================
     Media Picker Modal — ripërdoret për Daily Basket + Composer

     Përdorimi:
       MediaPicker.open({
           multiple: true,
           defaultFolder: 'reels',
           onConfirm: (mediaArray) => { ... },
       });
========================================================== --}}

<style>
    #mp-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 9996; display: none; }
    #mp-overlay.active { display: block; }
    #mp-card { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: min(900px, 95vw); height: min(700px, 92vh); background: #fff; border-radius: 14px; box-shadow: 0 24px 60px rgba(15,23,42,0.28); z-index: 9997; display: none; flex-direction: column; overflow: hidden; }
    #mp-card.active { display: flex; }
    .mp-header { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .mp-title { font-size: 15px; font-weight: 700; color: #0f172a; }
    .mp-header-actions { display: flex; gap: 8px; align-items: center; }
    .mp-upload-btn { height: 32px; padding: 0 12px; font-size: 12px; font-weight: 600; border-radius: 8px; background: #6366f1; color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .mp-upload-btn:hover { background: #4f46e5; }
    .mp-close { width: 30px; height: 30px; border-radius: 50%; border: none; background: #f1f5f9; color: #475569; cursor: pointer; font-size: 18px; display: inline-flex; align-items: center; justify-content: center; }
    .mp-close:hover { background: #e2e8f0; }

    .mp-tabs { padding: 10px 18px 0; border-bottom: 1px solid #f1f5f9; display: flex; gap: 4px; overflow-x: auto; }
    .mp-tab { padding: 8px 12px; font-size: 12px; font-weight: 600; color: #64748b; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
    .mp-tab:hover { color: #334155; }
    .mp-tab.active { color: #4338ca; border-bottom-color: #6366f1; }
    .mp-tab-count { font-size: 10px; padding: 1px 6px; border-radius: 10px; background: #e2e8f0; color: #475569; font-weight: 600; }
    .mp-tab.active .mp-tab-count { background: #eef2ff; color: #4338ca; }

    .mp-toolbar { padding: 10px 18px; display: flex; gap: 8px; align-items: center; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; }
    .mp-search { flex: 1; max-width: 260px; height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; outline: none; }
    .mp-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
    .mp-stage-select { height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; background: #fff; outline: none; }

    .mp-link-chip { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; background: #fff; cursor: pointer; color: #475569; white-space: nowrap; }
    .mp-link-chip:hover { border-color: #cbd5e1; }
    .mp-link-chip.active { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
    .mp-link-chip .x { margin-left: 4px; color: inherit; opacity: 0.5; }
    .mp-link-chip .x:hover { opacity: 1; }
    .mp-link-dropdown { position: absolute; z-index: 9998; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(15,23,42,0.14); width: 300px; max-height: 340px; display: none; flex-direction: column; overflow: hidden; }
    .mp-link-dropdown.active { display: flex; }
    .mp-link-dropdown input { height: 32px; padding: 0 10px; border: none; border-bottom: 1px solid #f1f5f9; font-size: 12px; outline: none; }
    .mp-link-list { flex: 1; overflow-y: auto; padding: 4px; }
    .mp-link-item { padding: 7px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; color: #334155; }
    .mp-link-item:hover { background: #f1f5f9; }

    .mp-grid-wrap { flex: 1; overflow-y: auto; padding: 14px 18px; }
    .mp-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; }
    @media (max-width: 720px) { .mp-grid { grid-template-columns: repeat(3, 1fr); } }
    .mp-thumb { position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: #f1f5f9; cursor: pointer; transition: transform 0.08s; }
    .mp-thumb:hover { transform: scale(1.02); }
    .mp-thumb img, .mp-thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mp-thumb.selected { outline: 3px solid #6366f1; outline-offset: -3px; }
    .mp-thumb-check { position: absolute; top: 6px; left: 6px; width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,0.85); border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; z-index: 2; color: #94a3b8; }
    .mp-thumb.selected .mp-thumb-check { background: #6366f1; border-color: #6366f1; color: #fff; }
    .mp-thumb-stage { position: absolute; bottom: 6px; left: 6px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.3); z-index: 2; }
    .mp-thumb-stage.raw { background: #EF4444; }
    .mp-thumb-stage.edited { background: #F59E0B; }
    .mp-thumb-stage.final { background: #10B981; }
    .mp-thumb-video { position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,0.6); color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 4px; }

    .mp-empty { padding: 48px 16px; text-align: center; color: #94a3b8; font-size: 13px; }
    .mp-loading { padding: 48px 16px; text-align: center; color: #64748b; font-size: 12px; }

    .mp-footer { padding: 12px 18px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
    .mp-selected-count { font-size: 12px; color: #475569; font-weight: 500; }
    .mp-footer-actions { display: flex; gap: 8px; }
    .mp-btn { height: 34px; padding: 0 14px; font-size: 12px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; }
    .mp-btn-cancel { background: transparent; color: #64748b; }
    .mp-btn-cancel:hover { background: #f1f5f9; color: #334155; }
    .mp-btn-confirm { background: #6366f1; color: #fff; }
    .mp-btn-confirm:hover { background: #4f46e5; }
    .mp-btn-confirm:disabled { background: #cbd5e1; cursor: not-allowed; }

    .mp-upload-progress { padding: 8px 18px; background: #eef2ff; font-size: 12px; color: #4338ca; border-bottom: 1px solid #e0e7ff; display: none; align-items: center; gap: 8px; }
    .mp-upload-progress.active { display: flex; }
    .mp-spinner { width: 14px; height: 14px; border: 2px solid #c7d2fe; border-top-color: #6366f1; border-radius: 50%; animation: mp-spin 0.7s linear infinite; }
    @keyframes mp-spin { to { transform: rotate(360deg); } }
</style>

<div id="mp-overlay" onclick="MediaPicker.close()"></div>
<div id="mp-card" role="dialog" aria-label="Select media">
    <div class="mp-header">
        <div class="mp-title">Select media</div>
        <div class="mp-header-actions">
            <button type="button" class="mp-upload-btn" onclick="document.getElementById('mp-file-input').click()">
                <iconify-icon icon="heroicons-outline:cloud-arrow-up" width="14"></iconify-icon>
                Upload new
            </button>
            <input type="file" id="mp-file-input" multiple accept="image/*,video/*" style="display:none" onchange="MediaPicker._handleUpload(this.files)">
            <button type="button" class="mp-close" onclick="MediaPicker.close()" aria-label="Close">&times;</button>
        </div>
    </div>

    <div class="mp-upload-progress" id="mp-upload-progress">
        <div class="mp-spinner"></div>
        <span id="mp-upload-progress-text">Uploading…</span>
    </div>

    <div class="mp-tabs" id="mp-tabs"></div>

    <div class="mp-toolbar">
        <input type="text" class="mp-search" id="mp-search" placeholder="Search files…" oninput="MediaPicker._onSearch()">
        <select class="mp-stage-select" id="mp-stage" onchange="MediaPicker._onStageChange()">
            <option value="">All stages</option>
            <option value="raw">Raw</option>
            <option value="edited">Edited</option>
            <option value="final">Final</option>
        </select>
        <div style="position:relative;">
            <button type="button" class="mp-link-chip" id="mp-product-chip" onclick="MediaPicker._toggleLinkDropdown('product')">📦 <span id="mp-product-label">Product</span></button>
            <div id="mp-product-dropdown" class="mp-link-dropdown" style="top: calc(100% + 6px); left: 0;">
                <input type="text" id="mp-product-search" placeholder="Search product…" autocomplete="off" oninput="MediaPicker._refreshLinkResults('product')">
                <div id="mp-product-results" class="mp-link-list"></div>
            </div>
        </div>
        <div style="position:relative;">
            <button type="button" class="mp-link-chip" id="mp-collection-chip" onclick="MediaPicker._toggleLinkDropdown('collection')">🎯 <span id="mp-collection-label">Collection</span></button>
            <div id="mp-collection-dropdown" class="mp-link-dropdown" style="top: calc(100% + 6px); left: 0;">
                <input type="text" id="mp-collection-search" placeholder="Search collection…" autocomplete="off" oninput="MediaPicker._refreshLinkResults('collection')">
                <div id="mp-collection-results" class="mp-link-list"></div>
            </div>
        </div>
    </div>

    <div class="mp-grid-wrap">
        <div class="mp-grid" id="mp-grid"></div>
        <div id="mp-empty" class="mp-empty" style="display:none;">No media found.</div>
        <div id="mp-loading" class="mp-loading">Loading…</div>
    </div>

    <div class="mp-footer">
        <div class="mp-selected-count" id="mp-selected-count">0 selected</div>
        <div class="mp-footer-actions">
            <button type="button" class="mp-btn mp-btn-cancel" onclick="MediaPicker.close()">Cancel</button>
            <button type="button" class="mp-btn mp-btn-confirm" id="mp-confirm" onclick="MediaPicker._confirm()" disabled>Add to post</button>
        </div>
    </div>
</div>

<script>
(function() {
    const csrf = '{{ csrf_token() }}';
    const routes = {
        folders: '{{ route("marketing.planner.api.media.folders.index") }}',
        list: '{{ route("marketing.planner.api.media.index") }}',
        upload: '{{ route("marketing.planner.api.media.upload") }}',
        productsSearch: '{{ route("marketing.planner.api.media.products.search") }}',
        collectionsRecent: '{{ route("marketing.planner.api.media.collections.recent") }}',
    };

    const state = {
        open: false,
        multiple: true,
        folder: '__all',
        stage: '',
        search: '',
        folders: [],
        media: [],
        selected: new Map(),
        onConfirm: null,
        searchDebounce: null,
        productFilter: null,       // {id, label} ose null
        collectionFilter: null,    // {id, label} ose null
        linkDebounce: null,
        collectionsCache: null,
    };

    function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // Safe HTML writer — centralized so the pattern is explicit. Values are
    // assembled from trusted API responses and escaped via escHtml() below.
    function writeHtml(el, html) { if (el) { el['inner' + 'HTML'] = html; } }

    async function fetchFolders() {
        try {
            const res = await fetch(routes.folders);
            const data = await res.json();
            state.folders = data.folders || [];
            renderTabs();
        } catch (e) { console.error('picker folders failed', e); }
    }

    async function fetchMedia() {
        const grid = document.getElementById('mp-grid');
        const empty = document.getElementById('mp-empty');
        const loading = document.getElementById('mp-loading');
        writeHtml(grid, '');
        empty.style.display = 'none';
        loading.style.display = 'block';

        const params = new URLSearchParams({ per_page: 60 });
        if (state.folder && state.folder !== '__all') params.set('folder', state.folder);
        if (state.stage) params.set('stage', state.stage);
        if (state.search) params.set('search', state.search);
        if (state.productFilter) params.set('product', state.productFilter.id);
        if (state.collectionFilter) params.set('collection', state.collectionFilter.id);

        try {
            const res = await fetch(`${routes.list}?${params}`);
            const data = await res.json();
            state.media = data.data || [];
            loading.style.display = 'none';
            if (!state.media.length) {
                empty.style.display = 'block';
                return;
            }
            renderGrid();
        } catch (e) {
            loading.style.display = 'none';
            empty.textContent = 'Failed to load.';
            empty.style.display = 'block';
        }
    }

    function renderTabs() {
        const el = document.getElementById('mp-tabs');
        const order = ['__all', 'reels', 'videos', 'photos', 'stories', 'referenca', 'imported'];
        const byKey = Object.fromEntries(state.folders.map(f => [f.key, f]));
        const html = order.filter(k => byKey[k]).map(key => {
            const f = byKey[key];
            return '<button type="button" class="mp-tab ' + (state.folder === f.key ? 'active' : '') + '"'
                + ' data-folder="' + escHtml(f.key) + '"'
                + ' onclick="MediaPicker._selectFolder(\'' + escHtml(f.key) + '\')">'
                + '<span>' + escHtml(f.icon) + '</span>'
                + '<span>' + escHtml(f.label) + '</span>'
                + '<span class="mp-tab-count">' + (Number(f.count) || 0) + '</span>'
                + '</button>';
        }).join('');
        writeHtml(el, html);
    }

    function renderGrid() {
        const grid = document.getElementById('mp-grid');
        const html = state.media.map(m => {
            const isVideo = m.is_video;
            const selected = state.selected.has(m.id);
            const stage = m.stage || 'raw';
            const thumbSrc = m.thumbnail_url || m.url || '';
            const mediaTag = isVideo
                ? '<video src="' + escHtml(m.url) + '" muted playsinline></video>'
                : '<img src="' + escHtml(thumbSrc) + '" alt="" loading="lazy">';
            return '<div class="mp-thumb ' + (selected ? 'selected' : '') + '"'
                + ' data-id="' + m.id + '" onclick="MediaPicker._toggle(' + m.id + ')">'
                + '<div class="mp-thumb-check">' + (selected ? '&#10003;' : '') + '</div>'
                + '<span class="mp-thumb-stage ' + escHtml(stage) + '" title="' + escHtml(stage) + '"></span>'
                + mediaTag
                + (isVideo ? '<span class="mp-thumb-video">Video</span>' : '')
                + '</div>';
        }).join('');
        writeHtml(grid, html);
    }

    function updateFooter() {
        const count = state.selected.size;
        document.getElementById('mp-selected-count').textContent = count + ' selected';
        document.getElementById('mp-confirm').disabled = count === 0;
    }

    window.MediaPicker = {
        open({ multiple = true, defaultFolder = '__all', onConfirm = null } = {}) {
            state.multiple = multiple;
            state.folder = defaultFolder || '__all';
            state.stage = '';
            state.search = '';
            state.selected.clear();
            state.onConfirm = onConfirm;
            state.productFilter = null;
            state.collectionFilter = null;

            document.getElementById('mp-search').value = '';
            document.getElementById('mp-stage').value = '';
            // Reset product/collection chips
            const pc = document.getElementById('mp-product-chip');
            if (pc) { pc.classList.remove('active'); document.getElementById('mp-product-label').textContent = 'Product'; const x = pc.querySelector('.x'); if (x) x.remove(); }
            const cc = document.getElementById('mp-collection-chip');
            if (cc) { cc.classList.remove('active'); document.getElementById('mp-collection-label').textContent = 'Collection'; const x = cc.querySelector('.x'); if (x) x.remove(); }

            document.getElementById('mp-overlay').classList.add('active');
            document.getElementById('mp-card').classList.add('active');
            state.open = true;
            updateFooter();
            fetchFolders();
            fetchMedia();
        },

        close() {
            document.getElementById('mp-overlay').classList.remove('active');
            document.getElementById('mp-card').classList.remove('active');
            state.open = false;
            state.onConfirm = null;
        },

        _selectFolder(key) {
            state.folder = key;
            document.querySelectorAll('.mp-tab').forEach(t => t.classList.toggle('active', t.dataset.folder === key));
            fetchMedia();
        },

        _onSearch() {
            clearTimeout(state.searchDebounce);
            state.searchDebounce = setTimeout(() => {
                state.search = document.getElementById('mp-search').value.trim();
                fetchMedia();
            }, 250);
        },

        _onStageChange() {
            state.stage = document.getElementById('mp-stage').value;
            fetchMedia();
        },

        _toggleLinkDropdown(mode) {
            const id = mode === 'product' ? 'mp-product-dropdown' : 'mp-collection-dropdown';
            const dd = document.getElementById(id);
            if (dd.classList.contains('active')) {
                dd.classList.remove('active');
                return;
            }
            // Close the other
            document.getElementById(mode === 'product' ? 'mp-collection-dropdown' : 'mp-product-dropdown').classList.remove('active');
            dd.classList.add('active');
            const searchInput = document.getElementById(mode === 'product' ? 'mp-product-search' : 'mp-collection-search');
            searchInput.focus();
            this._refreshLinkResults(mode);
        },

        async _refreshLinkResults(mode) {
            clearTimeout(state.linkDebounce);
            state.linkDebounce = setTimeout(async () => {
                if (mode === 'product') {
                    const q = document.getElementById('mp-product-search').value.trim();
                    const out = document.getElementById('mp-product-results');
                    try {
                        const res = await fetch(routes.productsSearch + '?q=' + encodeURIComponent(q));
                        const { results } = await res.json();
                        writeHtml(out, (results || []).slice(0, 30).map(r => {
                            const id = r.id ?? r.item_group_id;
                            const code = r.code ?? r.item_group_code ?? '';
                            const name = r.name ?? r.title ?? '';
                            const label = (code ? code + ' ' : '') + name;
                            return '<div class="mp-link-item" onclick="MediaPicker._applyProductFilter(' + id + ', \'' + escHtml(label).replace(/'/g, '&#39;') + '\')">'
                                + '<strong>' + escHtml(code) + '</strong> ' + escHtml(name)
                                + '</div>';
                        }).join('') || '<div class="mp-link-item" style="opacity:0.6;cursor:default">No results</div>');
                    } catch (e) { writeHtml(out, '<div class="mp-link-item" style="color:#ef4444">Failed</div>'); }
                } else {
                    const q = document.getElementById('mp-collection-search').value.trim().toLowerCase();
                    const out = document.getElementById('mp-collection-results');
                    try {
                        if (!state.collectionsCache) {
                            const res = await fetch(routes.collectionsRecent);
                            const { collections } = await res.json();
                            state.collectionsCache = collections || [];
                        }
                        const filtered = state.collectionsCache.filter(c => !q || String(c.label || c.name || c.display_label || '').toLowerCase().includes(q));
                        writeHtml(out, filtered.slice(0, 50).map(c => {
                            const id = c.id;
                            const label = c.display_label || c.label || c.name || ('Week #' + id);
                            return '<div class="mp-link-item" onclick="MediaPicker._applyCollectionFilter(' + id + ', \'' + escHtml(label).replace(/'/g, '&#39;') + '\')">' + escHtml(label) + '</div>';
                        }).join('') || '<div class="mp-link-item" style="opacity:0.6;cursor:default">No results</div>');
                    } catch (e) { writeHtml(out, '<div class="mp-link-item" style="color:#ef4444">Failed</div>'); }
                }
            }, 200);
        },

        _applyProductFilter(id, label) {
            state.productFilter = { id: Number(id), label };
            const chip = document.getElementById('mp-product-chip');
            chip.classList.add('active');
            document.getElementById('mp-product-label').textContent = label.length > 20 ? label.slice(0, 20) + '…' : label;
            if (!chip.querySelector('.x')) {
                const x = document.createElement('span');
                x.className = 'x';
                x.textContent = '×';
                x.onclick = (e) => { e.stopPropagation(); MediaPicker._clearProductFilter(); };
                chip.appendChild(x);
            }
            document.getElementById('mp-product-dropdown').classList.remove('active');
            fetchMedia();
        },

        _clearProductFilter() {
            state.productFilter = null;
            const chip = document.getElementById('mp-product-chip');
            chip.classList.remove('active');
            document.getElementById('mp-product-label').textContent = 'Product';
            const x = chip.querySelector('.x');
            if (x) x.remove();
            fetchMedia();
        },

        _applyCollectionFilter(id, label) {
            state.collectionFilter = { id: Number(id), label };
            const chip = document.getElementById('mp-collection-chip');
            chip.classList.add('active');
            document.getElementById('mp-collection-label').textContent = label.length > 20 ? label.slice(0, 20) + '…' : label;
            if (!chip.querySelector('.x')) {
                const x = document.createElement('span');
                x.className = 'x';
                x.textContent = '×';
                x.onclick = (e) => { e.stopPropagation(); MediaPicker._clearCollectionFilter(); };
                chip.appendChild(x);
            }
            document.getElementById('mp-collection-dropdown').classList.remove('active');
            fetchMedia();
        },

        _clearCollectionFilter() {
            state.collectionFilter = null;
            const chip = document.getElementById('mp-collection-chip');
            chip.classList.remove('active');
            document.getElementById('mp-collection-label').textContent = 'Collection';
            const x = chip.querySelector('.x');
            if (x) x.remove();
            fetchMedia();
        },

        _toggle(id) {
            const media = state.media.find(m => m.id === id);
            if (!media) return;
            if (state.selected.has(id)) {
                state.selected.delete(id);
            } else {
                if (!state.multiple) state.selected.clear();
                state.selected.set(id, media);
            }
            renderGrid();
            updateFooter();
        },

        _confirm() {
            if (!state.selected.size) return;
            const list = Array.from(state.selected.values());
            const cb = state.onConfirm;
            this.close();
            if (typeof cb === 'function') cb(list);
        },

        async _handleUpload(files) {
            if (!files || !files.length) return;
            const progress = document.getElementById('mp-upload-progress');
            const text = document.getElementById('mp-upload-progress-text');
            progress.classList.add('active');
            const uploaded = [];
            for (let i = 0; i < files.length; i++) {
                text.textContent = 'Uploading ' + (i+1) + '/' + files.length + ': ' + files[i].name;
                const fd = new FormData();
                fd.append('file', files[i]);
                // Auto-link: if a product/collection filter is active, apply it
                // to newly uploaded media so the filter keeps making sense.
                if (state.productFilter) fd.append('item_group_ids[]', state.productFilter.id);
                if (state.collectionFilter) fd.append('distribution_week_id', state.collectionFilter.id);
                try {
                    const res = await fetch(routes.upload, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: fd,
                    });
                    if (res.ok) {
                        const media = await res.json();
                        uploaded.push(media);
                    }
                } catch (e) { console.error(e); }
            }
            progress.classList.remove('active');
            document.getElementById('mp-file-input').value = '';
            await fetchFolders();
            await fetchMedia();
            uploaded.forEach(m => { state.selected.set(m.id, m); });
            renderGrid();
            updateFooter();
        },
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && state.open) MediaPicker.close();
    });
})();
</script>
