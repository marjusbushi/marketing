{{-- Media Library Sidebar Panel — Planable style --}}
<div id="mediaSidebarPanel" style="display:none; position:fixed; top:0; right:0; bottom:0; width:360px; z-index:9985; background:#fff; border-left:1px solid #e5e7eb; box-shadow:-4px 0 24px rgba(0,0,0,0.08); font-family:Inter,system-ui,sans-serif; display:none; flex-direction:column;">

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0;">
        <span style="font-size:15px; font-weight:600; color:#1e293b;">Media library</span>
        <button onclick="closeMediaPanel()" style="width:28px; height:28px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
            <iconify-icon icon="heroicons-outline:x-mark" width="18" style="color:#94a3b8;"></iconify-icon>
        </button>
    </div>

    {{-- Folder tabs (Media Library v2) --}}
    <div id="mediaPanelFolders" style="display:flex; gap:4px; padding:8px 12px; border-bottom:1px solid #f1f5f9; overflow-x:auto; flex-shrink:0;">
        <span style="font-size:10px; color:#94a3b8; padding:6px 4px;">Loading folders…</span>
    </div>

    {{-- Upload + filter row --}}
    <div style="display:flex; align-items:center; gap:6px; padding:12px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0;">
        <button onclick="document.getElementById('mediaPanelFileInput').click()" style="display:inline-flex; align-items:center; gap:4px; height:28px; padding:0 10px; font-size:11px; font-weight:500; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">
            <iconify-icon icon="heroicons-outline:arrow-up-tray" width="13"></iconify-icon> Upload
        </button>
        <input id="mediaPanelFileInput" type="file" accept="image/*,video/*" multiple style="display:none;" onchange="handlePanelUpload(this.files); this.value='';">
        <div style="flex:1;"></div>
        <select id="mediaPanelStage" onchange="refreshMediaPanel()" style="height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:0 6px; font-size:11px; color:#64748b; background:#fff; cursor:pointer;" title="Filter by stage">
            <option value="">All stages</option>
            <option value="raw">🔴 Raw</option>
            <option value="edited">🟡 Edited</option>
            <option value="final">🟢 Final</option>
        </select>
        <select id="mediaPanelSort" onchange="refreshMediaPanel()" style="height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:0 6px; font-size:11px; color:#64748b; background:#fff; cursor:pointer;">
            <option value="latest">Latest first</option>
            <option value="oldest">Oldest first</option>
        </select>
        <button onclick="toggleMediaPanelFilter()" style="display:inline-flex; align-items:center; gap:3px; height:28px; padding:0 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer;">
            <iconify-icon icon="heroicons-outline:funnel" width="12"></iconify-icon> Filters
        </button>
    </div>

    {{-- Filter panel (hidden by default) --}}
    <div id="mediaPanelFilterRow" style="display:none; padding:8px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0;">
        <div style="display:flex; gap:6px;">
            <select id="mediaPanelType" onchange="refreshMediaPanel()" style="flex:1; height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:0 6px; font-size:11px; color:#64748b; background:#fff;">
                <option value="">All types</option>
                <option value="image">Images</option>
                <option value="video">Videos</option>
            </select>
            <input id="mediaPanelSearch" type="text" placeholder="Search..." oninput="debounceMediaPanel()" style="flex:1; height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:0 8px; font-size:11px; color:#374151; outline:none;">
        </div>
    </div>

    {{-- Upload progress --}}
    <div id="mediaPanelProgress" style="display:none; padding:8px 16px; flex-shrink:0;">
        <div style="display:flex; align-items:center; gap:8px; background:#eef2ff; border-radius:6px; padding:8px 12px;">
            <div style="width:14px; height:14px; border:2px solid #c7d2fe; border-top-color:#6366f1; border-radius:50%; animation:mp-spin 0.8s linear infinite;"></div>
            <span id="mediaPanelProgressText" style="font-size:11px; color:#4338ca; font-weight:500;">Uploading...</span>
        </div>
    </div>

    {{-- Media grid --}}
    <div id="mediaPanelGrid" style="flex:1; overflow-y:auto; padding:12px 16px;">
        <div style="text-align:center; padding:40px 0; color:#94a3b8; font-size:12px;">Loading...</div>
    </div>

    {{-- Load more --}}
    <div id="mediaPanelLoadMore" style="display:none; padding:8px 16px 12px; flex-shrink:0; text-align:center;">
        <button onclick="loadMoreMediaPanel()" style="height:28px; padding:0 16px; font-size:11px; font-weight:500; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer; width:100%;">Load more</button>
    </div>
</div>

<style>
@keyframes mp-spin { to { transform:rotate(360deg); } }
.mp-thumb { position:relative; aspect-ratio:1; overflow:hidden; border-radius:6px; cursor:pointer; background:#f1f5f9; }
.mp-thumb img, .mp-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
.mp-thumb:hover { outline:2px solid #6366f1; outline-offset:-2px; }
.mp-badge { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.6); color:#fff; font-size:9px; font-weight:600; padding:1px 5px; border-radius:4px; display:flex; align-items:center; gap:2px; }
.mp-stage { position:absolute; bottom:4px; left:4px; width:8px; height:8px; border-radius:50%; border:1.5px solid #fff; box-shadow:0 1px 2px rgba(0,0,0,0.3); }
.mp-stage.raw { background:#EF4444; }
.mp-stage.edited { background:#F59E0B; }
.mp-stage.final { background:#10B981; }
.mp-folder-pill { display:inline-flex; align-items:center; gap:3px; padding:4px 8px; font-size:10px; font-weight:600; color:#64748b; background:transparent; border:1px solid transparent; border-radius:999px; cursor:pointer; white-space:nowrap; transition:all 0.1s; }
.mp-folder-pill:hover { background:#f1f5f9; }
.mp-folder-pill.active { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
.mp-folder-pill-count { font-size:9px; color:#94a3b8; }
.mp-folder-pill.active .mp-folder-pill-count { color:#6366f1; }
</style>

<script>
(function() {
    let mpPage = 1;
    let mpItems = [];
    let mpDebounce;
    let mpFolder = '__all';
    const API_URL = @json(route('marketing.planner.api.media.index'));
    const UPLOAD_URL = @json(route('marketing.planner.api.media.upload'));
    const FOLDERS_URL = @json(route('marketing.planner.api.media.folders.index'));
    const CSRF = @json(csrf_token());

    function mpEsc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function mpSetHtml(el, html) { if (el) el['inner' + 'HTML'] = html; }

    window.openMediaPanel = function() {
        const panel = document.getElementById('mediaSidebarPanel');
        panel.style.display = 'flex';
        mpPage = 1;
        mpItems = [];
        refreshFolderTabs();
        refreshMediaPanel();
    };

    window.refreshFolderTabs = async function() {
        try {
            const res = await fetch(FOLDERS_URL);
            const { folders } = await res.json();
            const el = document.getElementById('mediaPanelFolders');
            const order = ['__all','reels','videos','photos','stories','referenca','imported'];
            const byKey = Object.fromEntries(folders.map(f => [f.key, f]));
            const html = order.filter(k => byKey[k]).map(key => {
                const f = byKey[key];
                return '<button type="button" class="mp-folder-pill ' + (mpFolder === f.key ? 'active' : '') + '" onclick="selectMediaPanelFolder(\'' + mpEsc(f.key) + '\')">'
                    + '<span>' + mpEsc(f.icon) + '</span>'
                    + '<span>' + mpEsc(f.label) + '</span>'
                    + '<span class="mp-folder-pill-count">' + (Number(f.count) || 0) + '</span>'
                    + '</button>';
            }).join('');
            mpSetHtml(el, html);
        } catch (e) { console.error('folders failed', e); }
    };

    window.selectMediaPanelFolder = function(key) {
        mpFolder = key;
        document.querySelectorAll('#mediaPanelFolders .mp-folder-pill').forEach((b, i) => {
            // Re-render pills to update active class; simpler than querying keys
        });
        refreshFolderTabs();
        mpPage = 1; mpItems = [];
        refreshMediaPanel();
    };

    window.closeMediaPanel = function() {
        document.getElementById('mediaSidebarPanel').style.display = 'none';
    };

    window.toggleMediaPanelFilter = function() {
        const row = document.getElementById('mediaPanelFilterRow');
        row.style.display = row.style.display === 'none' ? 'flex' : 'none';
    };

    window.debounceMediaPanel = function() {
        clearTimeout(mpDebounce);
        mpDebounce = setTimeout(() => { mpPage = 1; mpItems = []; refreshMediaPanel(); }, 300);
    };

    window.refreshMediaPanel = function() {
        if (mpPage === 1) mpItems = [];
        const params = new URLSearchParams({ page: mpPage, per_page: 24 });
        const type = document.getElementById('mediaPanelType')?.value;
        const search = document.getElementById('mediaPanelSearch')?.value;
        const sort = document.getElementById('mediaPanelSort')?.value;
        const stage = document.getElementById('mediaPanelStage')?.value;
        if (type) params.set('type', type);
        if (search) params.set('search', search);
        if (sort === 'oldest') params.set('sort', 'oldest');
        if (stage) params.set('stage', stage);
        if (mpFolder && mpFolder !== '__all') params.set('folder', mpFolder);

        fetch(API_URL + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                const items = data.data || [];
                if (mpPage === 1) mpItems = items;
                else mpItems = mpItems.concat(items);
                renderMediaPanel();
                // Load more button
                const btn = document.getElementById('mediaPanelLoadMore');
                btn.style.display = data.current_page < data.last_page ? 'block' : 'none';
            })
            .catch(e => {
                document.getElementById('mediaPanelGrid').innerHTML = '<div style="text-align:center;padding:24px;color:#ef4444;font-size:12px;">Failed to load media</div>';
            });
    };

    window.loadMoreMediaPanel = function() {
        mpPage++;
        refreshMediaPanel();
    };

    function renderMediaPanel() {
        const grid = document.getElementById('mediaPanelGrid');
        if (!mpItems.length) {
            grid.innerHTML = '<div style="text-align:center;padding:40px 0;color:#94a3b8;font-size:12px;"><iconify-icon icon="heroicons-outline:photo" width="28" style="display:block;margin:0 auto 6px;color:#cbd5e1;"></iconify-icon>No media yet</div>';
            return;
        }
        const html = '<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:6px;">' +
            mpItems.map(m => {
                const postCount = m.posts_count ?? m.posts?.length ?? 0;
                const badge = postCount > 0 ? `<span class="mp-badge"><iconify-icon icon="heroicons-outline:document" width="8"></iconify-icon>${postCount} Post${postCount > 1 ? 's' : ''}</span>` : '';
                const stage = mpEsc(m.stage || 'raw');
                const stageDot = `<span class="mp-stage ${stage}" title="${stage}"></span>`;
                const media = m.is_video
                    ? `<video src="${mpEsc(m.url)}" muted autoplay loop playsinline></video>`
                    : `<img src="${mpEsc(m.thumbnail_url || m.url)}" alt="" loading="lazy">`;
                return `<div class="mp-thumb" onclick="selectMediaFromPanel(${m.id})" title="${mpEsc(m.original_filename || '')}">
                    ${media}${badge}${stageDot}
                    <div style="position:absolute;bottom:0;left:0;right:0;padding:3px 5px;background:linear-gradient(transparent,rgba(0,0,0,0.5));opacity:0;transition:opacity 0.15s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                        <span style="font-size:9px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;">${mpEsc(m.original_filename || '')}</span>
                    </div>
                </div>`;
            }).join('') + '</div>';
        mpSetHtml(grid, html);
    }

    window.selectMediaFromPanel = function(mediaId) {
        const media = mpItems.find(m => m.id === mediaId);
        if (!media) return;
        // If composer is open, add media to it
        if (typeof composerState !== 'undefined' && document.getElementById('postComposerOverlay')?.style.display === 'block') {
            if (!composerState.mediaIds.includes(media.id)) {
                composerState.mediaIds.push(media.id);
                composerState.mediaItems.push(media);
                addMediaToComposer(media);
            }
        } else {
            // Open composer with this media
            openComposer();
            setTimeout(() => {
                composerState.mediaIds.push(media.id);
                composerState.mediaItems.push(media);
                addMediaToComposer(media);
            }, 100);
        }
    };

    window.handlePanelUpload = async function(files) {
        const progress = document.getElementById('mediaPanelProgress');
        const progressText = document.getElementById('mediaPanelProgressText');
        progress.style.display = 'block';
        for (let i = 0; i < files.length; i++) {
            progressText.textContent = `Uploading ${i + 1}/${files.length}...`;
            const formData = new FormData();
            formData.append('file', files[i]);
            try {
                await fetch(UPLOAD_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: formData });
            } catch (e) { console.error(e); }
        }
        progress.style.display = 'none';
        mpPage = 1; mpItems = [];
        refreshFolderTabs();
        refreshMediaPanel();
    };
})();
</script>
