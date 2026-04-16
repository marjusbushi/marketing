@extends('_layouts.app', [
    'title'     => 'Content Planner — Feed',
    'pageTitle' => 'Content Planner',
])

@section('styles')
<style>
    /* Stories carousel — larger Planable-style cards */
    .stories-track { display: flex; gap: 12px; overflow-x: auto; padding: 4px 0; scroll-behavior: smooth; -ms-overflow-style: none; scrollbar-width: none; }
    .stories-track::-webkit-scrollbar { display: none; }
    .story-card { flex-shrink: 0; width: 150px; height: 230px; border-radius: 14px; overflow: hidden; position: relative; cursor: pointer; transition: transform 0.15s; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
    .story-card:hover { transform: scale(1.02); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .story-card img, .story-card video { width: 100%; height: 100%; object-fit: cover; }
    .story-card .story-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.03), rgba(0,0,0,0.4)); }
    .story-card .story-date { position: absolute; top: 10px; left: 10px; background: rgba(255,255,255,0.95); border-radius: 8px; padding: 4px 8px; text-align: center; line-height: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
    .story-card .story-platform { position: absolute; bottom: 10px; right: 10px; }
    .story-card .story-status { position: absolute; bottom: 10px; left: 10px; }
    .story-new { flex-shrink: 0; width: 150px; height: 230px; border-radius: 14px; border: 2px dashed #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
    .story-new:hover { border-color: #6366f1; background: #f5f3ff; }

    /* Feed — Planable-style: 3 columns, 4:5 uniform tiles */
    .feed-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; }
    .feed-tile { position: relative; overflow: hidden; cursor: pointer; background: #f1f5f9; aspect-ratio: 4/5; }
    .feed-tile img, .feed-tile video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .feed-tile .feed-hover { position: absolute; inset: 0; background: rgba(0,0,0,0.08); opacity: 0; transition: opacity 0.15s; }
    .feed-tile:hover .feed-hover { opacity: 1; }

    .sortable-ghost { opacity: 0.4; }
    .sortable-chosen { box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important; }

    /* Section header */
    .section-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 12px; }
    .section-title { font-size: 15px; font-weight: 600; color: #1e293b; letter-spacing: -0.01em; }
    .section-count { font-size: 12px; color: #94a3b8; font-weight: 500; margin-left: 8px; }
</style>
@endsection

@section('content')
<div class="space-y-4" style="max-width:680px; margin:0 auto;">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <div class="relative" id="feedFilterWrap">
                <button onclick="document.getElementById('feedFilterPanel').classList.toggle('hidden')" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:funnel" width="13"></iconify-icon> Filter
                </button>
                <div id="feedFilterPanel" class="hidden absolute top-full left-0 mt-1.5 w-[220px] bg-white rounded-xl border border-slate-200 shadow-xl z-50 p-3 space-y-2">
                    <select id="filterPlatform" class="w-full h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs" onchange="refreshGrid()">
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="tiktok">TikTok</option>
                        <option value="">All</option>
                    </select>
                </div>
            </div>
            <button onclick="syncFromMeta(this)" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-500 hover:bg-slate-50 transition-colors">
                <iconify-icon icon="heroicons-outline:arrow-path" width="13"></iconify-icon> Sync
            </button>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="openMediaPanel()" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-500 hover:bg-slate-50 transition-colors">
                <iconify-icon icon="heroicons-outline:photo" width="13"></iconify-icon> Media
            </button>
            <button onclick="openComposer()" class="inline-flex items-center gap-1.5 h-[30px] px-4 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors shadow-sm">
                <iconify-icon icon="heroicons-outline:pencil-square" width="13"></iconify-icon> Compose
            </button>
        </div>
    </div>

    {{-- Stories section --}}
    <div>
        <div class="section-header px-0">
            <div class="flex items-center">
                <span class="section-title">Stories</span>
                <button id="storiesToggle" onclick="toggleStories()" class="ml-1.5 text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-4 h-4 transition-transform" id="storiesChevron" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <span class="section-count" id="storiesCount"></span>
            </div>
            <div class="flex items-center gap-1.5">
                <button id="storiesScrollLeft" class="w-6 h-6 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                </button>
                <button id="storiesScrollRight" class="w-6 h-6 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                </button>
            </div>
        </div>
        <div id="storiesBody" class="pb-2">
            <div class="stories-track" id="storiesTrack">
                <div class="story-new" onclick="openComposer()">
                    <iconify-icon icon="heroicons-outline:plus" width="24" class="text-slate-400"></iconify-icon>
                    <span class="text-[11px] text-slate-500 font-medium">New story</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Feed section — natural aspect ratio, no placeholders --}}
    <div>
        <div id="feedContainer" class="feed-grid"></div>
        <div id="feedEmpty" class="hidden py-10 text-center">
            <iconify-icon icon="heroicons-outline:photo" width="32" class="text-slate-200 mx-auto block mb-2"></iconify-icon>
            <p class="text-sm text-slate-400 mb-3">No posts yet</p>
            <button onclick="openComposer()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors shadow-sm">
                <iconify-icon icon="heroicons-outline:plus" width="14"></iconify-icon> Create Post
            </button>
        </div>
    </div>
</div>

{{-- Reorder confirm --}}
<div id="reorderConfirmOverlay" class="hidden fixed inset-0 bg-black/40 z-[9996]" onclick="closeReorderConfirm(); refreshGrid();"></div>
<div id="reorderConfirmModal" class="hidden fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] bg-white rounded-xl shadow-2xl z-[9997] overflow-hidden">
    <div class="p-5">
        <h3 class="text-[15px] font-bold text-slate-900 mb-2">Update Schedule?</h3>
        <p class="text-sm text-slate-500 mb-4">Swap scheduled times to match the new order?</p>
        <div class="flex gap-2 justify-end">
            <button id="reorderConfirmCancel" class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-500 hover:bg-slate-50">Cancel</button>
            <button id="reorderConfirmNo" class="px-3 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">Reorder Only</button>
            <button id="reorderConfirmYes" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">Swap Times</button>
        </div>
    </div>
</div>

@include('content-planner._partials.post-composer-modal')
@include('content-planner._partials.media-sidebar')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
    const statusColors = { draft:'#9CA3AF', pending_review:'#F59E0B', approved:'#3B82F6', scheduled:'#8B5CF6', published:'#10B981', failed:'#EF4444' };
    const statusLabels = { draft:'Draft', pending_review:'Review', approved:'Approved', scheduled:'Scheduled', published:'Published', failed:'Failed' };
    const platformIcons = { facebook:'logos:facebook', instagram:'skill-icons:instagram', tiktok:'logos:tiktok-icon' };
    let sortable;

    // Close filter on outside click
    document.addEventListener('click', function(e) {
        const w = document.getElementById('feedFilterWrap');
        const p = document.getElementById('feedFilterPanel');
        if (w && p && !w.contains(e.target)) p.classList.add('hidden');
    });

    // Stories scroll (both directions)
    document.getElementById('storiesScrollRight')?.addEventListener('click', () => {
        document.getElementById('storiesTrack').scrollBy({ left: 300, behavior: 'smooth' });
    });
    document.getElementById('storiesScrollLeft')?.addEventListener('click', () => {
        document.getElementById('storiesTrack').scrollBy({ left: -300, behavior: 'smooth' });
    });

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        const h = d.getHours().toString().padStart(2,'0');
        const m = d.getMinutes().toString().padStart(2,'0');
        return `${months[d.getMonth()]} ${d.getDate()} at ${h}:${m}, ${days[d.getDay()]}`;
    }

    function formatShortDate(dateStr) {
        if (!dateStr) return { day:'', month:'' };
        const d = new Date(dateStr);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return { day: d.getDate(), month: months[d.getMonth()] };
    }

    async function refreshGrid() {
        const platform = document.getElementById('filterPlatform').value;
        const params = new URLSearchParams();
        if (platform) params.set('platforms', platform);
        params.set('include_external', '1');
        try {
            const res = await fetch(`{{ route('marketing.planner.api.posts.index') }}?from=2020-01-01&to=2030-12-31&${params}`, { headers: { 'Accept': 'application/json' } });
            const ct = res.headers.get('Content-Type') || '';
            if (ct.includes('text/html')) { window.location.reload(); return; }
            const posts = await res.json();
            renderFeed(posts);
        } catch (e) { console.error(e); }
    }

    function renderFeed(posts) {
        posts.sort((a, b) => {
            const da = a.start ? new Date(a.start) : new Date(0);
            const db = b.start ? new Date(b.start) : new Date(0);
            return db - da;
        });

        // Separate stories from feed posts
        const storyPosts = posts.filter(p => {
            const props = p.extendedProps || {};
            return props.content_type === 'story' || props.media_type === 'story' || props.is_story === true;
        });
        const feedPosts = posts.filter(p => {
            const props = p.extendedProps || {};
            return !(props.content_type === 'story' || props.media_type === 'story' || props.is_story === true);
        });

        // Update counts
        document.getElementById('storiesCount').textContent = storyPosts.length ? `${storyPosts.length}` : '';
        const feedCountEl = document.getElementById('feedCount');
        if (feedCountEl) feedCountEl.textContent = feedPosts.length ? `${feedPosts.length} posts` : '';

        // ─── STORIES CAROUSEL ───
        const storiesTrack = document.getElementById('storiesTrack');
        storiesTrack.querySelectorAll('.story-card').forEach(el => el.remove());

        const storyItems = storyPosts.length ? storyPosts : posts.filter(p => p.extendedProps?.thumbnail).slice(0, 12);
        storyItems.forEach(p => {
            const props = p.extendedProps || {};
            const dt = formatShortDate(p.start);
            const pl = (props.platform_icons || [])[0] || '';
            const icon = platformIcons[pl] || '';
            const permalink = props.permalink || '';
            const sc = statusColors[props.status] || '';
            const sl = statusLabels[props.status] || '';
            const isExternal = props.is_external === true || props.is_imported === true;

            const card = document.createElement('div');
            card.className = 'story-card';
            card.onclick = function() {
                if (isExternal && permalink) window.open(permalink, '_blank');
                else openComposer(p.id);
            };
            card.innerHTML = `
                <img src="${props.thumbnail}" alt="" onerror="this.parentElement.style.background='#f1f5f9'">
                <div class="story-overlay"></div>
                <div class="story-date">
                    <div class="text-[15px] font-bold text-slate-900 leading-none">${dt.day}</div>
                    <div class="text-[10px] font-medium text-slate-500 mt-0.5">${dt.month}</div>
                </div>
            `;
            storiesTrack.appendChild(card);
        });

        // ─── FEED — natural aspect ratio, no placeholders ───
        const container = document.getElementById('feedContainer');
        const emptyEl = document.getElementById('feedEmpty');
        const displayPosts = feedPosts.length ? feedPosts : posts;

        if (!displayPosts.length) {
            container.innerHTML = '';
            emptyEl.classList.remove('hidden');
            return;
        }
        emptyEl.classList.add('hidden');

        container.innerHTML = displayPosts.map(event => {
            const p = event.extendedProps || {};
            const isExternal = p.is_external === true || p.is_imported === true;
            const sc = statusColors[p.status] || '#6B7280';
            const sl = statusLabels[p.status] || '';
            const thumb = p.thumbnail;
            const mediaUrl = p.first_media_url;
            const isVideo = p.is_video;
            const permalink = p.permalink || p.url || '';
            const dataAttr = isExternal ? 'data-external="1"' : `data-id="${event.id}"`;

            // Media — square crop via CSS
            let mediaHtml = '';
            if (thumb) {
                mediaHtml = `<img src="${thumb}" alt="" loading="lazy">`;
            } else if (isVideo && mediaUrl) {
                mediaHtml = `<video src="${mediaUrl}" muted preload="metadata"></video>`;
            } else if (mediaUrl) {
                mediaHtml = `<img src="${mediaUrl}" alt="" loading="lazy">`;
            } else {
                mediaHtml = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc;"><iconify-icon icon="heroicons-outline:photo" width="28" style="color:#e2e8f0;"></iconify-icon></div>`;
            }

            // Hover overlay — minimal
            const hoverHtml = `<div class="feed-hover"></div>`;

            const imgSrc = thumb || mediaUrl || '';
            const clickFn = isExternal && permalink
                ? `window.open('${permalink.replace(/'/g, "\\'")}','_blank')`
                : `openPostPreview(${event.id}, '${(imgSrc).replace(/'/g, "\\'")}', ${isVideo ? 'true' : 'false'}, '${(p.content || '').replace(/'/g, "\\'").replace(/\n/g, ' ').substring(0, 120)}')`;

            return `<div class="feed-tile" ${dataAttr} onclick="${clickFn}">
                ${mediaHtml}
                ${hoverHtml}
            </div>`;
        }).join('');

        // SortableJS on grid
        if (sortable) sortable.destroy();
        sortable = Sortable.create(container, {
            animation: 200, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', filter: '[data-external]',
            onEnd: function() {
                const orderedIds = [...container.querySelectorAll('[data-id]')].map(el => parseInt(el.dataset.id));
                if (orderedIds.length) showReorderConfirm(orderedIds);
            }
        });
    }

    function showReorderConfirm(orderedIds) {
        document.getElementById('reorderConfirmModal').classList.remove('hidden');
        document.getElementById('reorderConfirmOverlay').classList.remove('hidden');
        document.getElementById('reorderConfirmYes').onclick = () => { closeReorderConfirm(); saveGridOrder(orderedIds, true); };
        document.getElementById('reorderConfirmNo').onclick = () => { closeReorderConfirm(); saveGridOrder(orderedIds, false); };
        document.getElementById('reorderConfirmCancel').onclick = () => { closeReorderConfirm(); refreshGrid(); };
    }
    function closeReorderConfirm() {
        document.getElementById('reorderConfirmModal').classList.add('hidden');
        document.getElementById('reorderConfirmOverlay').classList.add('hidden');
    }

    async function saveGridOrder(orderedIds, swapTimes = false) {
        try {
            await fetch('{{ route("marketing.planner.api.posts.reorder") }}', {
                method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ ordered_ids: orderedIds, swap_times: swapTimes }),
            });
            if (swapTimes) refreshGrid();
        } catch (e) { console.error(e); }
    }

    async function syncFromMeta(btn) {
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<iconify-icon icon="heroicons-outline:arrow-path" width="14" class="animate-spin"></iconify-icon> Syncing...';
        try {
            const res = await fetch('{{ route("marketing.planner.api.posts.sync-meta") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
            const ct = res.headers.get('Content-Type') || '';
            if (ct.includes('text/html')) { window.location.reload(); return; }
            const data = await res.json();
            if (res.ok) { alert(`Imported ${data.facebook??0} FB + ${data.instagram??0} IG posts`); refreshGrid(); }
            else { alert('Sync failed: ' + (data.message || res.statusText)); }
        } catch (e) { alert('Sync failed: ' + e.message); }
        finally { btn.disabled = false; btn.innerHTML = origText; }
    }

    // Stories toggle collapse/expand
    function toggleStories() {
        const body = document.getElementById('storiesBody');
        const chevron = document.getElementById('storiesChevron');
        body.classList.toggle('hidden');
        chevron.style.transform = body.classList.contains('hidden') ? 'rotate(-90deg)' : '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('postPreviewOverlay').style.display === 'flex') closePostPreview();
        }
    });

    document.addEventListener('DOMContentLoaded', refreshGrid);

    // ─── Post Preview Overlay (Planable-style) ───
    let previewPostId = null;

    function openPostPreview(postId, mediaSrc, isVideo, caption) {
        previewPostId = postId;
        const overlay = document.getElementById('postPreviewOverlay');
        const media = document.getElementById('postPreviewMedia');

        if (isVideo && mediaSrc) {
            media.innerHTML = `<video src="${mediaSrc}" autoplay loop muted playsinline style="max-width:100%;max-height:80vh;object-fit:contain;border-radius:8px;display:block;"></video>`;
        } else if (mediaSrc) {
            media.innerHTML = `<img src="${mediaSrc}" alt="" style="max-width:100%;max-height:80vh;object-fit:contain;border-radius:8px;display:block;">`;
        } else {
            media.innerHTML = `<div style="width:300px;height:300px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;"><iconify-icon icon="heroicons-outline:photo" width="48" style="color:#cbd5e1;"></iconify-icon></div>`;
        }

        const captionEl = document.getElementById('postPreviewCaption');
        captionEl.textContent = caption || '';
        captionEl.style.display = caption ? '' : 'none';

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closePostPreview() {
        document.getElementById('postPreviewOverlay').style.display = 'none';
        document.body.style.overflow = '';
        const vid = document.querySelector('#postPreviewMedia video');
        if (vid) vid.pause();
        previewPostId = null;
    }

    function editFromPreview() {
        const id = previewPostId;
        closePostPreview();
        if (id) openComposer(id);
    }

    function downloadFromPreview() {
        const img = document.querySelector('#postPreviewMedia img');
        const vid = document.querySelector('#postPreviewMedia video');
        const src = img?.src || vid?.src;
        if (src) { const a = document.createElement('a'); a.href = src; a.download = ''; a.click(); }
    }
</script>

{{-- Post Preview Overlay --}}
<div id="postPreviewOverlay" style="position:fixed;inset:0;z-index:9980;background:rgba(0,0,0,0.75);display:none;flex-direction:column;">
    {{-- Toolbar --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;flex-shrink:0;">
        <div style="display:flex;align-items:center;gap:6px;">
            <button onclick="editFromPreview()" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'" title="Edit post">
                <iconify-icon icon="heroicons-outline:pencil-square" width="18" style="color:#fff;"></iconify-icon>
            </button>
            <button onclick="downloadFromPreview()" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'" title="Download">
                <iconify-icon icon="heroicons-outline:arrow-down-tray" width="18" style="color:#fff;"></iconify-icon>
            </button>
        </div>
        <button onclick="closePostPreview()" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'" title="Close">
            <iconify-icon icon="heroicons-outline:x-mark" width="20" style="color:#fff;"></iconify-icon>
        </button>
    </div>
    {{-- Media --}}
    <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:0 40px 20px;min-height:0;" onclick="closePostPreview()">
        <div id="postPreviewMedia" onclick="event.stopPropagation()" style="display:flex;align-items:center;justify-content:center;"></div>
    </div>
    {{-- Caption --}}
    <div style="padding:0 40px 20px;text-align:center;">
        <p id="postPreviewCaption" style="color:rgba(255,255,255,0.7);font-size:13px;margin:0;max-width:500px;display:inline-block;"></p>
    </div>
</div>
@endsection
