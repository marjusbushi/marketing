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

    .mp-toolbar { padding: 10px 18px; display: flex; gap: 8px; align-items: center; border-bottom: 1px solid #f1f5f9; }
    .mp-search { flex: 1; max-width: 260px; height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; outline: none; }
    .mp-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
    .mp-stage-select { height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; background: #fff; outline: none; }

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

            document.getElementById('mp-search').value = '';
            document.getElementById('mp-stage').value = '';

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
