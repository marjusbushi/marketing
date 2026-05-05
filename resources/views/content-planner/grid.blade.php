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
    /* Delete affordance — gjithmone i dukshem qe butoni te jete i arritshem
       edhe ne mobile (pa hover) dhe diskutueshem ne desktop pa pasur nevoje
       te kalosh maus-in mbi kartele. Shfaqet vetem per story-t pre-skedulim
       (status draft/pending/approved) dhe asnjehere per ato te importuara
       nga Meta -- kontrolli gating eshte ne JS te canDelete. */
    .story-card .story-del {
        position: absolute; top: 8px; right: 8px;
        width: 24px; height: 24px; border-radius: 50%;
        border: none; background: rgba(255,255,255,0.92);
        color: #475569; font-size: 15px; line-height: 1;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; opacity: 1;
        transition: background 0.15s, color 0.15s, transform 0.1s;
        box-shadow: 0 1px 4px rgba(0,0,0,0.18);
    }
    .story-card .story-del:hover { background: #fee2e2; color: #dc2626; transform: scale(1.05); }
    .story-new { flex-shrink: 0; width: 150px; height: 230px; border-radius: 14px; border: 2px dashed #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
    .story-new:hover { border-color: #6366f1; background: #f5f3ff; }

    /* Feed — Planable-style: 3 columns, 4:5 uniform tiles */
    .feed-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; }
    .feed-tile { position: relative; overflow: hidden; cursor: pointer; background: #f1f5f9; aspect-ratio: 4/5; }
    .feed-tile img, .feed-tile video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .feed-tile .feed-hover { position: absolute; inset: 0; background: rgba(0,0,0,0.08); opacity: 0; transition: opacity 0.15s; }
    .feed-tile:hover .feed-hover { opacity: 1; }

    /* Carousel/video indicators — Instagram-style ne kend te djathtë lart */
    .feed-tile-badge {
        position: absolute; top: 6px; right: 6px; z-index: 2;
        background: rgba(0,0,0,0.55); color: #fff;
        border-radius: 4px; padding: 2px 6px;
        font-size: 10px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 3px;
        backdrop-filter: blur(4px);
    }
    .feed-tile-badge iconify-icon { width: 10px; height: 10px; }

    /* Daily-basket draft tiles — stage badge (top-left) + status outline */
    .stage-badge {
        position: absolute; top: 6px; left: 6px; z-index: 2;
        padding: 2px 7px; border-radius: 999px;
        font-size: 9px; font-weight: 700; color: #fff;
        text-transform: uppercase; letter-spacing: 0.04em;
        backdrop-filter: blur(4px);
    }
    .stage-badge.stage-planning      { background: #94a3b8; }
    .stage-badge.stage-production    { background: #f59e0b; }
    .stage-badge.stage-editing       { background: #8b5cf6; }
    .stage-badge.stage-scheduling    { background: #3b82f6; }
    .stage-badge.stage-published     { background: #22c55e; }
    /* Status badges for scheduled ContentPost + external published posts,
       so every tile advertises its state (consistent with the legend). */
    .stage-badge.status-draft          { background: #9CA3AF; }
    .stage-badge.status-pending_review { background: #F59E0B; }
    .stage-badge.status-approved       { background: #3B82F6; }
    .stage-badge.status-scheduled      { background: #8B5CF6; }
    .stage-badge.status-publishing     { background: #06B6D4; }
    .stage-badge.status-published      { background: #10B981; }
    .stage-badge.status-failed         { background: #EF4444; }

    /* External (published) tiles show only a small dot — the caption-heavy
       IG/FB style looks cleaner without a text label. */
    .stage-dot {
        position: absolute; top: 8px; left: 8px; z-index: 2;
        width: 10px; height: 10px; border-radius: 999px;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.9);
    }
    .stage-dot.status-published { background: #10B981; }

    /* Compact legend above the feed grid */
    .feed-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 0 20px 10px; font-size: 11px; color: #94a3b8; }
    .feed-legend .legend-item { display: inline-flex; align-items: center; gap: 5px; }
    .feed-legend .legend-dot { width: 7px; height: 7px; border-radius: 999px; display: inline-block; }

    .sortable-ghost { opacity: 0.4; }
    .sortable-chosen { box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important; }

    /* Section header */
    .section-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 12px; }
    .section-title { font-size: 15px; font-weight: 600; color: #1e293b; letter-spacing: -0.01em; }
    .section-count { font-size: 12px; color: #94a3b8; font-weight: 500; margin-left: 8px; }

    /* Post Detail modal — 60/40 layout (mockup parity).
       Light theme inside a dark backdrop so metrics + caption read on white
       like the rest of Content Planner, not on the old black/fullscreen. */
    .pd-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9980; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
    .pd-backdrop.open { display: flex; }
    .pd-modal { background: #fff; border-radius: 12px; overflow: hidden; max-width: 1100px; width: 100%; max-height: 92vh; display: grid; grid-template-columns: 1.15fr 0.85fr; box-shadow: 0 40px 100px rgba(0,0,0,0.35); position: relative; }
    .pd-close { position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.92); border: 1px solid #e4e4e7; width: 32px; height: 32px; border-radius: 999px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; z-index: 20; color: #18181b; }
    .pd-close:hover { background: #fff; color: #dc2626; }

    /* LEFT: media */
    /* min-height:0 + max-height:inherit let the grid item cap at the modal's
       own max-height so the row cannot exceed the viewport constraint. */
    .pd-media { background: #0a0a0a; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; min-height: 0; max-height: inherit; }
    .pd-media-inner { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; }
    .pd-media-inner img, .pd-media-inner video { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
    .pd-type-chip { position: absolute; top: 14px; left: 14px; background: rgba(0,0,0,0.55); color: #fff; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 999px; display: flex; align-items: center; gap: 5px; z-index: 5; }
    .pd-carousel-arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.4); border: none; width: 34px; height: 34px; border-radius: 999px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; cursor: pointer; z-index: 5; }
    .pd-carousel-arrow.left { left: 12px; }
    .pd-carousel-arrow.right { right: 12px; }
    .pd-carousel-dots { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); display: flex; gap: 6px; z-index: 5; }
    .pd-dot { width: 7px; height: 7px; border-radius: 999px; background: rgba(255,255,255,0.5); }
    .pd-dot.active { background: #fff; }

    /* RIGHT: detail */
    /* Detail column caps at the modal's max-height so the flex column can
       hand a definite height to .pd-scroll; without this the scroll region
       grows to natural content height and the footer is clipped by the
       modal's own overflow:hidden when captions are long. */
    .pd-detail { display: flex; flex-direction: column; overflow: hidden; min-height: 0; max-height: inherit; }
    .pd-scroll { overflow-y: auto; flex: 1; min-height: 0; padding: 22px 22px 16px; }
    .pd-scroll::-webkit-scrollbar { width: 6px; }
    .pd-scroll::-webkit-scrollbar-thumb { background: #e4e4e7; border-radius: 99px; }

    .pd-head-row { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap; }
    .pd-platform-badge { font-size: 11px; font-weight: 600; padding: 4px 9px; border-radius: 5px; color: #fff; white-space: nowrap; }
    .pd-platform-badge.ig { background: linear-gradient(135deg, #f58529 0%, #dd2a7b 50%, #8134af 100%); }
    .pd-platform-badge.fb { background: #1877f2; }
    .pd-platform-badge.tt { background: #000; }
    .pd-platform-badge.multi { background: #6366f1; }
    .pd-meta-line { font-size: 12px; color: #a1a1aa; }
    .pd-status-tag { display: inline-block; font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; }
    .pd-status-tag.draft { background: #f4f4f5; color: #71717a; }
    .pd-status-tag.scheduled { background: #eef2ff; color: #4338ca; }
    .pd-status-tag.published { background: #f0fdf4; color: #16a34a; }

    .pd-section { margin-top: 18px; }
    .pd-section-label { font-size: 10px; font-weight: 600; letter-spacing: 0.12em; color: #a1a1aa; text-transform: uppercase; margin-bottom: 8px; }
    .pd-caption-box { font-size: 13px; line-height: 1.55; color: #18181b; white-space: pre-wrap; background: #fafafa; padding: 10px 12px; border-radius: 7px; border: 1px solid #e4e4e7; }
    /* Long captions (15+ rreshta) would push Performance/Detaje beneath the
       viewport. Cap height + internal scroll keeps metrics above the fold. */
    .pd-caption-wrap { position: relative; }
    .pd-caption-box.capped { max-height: 220px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #d4d4d8 transparent; }
    .pd-caption-box.capped::-webkit-scrollbar { width: 5px; }
    .pd-caption-box.capped::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 99px; }
    .pd-caption-fade { position: absolute; left: 0; right: 0; bottom: 0; height: 28px; background: linear-gradient(to bottom, rgba(250,250,250,0), #fafafa); pointer-events: none; border-bottom-left-radius: 7px; border-bottom-right-radius: 7px; display: none; }
    .pd-caption-wrap.is-capped .pd-caption-fade { display: block; }
    .pd-caption-hint { display: none; align-items: center; gap: 4px; font-size: 10px; color: #a1a1aa; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
    .pd-caption-hint::before { content: "\2193"; font-size: 11px; }
    .pd-caption-wrap.is-capped + .pd-caption-hint { display: inline-flex; }
    .pd-hashtag-row { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
    .pd-hashtag { background: rgba(109,40,217,0.1); color: #6d28d9; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
    /* Performance metrics — IG Insights mobile-style: list rows kompakte
       me ikone te vogel + label majtas, numer djathtas, ndarje subtile. */
    .pd-metrics { display: flex; flex-direction: column; gap: 0; }
    .pd-metric {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 2px;
        border-bottom: 1px solid #f1f5f9;
    }
    .pd-metric:last-child { border-bottom: none; }
    .pd-metric .label-group {
        display: flex; align-items: center; gap: 8px;
        min-width: 0; flex: 1;
    }
    .pd-metric .icon {
        flex-shrink: 0;
        width: 14px; height: 14px;
        display: flex; align-items: center; justify-content: center;
        color: #94a3b8;
    }
    .pd-metric .l {
        font-size: 12px; color: #475569; font-weight: 500;
        letter-spacing: 0; text-transform: none;
        margin: 0;
    }
    .pd-metric .v {
        font-size: 14px; font-weight: 700; color: #0f172a;
        letter-spacing: -0.01em; line-height: 1;
        font-feature-settings: 'tnum' 1, 'lnum' 1;
        flex-shrink: 0;
    }
    /* Engagement rate -- ndarje me theksuar, brand color ne numer. */
    .pd-metric.derived {
        margin-top: 4px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
        border-bottom: none;
    }
    .pd-metric.derived .l { color: #334155; font-weight: 600; }
    .pd-metric.derived .v { color: #6366f1; font-size: 15px; }
    .pd-metric.derived .icon { color: #6366f1; }
    .pd-kv-list { display: flex; flex-direction: column; gap: 6px; }
    .pd-kv { display: flex; justify-content: space-between; font-size: 12px; padding: 6px 0; border-bottom: 1px dashed #e4e4e7; }
    .pd-kv:last-child { border-bottom: none; }
    .pd-kv .k { color: #a1a1aa; }
    .pd-kv .v { color: #18181b; font-weight: 500; }

    .pd-foot { border-top: 1px solid #e4e4e7; padding: 12px 22px; background: #fafafa; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
    .pd-btn { border: 1px solid #e4e4e7; background: #fff; color: #18181b; font-size: 12px; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
    .pd-btn:hover { border-color: #6d28d9; color: #6d28d9; }
    .pd-btn-primary { background: #6d28d9; color: #fff; border-color: #6d28d9; }
    .pd-btn-primary:hover { background: #5b21b6; color: #fff; }
    .pd-btn-ghost { background: transparent; border-color: transparent; }
    .pd-btn-ghost:hover { background: #f4f4f5; border-color: transparent; }
    .pd-btn-danger { color: #dc2626; }
    .pd-btn-danger:hover { border-color: #dc2626; color: #dc2626; }

    @media (max-width: 900px) {
      .pd-modal { grid-template-columns: 1fr; max-height: 98vh; }
      .pd-media { aspect-ratio: 4/5; }
    }
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
        <div class="feed-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#94a3b8"></span>Planifikim</span>
            <span class="legend-item"><span class="legend-dot" style="background:#f59e0b"></span>Prodhim</span>
            <span class="legend-item"><span class="legend-dot" style="background:#8b5cf6"></span>Editim</span>
            <span class="legend-item"><span class="legend-dot" style="background:#3b82f6"></span>Skedulim</span>
            <span class="legend-item"><span class="legend-dot" style="background:#22c55e"></span>Scheduled / Published</span>
        </div>
        <div id="feedContainer" class="feed-grid"></div>

        {{-- "Shfaq me shume" — Planable-style incremental reveal. The server
             returns the entire post set; we render in pages of FEED_PAGE_SIZE
             so tens of thousands of tiles don't hit the DOM at once. --}}
        <div id="feedLoadMoreWrap" class="hidden flex flex-col items-center gap-2 pt-6 pb-4">
            <div class="text-[11px] text-slate-400 font-medium" id="feedLoadMoreCount"></div>
            <button id="feedLoadMoreBtn"
                    class="inline-flex items-center gap-1.5 px-5 h-9 rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-primary-400 hover:text-primary-600 transition-colors shadow-sm">
                <iconify-icon icon="heroicons-outline:arrow-down-circle" width="14"></iconify-icon>
                <span id="feedLoadMoreLabel">Shfaq me shume</span>
            </button>
        </div>

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
@include('content-planner._partials.media-picker-modal')
@include('content-planner._partials.image-editor-modal')
@include('content-planner._partials.media-sidebar')
@include('content-planner._partials.post-retry-script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
    const statusColors = { draft:'#9CA3AF', pending_review:'#F59E0B', approved:'#3B82F6', scheduled:'#8B5CF6', publishing:'#06B6D4', published:'#10B981', failed:'#EF4444' };
    const statusLabels = { draft:'Draft', pending_review:'Review', approved:'Approved', scheduled:'Scheduled', publishing:'Publishing…', published:'Published', failed:'Failed' };
    const platformIcons = { facebook:'logos:facebook', instagram:'skill-icons:instagram', tiktok:'logos:tiktok-icon' };
    let sortable;

    // Client-side pagination state. The server returns every post in the
    // selected window (IG history can be thousands); we render in chunks so
    // scroll performance stays smooth. visibleCount is how many feed tiles
    // are currently in the DOM.
    const FEED_PAGE_SIZE = 48;
    let feedPostsCache = [];
    let visibleCount = 0;

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

    // Replace a broken img/video with a clean placeholder icon instead of the
    // browser's default broken-image symbol. Defined on window so the inline
    // `onerror` handlers rendered by refreshGrid() can call it.
    window.feedTileOnErr = function (el) {
        if (!el || !el.parentNode) return;
        el.onerror = null;
        const ph = document.createElement('div');
        ph.style.cssText = 'width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc;';
        const icon = document.createElement('iconify-icon');
        icon.setAttribute('icon', 'heroicons-outline:photo');
        icon.setAttribute('width', '28');
        icon.style.color = '#e2e8f0';
        ph.appendChild(icon);
        el.replaceWith(ph);
    };

    // Wrap IG/FB CDN URLs through our server-side proxy. These CDNs block
    // hotlinking (Referer check) and use expiring tokens, so direct use from
    // the browser fails. The proxy fetches server-side (no browser Referer)
    // and caches, giving us working images in the planner.
    const META_PROXY = @json(route('marketing.meta-image'));
    const META_HOSTS = ['cdninstagram.com', 'fbcdn.net', 'instagram.com', 'graph.facebook.com', 'lookaside.fbsbx.com'];
    function proxyMetaUrl(url) {
        if (!url || typeof url !== 'string') return url;
        // Already routed through us? Leave it alone.
        if (url.indexOf('/marketing/') === 0 || url.indexOf(location.origin + '/marketing/') === 0) {
            return url;
        }
        try {
            const u = new URL(url, location.origin);
            const isMeta = META_HOSTS.some(h => u.hostname === h || u.hostname.endsWith('.' + h));
            if (isMeta) {
                return META_PROXY + '?url=' + encodeURIComponent(url);
            }
        } catch (e) { /* malformed URL — pass through */ }
        return url;
    }

    // Fshin një story të paskeduluar. Click-u vjen nga butoni × te kartela
    // — stop-propagation-on që click-u të mos hapë composer-in. Refresh i grid-it
    // pas DELETE të suksesshëm e largon kartelën nga UI menjëherë.
    async function deleteStory(event, postId) {
        event.stopPropagation();
        if (!confirm('Të hiqet ky story?')) return;
        try {
            const url = `{{ url('/marketing/planner/api/posts') }}/${encodeURIComponent(postId)}`;
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            refreshGrid();
        } catch (err) {
            alert('Fshirja dështoi: ' + err.message);
        }
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

        // Cache the full feed set; we page through it client-side via the
        // "Shfaq me shume" button. Reset visible count so the new filter view
        // starts from the top (otherwise a shorter result set would still
        // show the load-more button pointing at stale indices).
        feedPostsCache = feedPosts;
        visibleCount = 0;

        // Update counts
        document.getElementById('storiesCount').textContent = storyPosts.length ? `${storyPosts.length}` : '';
        const feedCountEl = document.getElementById('feedCount');
        if (feedCountEl) feedCountEl.textContent = feedPosts.length ? `${feedPosts.length} posts` : '';

        // ─── STORIES CAROUSEL ───
        const storiesTrack = document.getElementById('storiesTrack');
        storiesTrack.querySelectorAll('.story-card').forEach(el => el.remove());

        // Stories shows ONLY posts explicitly marked as stories.
        // The previous fallback (show any post with a thumbnail when
        // there are no real stories) caused feed posts to leak into the
        // Stories strip — every post in the planner appeared in both
        // sections, which is what the user reported.
        const storyItems = storyPosts;
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
                <img src="${props.thumbnail}" alt="" loading="lazy" decoding="async" onerror="this.parentElement.style.background='#f1f5f9'">
                <div class="story-overlay"></div>
                <div class="story-date">
                    <div class="text-[15px] font-bold text-slate-900 leading-none">${dt.day}</div>
                    <div class="text-[10px] font-medium text-slate-500 mt-0.5">${dt.month}</div>
                </div>
            `;

            // Fshirja lejohet vetëm para skedulimit (draft/pending/approved) dhe
            // jo për story-t e importuara nga Meta (ato kthehen me sync, prandaj
            // fshirja lokale do bëhej e padobishme — do riimportohej).
            const canDelete = !isExternal && ['draft', 'pending_review', 'approved'].includes(props.status);
            if (canDelete) {
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'story-del';
                del.title = 'Hiq këtë story';
                del.textContent = '×';
                del.onclick = (e) => deleteStory(e, p.id);
                card.appendChild(del);
            }

            storiesTrack.appendChild(card);
        });

        // ─── FEED — natural aspect ratio, no placeholders ───
        const container = document.getElementById('feedContainer');
        const emptyEl = document.getElementById('feedEmpty');

        // When no feed posts exist but the broader list does (unlikely edge),
        // fall back to showing the raw posts so the grid isn't mysteriously empty.
        const fallbackList = feedPosts.length ? feedPosts : posts;
        if (!fallbackList.length) {
            container.innerHTML = '';
            emptyEl.classList.remove('hidden');
            hideLoadMore();
            return;
        }
        if (!feedPostsCache.length) {
            feedPostsCache = fallbackList;
        }
        emptyEl.classList.add('hidden');

        // Reset + render first page. renderFeedPage handles the Sortable rebind
        // so newly-appended tiles participate in drag-reorder.
        visibleCount = 0;
        renderFeedPage();
    }

    // Build HTML for a single feed tile. Extracted so both initial render
    // and "Shfaq me shume" use the exact same markup.
    function buildFeedTileHtml(event) {
        const p = event.extendedProps || {};
        const isExternal = p.is_external === true || p.is_imported === true;
        const isDraftBasket = p.is_draft_basket === true;
        const thumb = p.thumbnail;
        const mediaUrl = p.first_media_url;
        const isVideo = p.is_video;
        const permalink = p.permalink || p.url || '';
        const dataAttr = isExternal
            ? 'data-external="1"'
            : (isDraftBasket ? `data-basket-draft="1" data-id="${event.id}"` : `data-id="${event.id}"`);

        const placeholderHtml = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc;"><iconify-icon icon="heroicons-outline:photo" width="28" style="color:#e2e8f0;"></iconify-icon></div>`;

        // IG/FB CDN URLs routed through /marketing/meta-image (server proxy)
        // to sidestep hotlink protection + expiring tokens.
        const proxiedThumb = proxyMetaUrl(thumb);
        const proxiedMedia = proxyMetaUrl(mediaUrl);

        let mediaHtml = '';
        if (proxiedThumb) {
            mediaHtml = `<img src="${proxiedThumb}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="feedTileOnErr(this)">`;
        } else if (isVideo && proxiedMedia) {
            mediaHtml = `<video src="${proxiedMedia}" muted preload="metadata" onerror="feedTileOnErr(this)"></video>`;
        } else if (proxiedMedia) {
            mediaHtml = `<img src="${proxiedMedia}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="feedTileOnErr(this)">`;
        } else {
            mediaHtml = placeholderHtml;
        }

        let badgeHtml = '';
        const mediaCount = Number(p.media_count || 0);
        if (mediaCount > 1) {
            badgeHtml = `<span class="feed-tile-badge" title="${mediaCount} media">
                <iconify-icon icon="heroicons-outline:square-2-stack" width="10"></iconify-icon>${mediaCount}
            </span>`;
        } else if (isVideo || p.has_video) {
            badgeHtml = `<span class="feed-tile-badge" title="Video">
                <iconify-icon icon="heroicons-outline:play" width="10"></iconify-icon>
            </span>`;
        }
        mediaHtml += badgeHtml;

        // Status badge — every tile advertises its state so the legend at
        // the top of the grid actually maps to what the user sees. Three
        // cases: daily-basket drafts show their pipeline stage; external
        // (IG/FB/TikTok) always show "Publikuar"; planned ContentPosts
        // show their content_post.status.
        let stageBadgeHtml = '';
        let tileClasses = 'feed-tile';
        if (isDraftBasket && p.post_stage) {
            const stageClass = `stage-${p.post_stage}`;
            stageBadgeHtml = `<span class="stage-badge ${stageClass}">${p.status_label || p.post_stage}</span>`;
            tileClasses += ` is-draft-basket ${stageClass}`;
        } else if (isExternal) {
            stageBadgeHtml = `<span class="stage-dot status-published" title="Publikuar"></span>`;
        } else if (p.status) {
            const statusLabelsAL = { draft:'Draft', pending_review:'Rishikim', approved:'Aprovuar', scheduled:'Skeduluar', publishing:'Po publikohet…', published:'Publikuar', failed:'Dështoi' };
            stageBadgeHtml = `<span class="stage-badge status-${p.status}">${statusLabelsAL[p.status] || p.status}</span>`;
        }

        const hoverHtml = `<div class="feed-hover"></div>`;

        // Click routing: every tile (including daily-basket drafts) opens the
        // in-app preview modal. The cached extendedProps already carries
        // media_items + caption + status, so the modal renders without an
        // API roundtrip (openPostPreview short-circuits the fetch when the
        // cached entry is_draft_basket — same pattern as is_external).
        const clickFn = `openPostPreview('${String(event.id).replace(/'/g, "\\'")}')`;

        return `<div class="${tileClasses}" ${dataAttr} onclick="${clickFn}">
            ${stageBadgeHtml}
            ${mediaHtml}
            ${hoverHtml}
        </div>`;
    }

    // Deep-link to Shporta Ditore at the exact week+date, scrolling to the
    // post. The daily-basket SPA honors ?week=&date=&post= on load.
    function openBasketDraft(postId, weekId, basketDate) {
        if (!weekId || !basketDate || !postId) return;
        const base = '{{ route('marketing.daily-basket.index') }}';
        window.location.href = `${base}?week=${weekId}&date=${basketDate}&post=${postId}`;
    }

    // Render the first visibleCount+FEED_PAGE_SIZE tiles from feedPostsCache.
    // Re-renders the whole visible prefix each call to keep Sortable + ordering
    // logic simple (at 48-item pages this is cheap).
    function renderFeedPage() {
        const container = document.getElementById('feedContainer');
        const target = Math.min(visibleCount + FEED_PAGE_SIZE, feedPostsCache.length);
        if (target <= visibleCount) {
            hideLoadMore();
            return;
        }

        visibleCount = target;
        container.innerHTML = feedPostsCache.slice(0, visibleCount).map(buildFeedTileHtml).join('');

        if (sortable) sortable.destroy();
        sortable = Sortable.create(container, {
            animation: 200, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen',
            // Keep non-reorderable tiles static: external (published) and
            // basket drafts (not yet handed off to the planner).
            filter: '[data-external], [data-basket-draft]',
            onEnd: function() {
                const orderedIds = [...container.querySelectorAll('[data-id]:not([data-basket-draft]):not([data-external])')]
                    .map(el => parseInt(el.dataset.id))
                    .filter(n => Number.isFinite(n));
                if (orderedIds.length) showReorderConfirm(orderedIds);
            }
        });

        updateLoadMore();
    }

    function updateLoadMore() {
        const wrap = document.getElementById('feedLoadMoreWrap');
        const countEl = document.getElementById('feedLoadMoreCount');
        const labelEl = document.getElementById('feedLoadMoreLabel');
        const total = feedPostsCache.length;
        const remaining = total - visibleCount;
        if (remaining <= 0) { hideLoadMore(); return; }

        wrap.classList.remove('hidden');
        const nextBatch = Math.min(FEED_PAGE_SIZE, remaining);
        labelEl.textContent = `Shfaq ${nextBatch} me shume`;
        countEl.textContent = `${visibleCount} nga ${total} poste`;
    }

    function hideLoadMore() {
        document.getElementById('feedLoadMoreWrap').classList.add('hidden');
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
        // Full-history sync can walk hundreds of Graph API pages — the
        // "Syncing..." spinner plus explicit label manages expectations.
        btn.innerHTML = '<iconify-icon icon="heroicons-outline:arrow-path" width="14" class="animate-spin"></iconify-icon> Syncing historik...';
        try {
            // ?full=1 → walk up to ~5000 posts per source with no 30-day cutoff.
            // The server still stops at the end of pagination, so accounts with
            // fewer posts finish quickly.
            const res = await fetch('{{ route("marketing.planner.api.posts.sync-meta") }}?full=1', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
            const ct = res.headers.get('Content-Type') || '';
            if (ct.includes('text/html')) { window.location.reload(); return; }
            const data = await res.json();
            const fb = data.facebook ?? 0;
            const ig = data.instagram ?? 0;
            const total = fb + ig;
            if (!res.ok) {
                alert('Sync failed: ' + (data.message || res.statusText));
            } else if (total === 0 && Array.isArray(data.issues) && data.issues.length > 0) {
                alert((data.message || 'Imported 0 posts.') + '\n\n• ' + data.issues.join('\n• ') +
                    (data.hint ? '\n\n' + data.hint : ''));
            } else {
                alert('✓ Imported ' + fb + ' FB + ' + ig + ' IG posts');
                if (total > 0) refreshGrid();
            }
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

    document.addEventListener('DOMContentLoaded', function() {
        refreshGrid();
        // Planable-style "Shfaq me shume" — reveals the next FEED_PAGE_SIZE
        // tiles from the cached post set. No extra HTTP calls; everything
        // already loaded in feedPostsCache.
        const btn = document.getElementById('feedLoadMoreBtn');
        if (btn) btn.addEventListener('click', renderFeedPage);
    });

    // ─── Post Preview Overlay (Planable-style) ───
    //
    // Opens the post in a full-screen lightbox. When the post has multiple
    // media attached, builds a swipeable carousel (touch + mouse drag +
    // arrow buttons + arrow keys). The carousel mirrors the composer's
    // behaviour so the feel is the same across edit and view.
    let previewPostId = null;
    const preview = {
        media: [],       // [{ url, thumbnail_url, mime_type }]
        index: 0,
        dragging: false,
        startX: 0,
        currentDx: 0,
        width: 0,
        isVideoPost: false,  // true kur posti eshte Reel/Video (per play overlay)
        permalink: null,     // Instagram permalink per "Hap ne Instagram" button
    };

    async function openPostPreview(postId) {
        previewPostId = postId;
        preview.media = [];
        preview.index = 0;

        const overlay = document.getElementById('postPreviewOverlay');
        const mediaHost = document.getElementById('postPreviewMedia');

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Reset all the right-panel surfaces so a reopen never shows stale data.
        while (mediaHost.firstChild) mediaHost.removeChild(mediaHost.firstChild);

        const cached = Array.isArray(feedPostsCache)
            ? feedPostsCache.find(e => String(e.id) === String(postId))
            : null;

        // Optimistic render -- ngarkojmë thumbnail-in nga cache menjehere
        // qe modal-i te kete permbajtje vizuale ne <100ms. Pa kete, modal-i
        // kerce nga "Loading..." (i vogel) -> media reale, duke krijuar
        // flicker ne layout. Pas API response, render full do behet poshte
        // qe upgrade-on me video real / metrics fresh.
        if (cached && cached.extendedProps) {
            renderPreviewDetail(cached, !!cached.extendedProps.is_external);
        } else {
            const loader = document.createElement('div');
            loader.style.cssText = 'color:rgba(255,255,255,0.55);font-size:12px;';
            loader.textContent = 'Loading…';
            mediaHost.appendChild(loader);
        }

        try {
            if (cached && cached.extendedProps && (cached.extendedProps.is_external || cached.extendedProps.is_draft_basket)) {
                // External posts (already published on Meta) and daily-basket
                // drafts (not a ContentPost row) don't have a /api/posts/{id}
                // record to fetch — the cache is the only source of truth.
                return;
            }
            const url = `{{ url('/marketing/planner/api/posts') }}/${encodeURIComponent(postId)}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            // Upgrade nga thumbnail i shpejte -> video real + metrics te
            // freskët. Render-i i dyte zëvendëson media dhe paneli i djathtë.
            renderPreviewDetail(normalisePlanned(data, cached), false);
        } catch (e) {
            // Vetem nese s'kishim asgje te dukshme, shfaq error. Nese cache
            // tashmë rendoi thumbnail, lere te jete -- user-i sheh thumb dhe
            // metrika do mbeten ato qe kishte cache.
            if (!cached) {
                while (mediaHost.firstChild) mediaHost.removeChild(mediaHost.firstChild);
                const err = document.createElement('div');
                err.style.cssText = 'color:#fecaca;font-size:13px;';
                err.textContent = 'Nuk u ngarkua posti: ' + (e.message || 'unknown error');
                mediaHost.appendChild(err);
            } else {
                console.warn('Failed to upgrade post preview from API:', e);
            }
        }
    }

    // Converts the planned-post /api/posts/{id} response + the cached feed
    // event into the same shape that renderPreviewDetail consumes, so the
    // modal code below doesn't care whether a post is external or planned.
    function normalisePlanned(data, cached) {
        const p = (cached && cached.extendedProps) || {};
        const scheduled = data.scheduled_at || p.scheduled_at || null;
        const mediaItems = (data.media || []).map(m => ({
            url: m.url || m.thumbnail_url || '',
            thumbnail: m.thumbnail_url || m.url || '',
            is_video: (m.mime_type || '').startsWith('video/'),
        }));
        const hasVideo = mediaItems.some(m => m.is_video);
        return {
            id: (cached && cached.id) || data.id,
            extendedProps: {
                is_external: false,
                platform: p.platform || data.platform || 'multi',
                status: p.status || data.status || 'draft',
                status_label: p.status_label || data.status_label || 'Draft',
                content: data.content || p.content || '',
                media_items: mediaItems,
                media_count: mediaItems.length,
                is_video: hasVideo,
                has_video: hasVideo,
                // Posts e publikuar (qe kane platform_post_id) marrin metrics
                // nga meta_post_insights -- API e ka tashme te mbushur. Para
                // rregullimit, normalisePlanned i hidhte poshte (metrics:null).
                metrics: data.metrics || null,
                permalink: data.permalink || p.permalink || null,
                scheduled_at: scheduled,
                content_type: data.content_type || p.content_type || null,
                meta_post_type: data.meta_post_type || p.meta_post_type || null,
                // Per Reels te publikuar -- backend therret Meta Graph API
                // dhe kthen URL te fresket te video-s (cached 4h).
                video_url: data.video_url || null,
            },
        };
    }

    // Single render path for both external and planned posts. All data comes
    // from `event.extendedProps`; layout is the 60/40 modal defined in the
    // template below.
    function renderPreviewDetail(event, isExternal) {
        const p = event.extendedProps || {};

        // Media
        preview.media = (p.media_items || []).map(m => ({
            url: proxyMetaUrl(m.url || m.thumbnail || ''),
            thumbnail_url: proxyMetaUrl(m.thumbnail || m.url || ''),
            mime_type: m.is_video ? 'video/mp4' : 'image/jpeg',
        }));
        preview.index = 0;
        preview.isVideoPost = isVideoPost(p);
        preview.permalink = p.permalink || null;
        // Per Reels te publikuar, backend kthen URL te fresket te video-s
        // nga Meta Graph API. Nese e ka, slide-i i pare luan video native;
        // perndryshe biem ne fallback play-overlay -> hap permalink.
        preview.videoUrl = p.video_url || null;
        if (preview.videoUrl && preview.media.length > 0) {
            preview.media[0].url = preview.videoUrl;
            preview.media[0].mime_type = 'video/mp4';
        }
        renderPreviewCarousel();
        ensurePreviewWired();

        // Type chip (top-left of the media pane)
        const typeChip = document.getElementById('pdTypeChip');
        typeChip.textContent = formatMediaTypeLabel(p, preview.media.length);

        // Platform badge
        const badge = document.getElementById('pdPlatformBadge');
        const plat = (p.platform || 'multi').toLowerCase();
        badge.className = 'pd-platform-badge ' + (plat === 'instagram' ? 'ig' : plat === 'facebook' ? 'fb' : plat === 'tiktok' ? 'tt' : 'multi');
        badge.textContent = prettyPlatform(plat);

        // Meta line (date + post type)
        const metaLine = document.getElementById('pdMetaLine');
        const typeLabel = p.post_type_label || postTypeFromMedia(p);
        const when = formatWhen(event, p, isExternal);
        metaLine.textContent = typeLabel + (when ? ' · ' + when : '');

        // Status tag
        const statusEl = document.getElementById('pdStatusTag');
        const statusKey = isExternal ? 'published' : (p.status === 'scheduled' ? 'scheduled' : p.status === 'published' ? 'published' : 'draft');
        statusEl.className = 'pd-status-tag ' + statusKey;
        statusEl.textContent = isExternal ? 'Publikuar' : (p.status_label || 'Draft');

        // Caption (split out hashtags for the chip row)
        const rawCaption = p.content || '';
        const { body, hashtags } = splitCaptionHashtags(rawCaption);
        const captionEl = document.getElementById('postPreviewCaption');
        captionEl.textContent = body || '—';
        const tagsEl = document.getElementById('pdHashtagRow');
        while (tagsEl.firstChild) tagsEl.removeChild(tagsEl.firstChild);
        hashtags.forEach(t => {
            const el = document.createElement('span');
            el.className = 'pd-hashtag';
            el.textContent = t;
            tagsEl.appendChild(el);
        });
        applyCaptionCap();

        // Metrics — show all available. Conditionally render only metrics
        // qe kane vlere te ngarkuar (skip null/undefined) qe te shmangim
        // klutter te card-eve "—". Engagement rate llogaritet local nga
        // (likes + comments + shares + saves) / reach * 100.
        const metricsSec = document.getElementById('pdMetricsSection');
        const metricsGrid = document.getElementById('pdMetricsGrid');
        while (metricsGrid.firstChild) metricsGrid.removeChild(metricsGrid.firstChild);
        if (p.metrics) {
            metricsSec.style.display = '';
            const m = p.metrics;
            // Vetem metrikat strategjike per content marketing -- jo zhurma:
            //   - Reach: sizing audience (unique accounts)
            //   - Video views: KPI per Reels (vetem video)
            //   - Likes/Comments/Shares: engagement layer
            //   - Saves: intent me te lartë (klient potencial)
            //   - Engagement %: KPI derivuar, krahasueshem
            // U hoqen: Impressions (duplikate Reach), Plays (duplikate Video
            // views), Clicks (vague, jo actionable per organic posts).
            const candidates = [
                ['reach',        'Reach',        'discovery',  'heroicons-outline:eye'],
                ['video_views',  'Video views',  'video',      'heroicons-outline:play-circle'],
                ['likes',        'Likes',        'engagement', 'heroicons-outline:heart'],
                ['comments',     'Comments',     'engagement', 'heroicons-outline:chat-bubble-left-ellipsis'],
                ['shares',       'Shares',       'engagement', 'heroicons-outline:share'],
                ['saves',        'Saves',        'engagement', 'heroicons-outline:bookmark'],
            ];
            const cells = candidates
                .filter(([key]) => m[key] !== null && m[key] !== undefined)
                .map(([key, label, cat, icon]) => ({ v: fmtNum(m[key]), l: label, cat, icon }));

            // Engagement rate -- vetem kur reach > 0 dhe ka pakten nje sinjal engagement.
            const engagementSum = (m.likes || 0) + (m.comments || 0) + (m.shares || 0) + (m.saves || 0);
            if ((m.reach || 0) > 0 && engagementSum > 0) {
                const er = (engagementSum / m.reach) * 100;
                cells.push({ v: er.toFixed(1) + '%', l: 'Engagement', cat: 'derived', icon: 'heroicons-outline:bolt' });
            }

            cells.forEach(c => {
                const cell = document.createElement('div');
                cell.className = 'pd-metric ' + (c.cat || '');
                // Left side: icon + label
                const labelGroup = document.createElement('div');
                labelGroup.className = 'label-group';
                const iconWrap = document.createElement('span');
                iconWrap.className = 'icon';
                const iconEl = document.createElement('iconify-icon');
                iconEl.setAttribute('icon', c.icon);
                iconEl.setAttribute('width', '13');
                iconWrap.appendChild(iconEl);
                const l = document.createElement('div');
                l.className = 'l';
                l.textContent = c.l;
                labelGroup.appendChild(iconWrap);
                labelGroup.appendChild(l);
                // Right side: value
                const v = document.createElement('div');
                v.className = 'v';
                v.textContent = c.v;
                cell.appendChild(labelGroup);
                cell.appendChild(v);
                metricsGrid.appendChild(cell);
            });
        } else {
            metricsSec.style.display = 'none';
        }

        // Details kv list
        const kvList = document.getElementById('pdKvList');
        while (kvList.firstChild) kvList.removeChild(kvList.firstChild);
        const details = buildDetailsKv(event, p, isExternal);
        if (details.length > 0) {
            document.getElementById('pdDetailsSection').style.display = '';
            details.forEach(pair => {
                const row = document.createElement('div');
                row.className = 'pd-kv';
                const k = document.createElement('span'); k.className = 'k'; k.textContent = pair[0];
                const v = document.createElement('span'); v.className = 'v'; v.textContent = pair[1];
                row.appendChild(k); row.appendChild(v);
                kvList.appendChild(row);
            });
        } else {
            document.getElementById('pdDetailsSection').style.display = 'none';
        }

        // Footer actions
        const foot = document.getElementById('pdFooter');
        while (foot.firstChild) foot.removeChild(foot.firstChild);
        buildFooterActions(event, p, isExternal).forEach(a => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pd-btn' + (a.kind === 'primary' ? ' pd-btn-primary' : a.kind === 'ghost' ? ' pd-btn-ghost' : a.kind === 'danger' ? ' pd-btn-ghost pd-btn-danger' : '');
            btn.textContent = a.label;
            if (a.onClick) btn.addEventListener('click', a.onClick);
            foot.appendChild(btn);
        });
    }

    // Cap caption box height + show fade/hint only when content actually
    // overflows, so short captions keep the compact layout and long ones
    // stop pushing Performance/Detaje beneath the fold.
    function applyCaptionCap() {
        const wrap = document.getElementById('pdCaptionWrap');
        const box = document.getElementById('postPreviewCaption');
        if (!wrap || !box) return;
        wrap.classList.remove('is-capped');
        box.classList.remove('capped');
        requestAnimationFrame(() => {
            if (box.scrollHeight > 220) {
                box.classList.add('capped');
                wrap.classList.add('is-capped');
            }
        });
    }

    function splitCaptionHashtags(text) {
        if (!text) return { body: '', hashtags: [] };
        const lines = text.split(/\n+/);
        const tags = [];
        const bodyLines = [];
        lines.forEach(line => {
            const matches = line.match(/(^|\s)(#\w+)/g);
            if (matches && matches.join('').replace(/\s/g, '').length >= line.trim().length * 0.5) {
                // Line is mostly hashtags — extract them.
                (line.match(/#\w+/g) || []).forEach(t => tags.push(t));
            } else {
                bodyLines.push(line);
            }
        });
        return { body: bodyLines.join('\n').trim(), hashtags: Array.from(new Set(tags)) };
    }

    function prettyPlatform(plat) {
        return plat === 'instagram' ? 'Instagram'
            : plat === 'facebook' ? 'Facebook'
            : plat === 'tiktok' ? 'TikTok'
            : 'Multi';
    }

    // Detection prioritet: content_type / meta_post_type (vijne nga DB) jane
    // me te besueshme se mime_type i media-s, sepse ContentMedia per Reels
    // ruan thumbnail JPG (video file vete s'eshte downloaded).
    function isVideoPost(p) {
        const ct = (p.content_type || '').toLowerCase();
        const mt = (p.meta_post_type || '').toLowerCase();
        if (ct === 'reel' || ct === 'video') return true;
        if (mt === 'reel' || mt === 'video') return true;
        return !!(p.is_video || p.has_video);
    }

    function isCarouselPost(p, count) {
        const ct = (p.content_type || '').toLowerCase();
        const mt = (p.meta_post_type || '').toLowerCase();
        return ct === 'carousel' || mt === 'carousel_album' || count > 1;
    }

    function postTypeFromMedia(p) {
        if (isVideoPost(p)) return 'Reel';
        const count = Number(p.media_count || (p.media_items ? p.media_items.length : 0));
        if (isCarouselPost(p, count)) return 'Carousel';
        return 'Foto';
    }

    function formatMediaTypeLabel(p, count) {
        if (isVideoPost(p)) return 'Reel';
        if (isCarouselPost(p, count)) return 'Carousel · ' + count;
        return 'Foto';
    }

    function formatWhen(event, p, isExternal) {
        try {
            if (isExternal && event.start) {
                return new Date(event.start).toLocaleString('sq-AL', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            }
            if (p.scheduled_at) {
                return 'Skedulim: ' + new Date(p.scheduled_at).toLocaleString('sq-AL', { day: '2-digit', month: 'long', hour: '2-digit', minute: '2-digit' });
            }
        } catch (_) {}
        return '';
    }

    function fmtNum(n) {
        if (n === null || n === undefined) return '—';
        const num = Number(n) || 0;
        return num.toLocaleString('sq-AL');
    }

    function buildDetailsKv(event, p, isExternal) {
        const out = [];
        if (isExternal) {
            if (p.metrics && typeof p.metrics.engagement_rate === 'number') {
                out.push(['Engagement rate', p.metrics.engagement_rate.toFixed(2) + '%']);
            }
            if (p.metrics && (p.metrics.impressions || p.metrics.impressions === 0)) {
                out.push(['Impressions', fmtNum(p.metrics.impressions)]);
            }
            if (p.metrics && (p.metrics.saves || p.metrics.saves === 0) && p.metrics.shares) {
                // If Shares is in the grid, still show Saves here.
                out.push(['Saves', fmtNum(p.metrics.saves)]);
            }
        } else {
            if (p.user_name) out.push(['Krijoi', p.user_name]);
            if (p.platform_icons && p.platform_icons.length) {
                out.push(['Platforma', p.platform_icons.join(' + ')]);
            }
            if (p.priority) out.push(['Prioritet', p.priority]);
        }
        return out;
    }

    function buildFooterActions(event, p, isExternal) {
        if (isExternal) {
            const actions = [];
            if (p.permalink) {
                actions.push({
                    kind: 'ghost',
                    label: 'Kopjo link',
                    onClick: async () => {
                        try {
                            await navigator.clipboard.writeText(p.permalink);
                            toast('Link u kopjua');
                        } catch (_) {}
                    },
                });
                actions.push({
                    kind: 'secondary',
                    label: 'Hap në ' + prettyPlatform((p.platform || '').toLowerCase()),
                    onClick: () => window.open(p.permalink, '_blank', 'noopener'),
                });
            }
            return actions;
        }
        // Daily-basket drafts have their own edit surface (reference URL,
        // production notes, product links). The composer doesn't model
        // those, so route the edit back to /marketing/daily-basket where
        // the full flow lives. Loading the composer with a db_draft_* id
        // would 404 against /api/posts and surface as an empty editor.
        if (p.is_draft_basket) {
            return [
                {
                    kind: 'primary',
                    label: 'Edito te Shporta Ditore',
                    onClick: () => {
                        const base = '{{ route('marketing.daily-basket.index') }}';
                        const qs = new URLSearchParams();
                        if (p.distribution_week_id) qs.set('week', p.distribution_week_id);
                        if (p.basket_date) qs.set('date', p.basket_date);
                        if (p.db_post_id) qs.set('post', p.db_post_id);
                        closePostPreview();
                        window.location.href = base + (qs.toString() ? ('?' + qs.toString()) : '');
                    },
                },
            ];
        }
        // Planned-post actions (ContentPost rows)
        return [
            { kind: 'primary', label: 'Edito brief', onClick: () => editFromPreview() },
        ];
    }

    function toast(msg) {
        // Minimal inline toast — the planner already has rich status bars,
        // this is just for the modal's copy-link confirm so users get feedback.
        const el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#18181b;color:#fff;padding:8px 14px;border-radius:6px;font-size:12px;z-index:10010;box-shadow:0 10px 30px rgba(0,0,0,0.25);';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 2000);
    }

    function renderPreviewCarousel() {
        const host = document.getElementById('postPreviewMedia');
        while (host.firstChild) host.removeChild(host.firstChild);

        if (preview.media.length === 0) {
            const empty = document.createElement('div');
            empty.style.cssText = 'width:300px;height:300px;background:#1f2937;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#6b7280;';
            empty.textContent = 'Asnjë media';
            host.appendChild(empty);
            updatePreviewChrome();
            return;
        }

        // Viewport is a fixed-size container; track slides horizontally inside it.
        const viewport = document.createElement('div');
        viewport.id = 'postPreviewViewport';
        viewport.style.cssText = 'position:relative;max-width:90vw;max-height:80vh;width:min(90vw, 800px);overflow:hidden;border-radius:8px;touch-action:pan-y;user-select:none;';
        viewport.tabIndex = 0;

        const track = document.createElement('div');
        track.id = 'postPreviewTrack';
        track.style.cssText = 'display:flex;transition:transform 0.3s ease;will-change:transform;';

        preview.media.forEach((m) => {
            const slide = document.createElement('div');
            slide.style.cssText = 'flex:0 0 100%;width:100%;display:flex;align-items:center;justify-content:center;position:relative;';
            const isVideo = (m.mime_type || '').startsWith('video/');
            const el = document.createElement(isVideo ? 'video' : 'img');
            el.src = m.url || m.thumbnail_url || '';
            el.style.cssText = 'max-width:100%;max-height:80vh;object-fit:contain;display:block;' + (isVideo ? '' : 'pointer-events:none;');
            if (isVideo) {
                // Autoplay still requires muted (browser policy) — but expose
                // native controls so the user can unmute / scrub / pause.
                el.muted = true;
                el.autoplay = true;
                el.loop = true;
                el.playsInline = true;
                el.controls = true;
                el.preload = 'metadata';
            } else {
                el.alt = '';
                el.draggable = false;
            }
            slide.appendChild(el);

            // Play overlay per Reels/video posts -- klik hap permalink-un ne
            // tab te ri. Shfaqet vetem kur posti eshte video DHE ka permalink.
            // Ndertuar me createElementNS per SVG (jo innerHTML) -- sigurt nga XSS.
            if (!isVideo && preview.isVideoPost && preview.permalink) {
                const playBtn = document.createElement('a');
                playBtn.href = preview.permalink;
                playBtn.target = '_blank';
                playBtn.rel = 'noopener noreferrer';
                playBtn.title = 'Hap ne Instagram per ta luajtur';
                playBtn.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-decoration:none;cursor:pointer;background:rgba(0,0,0,0.0);transition:background 0.15s;';

                const playIcon = document.createElement('div');
                playIcon.style.cssText = 'width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,0.92);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,0.3);transition:transform 0.15s;';

                const SVG_NS = 'http://www.w3.org/2000/svg';
                const svg = document.createElementNS(SVG_NS, 'svg');
                svg.setAttribute('width', '32');
                svg.setAttribute('height', '32');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', '#18181b');
                const path = document.createElementNS(SVG_NS, 'path');
                path.setAttribute('d', 'M8 5v14l11-7z');
                svg.appendChild(path);
                playIcon.appendChild(svg);

                playBtn.onmouseover = () => { playBtn.style.background = 'rgba(0,0,0,0.25)'; playIcon.style.transform = 'scale(1.05)'; };
                playBtn.onmouseout = () => { playBtn.style.background = 'rgba(0,0,0,0.0)'; playIcon.style.transform = 'scale(1)'; };

                playBtn.appendChild(playIcon);
                slide.appendChild(playBtn);
            }

            track.appendChild(slide);
        });

        viewport.appendChild(track);
        host.appendChild(viewport);
        applyPreviewTransform(false);
        updatePreviewChrome();
    }

    function applyPreviewTransform(animate) {
        const track = document.getElementById('postPreviewTrack');
        if (!track) return;
        track.style.transition = animate === false ? 'none' : 'transform 0.3s ease';
        track.style.transform = `translateX(-${preview.index * 100}%)`;
        if (animate === false) {
            requestAnimationFrame(() => { track.style.transition = 'transform 0.3s ease'; });
        }
    }

    function updatePreviewChrome() {
        const prev = document.getElementById('postPreviewPrev');
        const next = document.getElementById('postPreviewNext');
        const counter = document.getElementById('postPreviewCounter');
        const dots = document.getElementById('postPreviewDots');
        const multi = preview.media.length > 1;

        prev.style.display = multi ? 'flex' : 'none';
        next.style.display = multi ? 'flex' : 'none';
        counter.style.display = multi ? 'block' : 'none';
        counter.textContent = `${preview.index + 1}/${preview.media.length}`;

        dots.style.display = multi ? 'flex' : 'none';
        while (dots.firstChild) dots.removeChild(dots.firstChild);
        if (multi) {
            for (let i = 0; i < preview.media.length; i++) {
                const d = document.createElement('div');
                d.style.cssText = `width:7px;height:7px;border-radius:50%;background:${i === preview.index ? '#fff' : 'rgba(255,255,255,0.4)'};`;
                dots.appendChild(d);
            }
        }
    }

    function previewAt(index) {
        if (!preview.media.length) return;
        preview.index = Math.max(0, Math.min(preview.media.length - 1, index));
        applyPreviewTransform(true);
        updatePreviewChrome();
    }
    function previewNext() { previewAt(preview.index + 1); }
    function previewPrev() { previewAt(preview.index - 1); }

    function ensurePreviewWired() {
        const viewport = document.getElementById('postPreviewViewport');
        if (!viewport || viewport.dataset.wired === '1') return;
        viewport.dataset.wired = '1';

        const track = document.getElementById('postPreviewTrack');

        const onStart = (x) => {
            if (preview.media.length < 2) return;
            preview.dragging = true;
            preview.startX = x;
            preview.currentDx = 0;
            preview.width = viewport.clientWidth;
            track.style.transition = 'none';
        };
        const onMove = (x) => {
            if (!preview.dragging) return;
            preview.currentDx = x - preview.startX;
            const base = -preview.index * preview.width;
            track.style.transform = `translate3d(${base + preview.currentDx}px, 0, 0)`;
        };
        const onEnd = () => {
            if (!preview.dragging) return;
            preview.dragging = false;
            track.style.transition = 'transform 0.3s ease';
            const threshold = preview.width * 0.18;
            if (preview.currentDx < -threshold) preview.index = Math.min(preview.media.length - 1, preview.index + 1);
            else if (preview.currentDx > threshold)  preview.index = Math.max(0, preview.index - 1);
            track.style.transform = `translateX(-${preview.index * 100}%)`;
            preview.currentDx = 0;
            updatePreviewChrome();
        };

        viewport.addEventListener('touchstart', (e) => onStart(e.touches[0].clientX), { passive: true });
        viewport.addEventListener('touchmove',  (e) => onMove(e.touches[0].clientX),  { passive: true });
        viewport.addEventListener('touchend',   onEnd);
        viewport.addEventListener('touchcancel', onEnd);

        viewport.addEventListener('mousedown', (e) => { onStart(e.clientX); e.preventDefault(); });
        window.addEventListener('mousemove',   (e) => onMove(e.clientX));
        window.addEventListener('mouseup',     onEnd);

        viewport.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft')  { e.preventDefault(); previewPrev(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); previewNext(); }
        });
    }

    function closePostPreview() {
        const overlay = document.getElementById('postPreviewOverlay');
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const vid = document.querySelector('#postPreviewMedia video');
        if (vid) vid.pause();
        previewPostId = null;
        preview.media = [];
        preview.index = 0;
    }

    // ESC closes the preview, and in the preview ← → navigate the carousel.
    // Also: clicking the backdrop (but not the modal itself) closes.
    document.addEventListener('keydown', (e) => {
        const overlay = document.getElementById('postPreviewOverlay');
        if (!overlay || !overlay.classList.contains('open')) return;
        if (e.key === 'Escape') closePostPreview();
        if (e.key === 'ArrowLeft')  previewPrev();
        if (e.key === 'ArrowRight') previewNext();
    });
    document.addEventListener('click', (e) => {
        const overlay = document.getElementById('postPreviewOverlay');
        if (!overlay || !overlay.classList.contains('open')) return;
        if (e.target === overlay) closePostPreview();
    });

    function editFromPreview() {
        const id = previewPostId;
        closePostPreview();
        if (id) openComposer(id);
    }

    function downloadFromPreview() {
        const active = preview.media[preview.index];
        const src = active?.url || active?.thumbnail_url;
        if (src) { const a = document.createElement('a'); a.href = src; a.download = ''; a.click(); }
    }
</script>

{{-- Post Detail Modal (60/40 — mockup parity). Replaces the fullscreen
     black overlay with a light, sectioned panel so the caption + metrics
     read naturally and external vs planned posts share the same shell. --}}
<div id="postPreviewOverlay" class="pd-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="pd-modal">
        <button type="button" class="pd-close" onclick="closePostPreview()" aria-label="Mbyll">×</button>

        <div class="pd-media">
            <div class="pd-type-chip" id="pdTypeChip"></div>
            <div class="pd-media-inner" id="postPreviewMedia"></div>

            <button type="button" class="pd-carousel-arrow left" id="postPreviewPrev" onclick="event.stopPropagation(); previewPrev();" aria-label="Previous" style="display:none;">‹</button>
            <button type="button" class="pd-carousel-arrow right" id="postPreviewNext" onclick="event.stopPropagation(); previewNext();" aria-label="Next" style="display:none;">›</button>
            <div class="pd-carousel-dots" id="postPreviewDots" style="display:none;"></div>
            <div id="postPreviewCounter" style="display:none;"></div>
        </div>

        <div class="pd-detail">
            <div class="pd-scroll">
                <div class="pd-head-row">
                    <span class="pd-platform-badge ig" id="pdPlatformBadge">—</span>
                    <span class="pd-meta-line" id="pdMetaLine">—</span>
                    <span class="pd-status-tag" id="pdStatusTag">—</span>
                </div>

                <div class="pd-section">
                    <div class="pd-section-label">Caption</div>
                    <div class="pd-caption-wrap" id="pdCaptionWrap">
                        <div class="pd-caption-box" id="postPreviewCaption">—</div>
                        <div class="pd-caption-fade" aria-hidden="true"></div>
                    </div>
                    <div class="pd-caption-hint">scroll per me shume</div>
                    <div class="pd-hashtag-row" id="pdHashtagRow"></div>
                </div>

                <div class="pd-section" id="pdMetricsSection" style="display:none;">
                    <div class="pd-section-label">Performance</div>
                    <div class="pd-metrics" id="pdMetricsGrid"></div>
                </div>

                <div class="pd-section" id="pdDetailsSection" style="display:none;">
                    <div class="pd-section-label">Detaje</div>
                    <div class="pd-kv-list" id="pdKvList"></div>
                </div>
            </div>

            <div class="pd-foot" id="pdFooter"></div>
        </div>
    </div>
</div>
@endsection
