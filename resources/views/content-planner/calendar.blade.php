@extends('_layouts.app', [
    'title'     => 'Content Planner',
    'pageTitle' => 'Content Planner',
])

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        /* ── FullCalendar Planable-style overrides ── */
        .fc { font-family: Inter, sans-serif; }

        /* Hide default toolbar — we use custom */
        .fc .fc-toolbar.fc-header-toolbar { display: none !important; }

        /* Clean day grid */
        .fc .fc-daygrid-day-number { font-size: 13px; font-weight: 500; color: #64748b; padding: 8px 10px !important; }
        .fc .fc-daygrid-day.fc-day-today { background: #fafbff !important; }
        .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #fff; background: #ef4444; border-radius: 50%; width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; }
        .fc .fc-col-header-cell-cushion { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; padding: 10px 0 !important; }
        .fc .fc-scrollgrid { border: none !important; }
        .fc .fc-scrollgrid td, .fc .fc-scrollgrid th { border-color: #f1f5f9 !important; }
        .fc .fc-daygrid-day { min-height: 100px; }
        .fc .fc-daygrid-day:hover { background: #f8fafc; }
        .fc .fc-day-other .fc-daygrid-day-number { color: #cbd5e1; }

        /* Events */
        .fc .fc-daygrid-event { border-radius: 6px !important; padding: 0 !important; font-size: 11px !important; border: none !important; cursor: pointer; overflow: hidden; margin-bottom: 2px !important; }
        .fc .fc-timegrid-event { border-radius: 6px !important; border: none !important; overflow: hidden; }
        .fc .fc-event-main { padding: 0 !important; }
        .fc .fc-more-link { font-size: 11px; color: #6366f1; font-weight: 600; padding: 2px 6px; }

        /* Week view */
        .fc .fc-timegrid-slot { height: 48px; }
        .fc .fc-timegrid-axis-cushion { font-size: 11px; color: #94a3b8; }

        /* ── Rich event cards ── */
        .cp-event-card { display: flex; align-items: center; gap: 5px; padding: 3px 6px; border-left: 3px solid; min-height: 22px; background: #fff; }
        .cp-event-thumb { width: 20px; height: 20px; border-radius: 3px; object-fit: cover; flex-shrink: 0; }
        .cp-event-platforms { display: flex; gap: 2px; flex-shrink: 0; }
        .cp-event-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 500; color: #374151; flex: 1; min-width: 0; }
        .cp-event-status { font-size: 9px; font-weight: 600; padding: 1px 4px; border-radius: 3px; white-space: nowrap; flex-shrink: 0; }
        .cp-event-card.external { background: #FAFAFA; }
        .cp-event-metrics { font-size: 9px; color: #6B7280; white-space: nowrap; flex-shrink: 0; }

        /* ── Popover (position:fixed, positioned by JS) ── */
        .cp-popover { display: none; position: fixed; z-index: 9999; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: 1px solid #E5E7EB; width: 360px; max-height: 85vh; overflow-y: auto; }
        .cp-popover-overlay { display: none; position: fixed; inset: 0; z-index: 9998; pointer-events: none; }
        .cp-popover-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .cp-popover-metric { background: #F9FAFB; border-radius: 6px; padding: 8px 10px; }
        .cp-popover-metric-label { font-size: 10px; color: #9CA3AF; text-transform: uppercase; font-weight: 600; }
        .cp-popover-metric-val { font-size: 16px; font-weight: 700; color: #111827; margin-top: 2px; }
    </style>
@endsection

@section('content')
<div id="content-planner" class="space-y-0">

    {{-- Top toolbar (Planable-style) --}}
    <div class="flex items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            {{-- Filter dropdown --}}
            <div class="relative" id="filterDropdownWrap">
                <button onclick="document.getElementById('filterPanel').classList.toggle('hidden')" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:funnel" width="13"></iconify-icon>
                    Filter
                    <span id="filterBadge" class="hidden min-w-[16px] h-[16px] rounded-full bg-primary-500 text-white text-[9px] font-bold flex items-center justify-center">0</span>
                </button>
                {{-- Filter panel --}}
                <div id="filterPanel" class="hidden absolute top-full left-0 mt-1.5 w-[280px] bg-white rounded-xl border border-slate-200 shadow-xl z-50 p-4 space-y-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Platform</label>
                        <select id="filterPlatform" onchange="refreshCalendar(); updateFilterBadge()" class="w-full h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 outline-none focus:ring-2 focus:ring-primary-500/20">
                            <option value="">All Platforms</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Status</label>
                        <select id="filterStatus" onchange="refreshCalendar(); updateFilterBadge()" class="w-full h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 outline-none focus:ring-2 focus:ring-primary-500/20">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="pending_review">Pending Review</option>
                            <option value="approved">Approved</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Label</label>
                        <select id="filterLabel" onchange="refreshCalendar(); updateFilterBadge()" class="w-full h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 outline-none focus:ring-2 focus:ring-primary-500/20">
                            <option value="">All Labels</option>
                            @foreach($labels as $label)
                                <option value="{{ $label->id }}">{{ $label->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer pt-1">
                        <input type="checkbox" id="filterExternal" checked onchange="refreshCalendar()" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        Show Published Posts
                    </label>
                    <button onclick="clearFilters()" class="w-full h-8 rounded-lg border border-slate-200 text-xs text-slate-500 hover:bg-slate-50 transition-colors">Clear Filters</button>
                </div>
            </div>

            {{-- Sync Meta --}}
            <button onclick="syncFromMeta(this)" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-500 hover:bg-slate-50 transition-colors">
                <iconify-icon icon="heroicons-outline:arrow-path" width="15"></iconify-icon>
                Sync
            </button>
        </div>

        {{-- Compose button --}}
        <button onclick="openComposer()" class="inline-flex items-center gap-1.5 h-[30px] px-4 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors shadow-sm">
            <iconify-icon icon="heroicons-outline:pencil-square" width="13"></iconify-icon>
            Compose
        </button>
    </div>

    {{-- Calendar with custom Planable-style toolbar --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {{-- Custom toolbar --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <div class="flex items-center gap-1">
                {{-- Month / Week toggle --}}
                <button id="viewMonth" onclick="switchView('dayGridMonth')" class="px-3.5 py-1.5 text-sm font-semibold rounded-lg bg-slate-900 text-white transition-colors">Month</button>
                <button id="viewWeek" onclick="switchView('timeGridWeek')" class="px-3.5 py-1.5 text-sm font-medium rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">Week</button>
            </div>

            <div class="flex items-center gap-3">
                {{-- Prev / Next --}}
                <button onclick="calendar.prev()" class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </button>
                <button onclick="calendar.next()" class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </button>
                {{-- Title --}}
                <h2 id="calendarTitle" class="text-lg font-bold text-slate-900 min-w-[140px]">Apr 2026</h2>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="calendar.today()" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">Today</button>
            </div>
        </div>

        {{-- Calendar body --}}
        <div class="p-4">
            <div id="calendar"></div>
        </div>
    </div>
</div>

{{-- FAB --}}
<button onclick="openComposer()" title="New Post"
    class="fixed bottom-7 right-7 w-14 h-14 rounded-full bg-primary-500 text-white shadow-lg hover:scale-110 transition-transform flex items-center justify-center z-[100] cursor-pointer border-none">
    <iconify-icon icon="heroicons-outline:plus" width="28"></iconify-icon>
</button>

{{-- External post popover --}}
<div class="cp-popover-overlay" id="popoverOverlay" onclick="closePopover()"></div>
<div class="cp-popover" id="externalPopover">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <div class="flex items-center gap-1.5">
            <span id="popoverPlatformIcon"></span>
            <span class="text-[13px] font-semibold text-slate-700" id="popoverTitle">Post Details</span>
        </div>
        <button onclick="closePopover()" class="bg-transparent border-none cursor-pointer p-1">
            <iconify-icon icon="heroicons-outline:x-mark" width="18" style="color:#9CA3AF;"></iconify-icon>
        </button>
    </div>
    <div class="p-4">
        <div id="popoverThumb" class="mb-3"></div>
        <p id="popoverContent" class="text-[13px] text-slate-700 m-0 mb-2 leading-relaxed"></p>
        <div class="cp-popover-metrics" id="popoverMetrics"></div>
        <div class="mt-3">
            <a id="popoverPermalink" href="#" target="_blank" class="text-xs text-primary-500 font-semibold no-underline inline-flex items-center gap-1">
                <iconify-icon icon="heroicons-outline:arrow-top-right-on-square" width="14"></iconify-icon> View Original Post
            </a>
        </div>
    </div>
</div>

{{-- Day detail modal --}}
<div id="dayDetailOverlay" class="hidden fixed inset-0 bg-black/40 z-[9996]" onclick="closeDayDetail()"></div>
<div id="dayDetailModal" class="hidden fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[94vw] max-w-[560px] max-h-[80vh] bg-white rounded-[14px] shadow-[0_20px_50px_rgba(0,0,0,0.2)] z-[9997] overflow-hidden font-['Inter',sans-serif]">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
        <h3 id="dayDetailTitle" class="text-base font-bold text-slate-900 m-0 flex items-center gap-1">
            <iconify-icon icon="heroicons-outline:calendar" width="18" class="align-middle"></iconify-icon>
            Posts for March 12
        </h3>
        <div class="flex gap-1.5">
            <button id="dayDetailNewBtn" onclick="" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-md bg-primary-600 text-white hover:bg-primary-700 transition-colors border-none cursor-pointer">
                <iconify-icon icon="heroicons-outline:plus" width="12"></iconify-icon> New Post
            </button>
            <button onclick="closeDayDetail()" class="bg-transparent border-none cursor-pointer p-1">
                <iconify-icon icon="heroicons-outline:x-mark" width="20" style="color:#9CA3AF;"></iconify-icon>
            </button>
        </div>
    </div>
    <div id="dayDetailBody" class="px-5 py-3 pb-5 overflow-y-auto max-h-[calc(80vh-60px)]"></div>
</div>

{{-- Post composer modal --}}
@include('content-planner._partials.post-composer-modal')
@include('content-planner._partials.media-picker-modal')
@include('content-planner._partials.image-editor-modal')
@include('content-planner._partials.post-retry-script')

{{-- FullCalendar JS --}}
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
    let calendar;
    const statusLabels = { draft:'Draft', pending_review:'In Review', approved:'Approved', scheduled:'Scheduled', published:'Published', failed:'Failed' };

    function formatNumber(n) {
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
        return String(n || 0);
    }

    async function syncFromMeta(btn) {
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<iconify-icon icon="heroicons-outline:arrow-path" width="14" class="animate-spin"></iconify-icon> Syncing...';
        try {
            const res = await fetch('{{ route("marketing.planner.api.posts.sync-meta") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (res.ok) {
                alert(`Imported ${data.facebook ?? 0} FB + ${data.instagram ?? 0} IG posts`);
                if (calendar) calendar.refetchEvents();
            } else {
                alert('Sync failed: ' + (data.message || res.statusText));
            }
        } catch (e) {
            alert('Sync failed: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');

        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: false,
            editable: true,
            droppable: true,
            eventStartEditable: true,
            dayMaxEvents: 4,
            firstDay: 1,
            height: 'auto',
            nowIndicator: true,
            fixedWeekCount: false,

            datesSet: function(info) {
                // Update custom title
                const d = info.view.currentStart;
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                document.getElementById('calendarTitle').textContent = months[d.getMonth()] + ' ' + d.getFullYear();

                // Update view toggle
                const isMonth = info.view.type === 'dayGridMonth';
                document.getElementById('viewMonth').className = isMonth
                    ? 'px-3.5 py-1.5 text-sm font-semibold rounded-lg bg-slate-900 text-white transition-colors'
                    : 'px-3.5 py-1.5 text-sm font-medium rounded-lg text-slate-500 hover:bg-slate-100 transition-colors';
                document.getElementById('viewWeek').className = !isMonth
                    ? 'px-3.5 py-1.5 text-sm font-semibold rounded-lg bg-slate-900 text-white transition-colors'
                    : 'px-3.5 py-1.5 text-sm font-medium rounded-lg text-slate-500 hover:bg-slate-100 transition-colors';
            },

            events: function(info, successCallback, failureCallback) {
                const params = new URLSearchParams({
                    from: info.startStr.split('T')[0],
                    to: info.endStr.split('T')[0],
                });

                const platform = document.getElementById('filterPlatform').value;
                const status = document.getElementById('filterStatus').value;
                const label = document.getElementById('filterLabel').value;
                const showExternal = document.getElementById('filterExternal').checked ? '1' : '0';

                if (platform) params.set('platforms', platform);
                if (status) params.set('statuses', status);
                if (label) params.set('label_ids', label);
                params.set('include_external', showExternal);

                fetch(`{{ route('marketing.planner.api.posts.index') }}?${params}`)
                    .then(res => res.json())
                    .then(events => successCallback(events))
                    .catch(err => failureCallback(err));
            },

            eventContent: function(arg) {
                const props = arg.event.extendedProps;
                const isExternal = props.is_external;
                const statusColor = props.status_color || arg.event.borderColor || '#6B7280';
                const bgColor = props.status_bg_color || '#fff';

                let html = `<div class="cp-event-card ${isExternal ? 'external' : ''}" style="border-left-color:${statusColor}; background:${bgColor};">`;

                // Platform icons
                html += '<span class="cp-event-platforms">';
                (props.platform_icons || []).forEach(p => {
                    if (p === 'facebook') html += '<iconify-icon icon="logos:facebook" width="12"></iconify-icon>';
                    else if (p === 'instagram') html += '<iconify-icon icon="skill-icons:instagram" width="12"></iconify-icon>';
                    else if (p === 'tiktok') html += '<iconify-icon icon="logos:tiktok-icon" width="12"></iconify-icon>';
                });
                html += '</span>';

                // Thumbnail
                if (props.thumbnail) {
                    html += `<img src="${props.thumbnail}" class="cp-event-thumb" onerror="this.style.display='none'">`;
                }

                // Title
                const title = arg.event.title || 'Untitled';
                html += `<span class="cp-event-title">${title}</span>`;

                if (isExternal) {
                    // Show metrics for external posts
                    const m = props.metrics || {};
                    const reach = m.reach || m.view_count || 0;
                    html += `<span class="cp-event-metrics">${formatNumber(reach)} reach</span>`;
                } else {
                    // Show status chip
                    const sl = props.status_label || statusLabels[props.status] || '';
                    html += `<span class="cp-event-status" style="background:${statusColor}20;color:${statusColor};">${sl}</span>`;
                }

                html += '</div>';
                return { html };
            },

            eventClick: function(info) {
                const props = info.event.extendedProps;
                if (props.is_external || props.status === 'published') {
                    showExternalPopover(info.event, info.jsEvent);
                } else {
                    openComposer(info.event.id);
                }
            },

            eventDrop: function(info) {
                const props = info.event.extendedProps;
                if (props.is_external || props.status === 'published') {
                    info.revert();
                    return;
                }
                const newDate = info.event.start.toISOString();
                fetch(`/marketing/planner/api/posts/${info.event.id}/schedule`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ scheduled_at: newDate }),
                }).catch(() => info.revert());
            },

            dateClick: function(info) {
                showDayDetail(info.dateStr);
            },

            eventDidMount: function(info) {
                const labels = info.event.extendedProps.labels || [];
                if (labels.length) {
                    info.el.title = labels.map(l => l.name).join(', ');
                }
            },
        });

        calendar.render();
    });

    function refreshCalendar() {
        if (calendar) calendar.refetchEvents();
    }

    function switchView(viewName) {
        if (calendar) calendar.changeView(viewName);
    }

    function updateFilterBadge() {
        let count = 0;
        if (document.getElementById('filterPlatform').value) count++;
        if (document.getElementById('filterStatus').value) count++;
        if (document.getElementById('filterLabel').value) count++;
        const badge = document.getElementById('filterBadge');
        if (count > 0) { badge.textContent = count; badge.classList.remove('hidden'); badge.classList.add('flex'); }
        else { badge.classList.add('hidden'); badge.classList.remove('flex'); }
    }

    function clearFilters() {
        document.getElementById('filterPlatform').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterLabel').value = '';
        document.getElementById('filterExternal').checked = true;
        updateFilterBadge();
        refreshCalendar();
    }

    // Close filter panel on outside click
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('filterDropdownWrap');
        const panel = document.getElementById('filterPanel');
        if (wrap && !wrap.contains(e.target)) panel.classList.add('hidden');
    });

    // ── External post popover ──
    function showExternalPopover(event, jsEvent) {
        const props = event.extendedProps;
        const m = props.metrics || {};
        const platforms = props.platform_icons || [];
        const platform = platforms[0] || 'unknown';

        // Platform icon
        const iconMap = { facebook: 'logos:facebook', instagram: 'skill-icons:instagram', tiktok: 'logos:tiktok-icon' };
        document.getElementById('popoverPlatformIcon').innerHTML = `<iconify-icon icon="${iconMap[platform] || ''}" width="18"></iconify-icon>`;
        document.getElementById('popoverTitle').textContent = platform.charAt(0).toUpperCase() + platform.slice(1) + ' Post';

        // Thumbnail
        const thumbEl = document.getElementById('popoverThumb');
        if (props.is_video && (props.first_media_url || props.thumbnail)) {
            thumbEl.innerHTML = `<video src="${props.first_media_url || props.thumbnail}" muted autoplay loop playsinline style="width:100%;border-radius:8px;max-height:280px;object-fit:contain;background:#000;"></video>`;
        } else if (props.thumbnail) {
            thumbEl.innerHTML = `<img src="${props.thumbnail}" style="width:100%;border-radius:8px;max-height:280px;object-fit:contain;background:#000;">`;
        } else {
            thumbEl.innerHTML = '';
        }

        // Content
        document.getElementById('popoverContent').textContent = props.content || event.title || 'No caption';

        // Metrics
        let metricsHtml = '';
        if (m.reach !== undefined) metricsHtml += `<div class="cp-popover-metric"><div class="cp-popover-metric-label">Reach</div><div class="cp-popover-metric-val">${formatNumber(m.reach)}</div></div>`;
        if (m.view_count !== undefined) metricsHtml += `<div class="cp-popover-metric"><div class="cp-popover-metric-label">Views</div><div class="cp-popover-metric-val">${formatNumber(m.view_count)}</div></div>`;
        if (m.likes !== undefined || m.like_count !== undefined) metricsHtml += `<div class="cp-popover-metric"><div class="cp-popover-metric-label">Likes</div><div class="cp-popover-metric-val">${formatNumber(m.likes || m.like_count)}</div></div>`;
        if (m.comments !== undefined || m.comment_count !== undefined) metricsHtml += `<div class="cp-popover-metric"><div class="cp-popover-metric-label">Comments</div><div class="cp-popover-metric-val">${formatNumber(m.comments || m.comment_count)}</div></div>`;
        if (m.shares !== undefined || m.share_count !== undefined) metricsHtml += `<div class="cp-popover-metric"><div class="cp-popover-metric-label">Shares</div><div class="cp-popover-metric-val">${formatNumber(m.shares || m.share_count)}</div></div>`;
        if (m.engagement_rate !== undefined) metricsHtml += `<div class="cp-popover-metric"><div class="cp-popover-metric-label">Eng. Rate</div><div class="cp-popover-metric-val">${m.engagement_rate}%</div></div>`;
        document.getElementById('popoverMetrics').innerHTML = metricsHtml;

        // Permalink
        const linkEl = document.getElementById('popoverPermalink');
        if (props.permalink) {
            linkEl.href = props.permalink;
            linkEl.style.display = 'inline-flex';
        } else {
            linkEl.style.display = 'none';
        }

        // Position near click but stay fixed (scrollable behind)
        const popover = document.getElementById('externalPopover');
        const overlay = document.getElementById('popoverOverlay');
        popover.style.display = 'block';
        overlay.style.display = 'block';

        const pw = 360, ph = popover.offsetHeight || 400;
        const vw = window.innerWidth, vh = window.innerHeight;
        const cx = jsEvent.clientX, cy = jsEvent.clientY;

        // If clicked on right half → show popover to the LEFT of click
        // If clicked on left half → show popover to the RIGHT of click
        let x = cx > vw / 2 ? cx - pw - 12 : cx + 12;
        let y = cy;

        // Keep within viewport bounds
        x = Math.max(12, Math.min(x, vw - pw - 12));
        y = Math.max(12, Math.min(y, vh - ph - 12));

        popover.style.left = x + 'px';
        popover.style.right = 'auto';
        popover.style.top = y + 'px';
        popover.style.transform = 'none';
    }

    function closePopover() {
        const popover = document.getElementById('externalPopover');
        popover.style.display = 'none';
        popover.style.transform = '';
        document.getElementById('popoverOverlay').style.display = 'none';
    }

    // Close popover when clicking outside it (but allow scrolling)
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('externalPopover');
        if (popover.style.display === 'block' && !popover.contains(e.target)) {
            // Don't close if clicking a calendar event (it will open a new popover)
            if (!e.target.closest('.fc-event')) {
                closePopover();
            }
        }
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closePopover(); closeDayDetail(); } });

    // ── Day detail modal ──
    function showDayDetail(dateStr) {
        const date = new Date(dateStr + 'T12:00:00');
        const formatted = date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        document.getElementById('dayDetailTitle').innerHTML = `<iconify-icon icon="heroicons-outline:calendar" width="18" style="vertical-align:middle; margin-right:4px;"></iconify-icon> ${formatted}`;

        // Set new post button with pre-filled date
        const dt = new Date(dateStr);
        dt.setHours(12, 0, 0);
        const localDt = dt.toISOString().slice(0, 16);
        document.getElementById('dayDetailNewBtn').onclick = function() { closeDayDetail(); openComposer(null, localDt); };

        // Get events for this day from calendar
        const allEvents = calendar.getEvents();
        const dayEvents = allEvents.filter(ev => {
            const evDate = ev.start ? ev.start.toISOString().slice(0, 10) : '';
            return evDate === dateStr;
        });

        const body = document.getElementById('dayDetailBody');

        if (!dayEvents.length) {
            body.innerHTML = `<div style="text-align:center; padding:32px 0; color:#9CA3AF;">
                <iconify-icon icon="heroicons-outline:inbox" width="32"></iconify-icon>
                <p style="font-size:13px; margin:8px 0 0;">No posts for this day</p>
            </div>`;
        } else {
            body.innerHTML = dayEvents.map(ev => {
                const p = ev.extendedProps;
                const isExt = p.is_external || p.status === 'published';
                const statusColor = p.status_color || '#6B7280';
                const statusLabel = p.status_label || statusLabels[p.status] || '';
                const time = ev.start ? ev.start.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'All day';
                const platforms = (p.platform_icons || []).map(pl => {
                    const icons = { facebook:'logos:facebook', instagram:'skill-icons:instagram', tiktok:'logos:tiktok-icon' };
                    return `<iconify-icon icon="${icons[pl]||''}" width="14"></iconify-icon>`;
                }).join(' ');

                const thumb = p.thumbnail ? `<img src="${p.thumbnail}" style="width:40px;height:40px;border-radius:6px;object-fit:cover;flex-shrink:0;">` : '';

                const title = ev.title || 'Untitled';
                const truncTitle = title.length > 60 ? title.substring(0, 60) + '...' : title;

                // Click action
                const clickAction = isExt
                    ? (p.permalink ? `window.open('${p.permalink}','_blank')` : '')
                    : `closeDayDetail(); openComposer(${ev.id})`;

                // Metrics for external
                const m = p.metrics || {};
                const metricsText = isExt && m.reach ? `<span style="font-size:11px; color:#6B7280;">${formatNumber(m.reach)} reach</span>` : '';

                return `<div onclick="${clickAction}" style="display:flex; align-items:center; gap:10px; padding:10px; margin-bottom:6px; background:#fff; border:1px solid #E5E7EB; border-left:3px solid ${statusColor}; border-radius:8px; cursor:pointer; transition:box-shadow 0.15s;" onmouseenter="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'" onmouseleave="this.style.boxShadow='none'">
                    ${thumb}
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px;">
                            ${platforms}
                            <span style="font-size:12px; font-weight:600; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${truncTitle}</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:11px; color:#9CA3AF;">${time}</span>
                            <span style="font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; background:${statusColor}20; color:${statusColor};">${statusLabel}</span>
                            ${metricsText}
                        </div>
                    </div>
                    <iconify-icon icon="${isExt ? 'heroicons-outline:arrow-top-right-on-square' : 'heroicons-outline:pencil-square'}" width="16" style="color:#9CA3AF; flex-shrink:0;"></iconify-icon>
                </div>`;
            }).join('');
        }

        document.getElementById('dayDetailOverlay').style.display = 'block';
        document.getElementById('dayDetailModal').style.display = 'block';
    }

    function closeDayDetail() {
        document.getElementById('dayDetailOverlay').style.display = 'none';
        document.getElementById('dayDetailModal').style.display = 'none';
    }
</script>
@endsection
