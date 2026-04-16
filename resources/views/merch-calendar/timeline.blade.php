@extends('_layouts.app', [
    'title'     => 'Merch Calendar — Timeline',
    'pageTitle' => 'Merch Calendar',
])

@section('styles')
<style>
    .tl-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; transition:box-shadow 0.15s; }
    .tl-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.06); }
    .tl-status { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:600; padding:2px 8px; border-radius:10px; }
    .tl-status-planned { background:#f1f5f9; color:#64748b; }
    .tl-status-active { background:#dcfce7; color:#166534; }
    .tl-status-completed { background:#dbeafe; color:#1e40af; }
    .tl-class-badge { display:inline-flex; align-items:center; gap:3px; font-size:9px; font-weight:600; padding:2px 6px; border-radius:4px; }
    .tl-thumb { width:48px; height:48px; border-radius:6px; object-fit:cover; background:#f1f5f9; }
    .tl-filter-btn { padding:5px 12px; font-size:11px; font-weight:500; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer; transition:all 0.1s; }
    .tl-filter-btn:hover { border-color:#cbd5e1; }
    .tl-filter-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; }
</style>
@endsection

@section('content')
<div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:1px solid #f1f5f9;">
        <div style="display:flex; align-items:center; gap:12px;">
            <iconify-icon icon="heroicons-outline:calendar-days" width="20" style="color:#6366f1;"></iconify-icon>
            <span style="font-size:15px; font-weight:600; color:#1e293b;">Merch Calendar</span>
            <div style="display:flex; gap:0; margin-left:12px;">
                <a href="{{ route('marketing.merch-calendar.calendar') }}" style="padding:5px 12px; font-size:11px; font-weight:500; border-radius:6px; text-decoration:none; color:#64748b;">Calendar</a>
                <a href="{{ route('marketing.merch-calendar.timeline') }}" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:6px; text-decoration:none; background:#6366f1; color:#fff;">Timeline</a>
                <a href="{{ route('marketing.merch-calendar.gantt') }}" style="padding:5px 12px; font-size:11px; font-weight:500; border-radius:6px; text-decoration:none; color:#64748b;">Gantt</a>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9; display:flex; gap:6px;">
        <button class="tl-filter-btn active" data-status="all" onclick="filterTimeline('all',this)">All</button>
        <button class="tl-filter-btn" data-status="planned" onclick="filterTimeline('planned',this)">Planifikuar</button>
        <button class="tl-filter-btn" data-status="active" onclick="filterTimeline('active',this)">Aktiv</button>
        <button class="tl-filter-btn" data-status="completed" onclick="filterTimeline('completed',this)">Kompletuar</button>
    </div>

    {{-- Timeline container --}}
    <div style="padding:20px; max-width:700px; margin:0 auto;">
        <div id="timelineContainer">
            <div style="text-align:center; padding:40px; color:#94a3b8; font-size:13px;">Loading...</div>
        </div>
    </div>
</div>

@include('merch-calendar._partials.collection-sidebar')
<script>
    const WEEKS_API = @json(route('marketing.merch-calendar.api.weeks'));
    const DETAIL_API = @json(url('/marketing/merch-calendar/api/weeks'));
    let allWeeks = [];
    let currentFilter = 'all';

    const classColors = {
        best_seller: { bg:'#fef3c7', text:'#92400e', label:'Best Seller' },
        fashion:     { bg:'#fce7f3', text:'#9d174d', label:'Fashion' },
        karrem:      { bg:'#d1fae5', text:'#065f46', label:'Karrem' },
        plotesues:   { bg:'#e0e7ff', text:'#3730a3', label:'Plotësues' },
    };

    async function loadTimeline() {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth() - 2, 1).toISOString().slice(0,10);
        const end = new Date(now.getFullYear(), now.getMonth() + 4, 0).toISOString().slice(0,10);

        try {
            const res = await fetch(`${WEEKS_API}?start=${start}&end=${end}`, { headers:{'Accept':'application/json'} });
            const events = await res.json();

            // Extract unique weeks from events
            const weekMap = {};
            events.forEach(e => {
                if (e.extendedProps?.type === 'collection') {
                    const p = e.extendedProps;
                    weekMap[p.distribution_week_id] = {
                        id: p.distribution_week_id,
                        name: e.title,
                        week_start: p.week_start,
                        week_end: p.week_end,
                        status: p.status,
                        notes: p.notes,
                        item_group_count: p.item_group_count,
                        cover_image_url: p.cover_image_url,
                    };
                }
            });

            allWeeks = Object.values(weekMap).sort((a,b) => a.week_start.localeCompare(b.week_start));

            // Load details for each week
            for (const w of allWeeks) {
                try {
                    const detail = await fetch(`${DETAIL_API}/${w.id}`, { headers:{'Accept':'application/json'} });
                    const data = await detail.json();
                    w.item_groups = data.item_groups || [];
                    w.stats = data.stats || {};
                } catch(e) { w.item_groups = []; w.stats = {}; }
            }

            renderTimeline();
        } catch(e) {
            document.getElementById('timelineContainer').innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;">Failed to load</div>';
        }
    }

    function filterTimeline(status, btn) {
        currentFilter = status;
        document.querySelectorAll('.tl-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderTimeline();
    }

    function renderTimeline() {
        const container = document.getElementById('timelineContainer');
        const weeks = currentFilter === 'all' ? allWeeks : allWeeks.filter(w => w.status === currentFilter);

        if (!weeks.length) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;font-size:13px;">No collections found</div>';
            return;
        }

        container.innerHTML = '<div style="position:relative; padding-left:24px; border-left:2px solid #e2e8f0;">' +
            weeks.map(w => {
                const st = w.status || 'planned';
                const dotColors = { planned:'#94a3b8', active:'#22c55e', completed:'#3b82f6' };

                // Classification counts
                const classCounts = {};
                (w.item_groups || []).forEach(g => {
                    const c = g.classification || 'plotesues';
                    classCounts[c] = (classCounts[c] || 0) + 1;
                });

                // Thumbnails (first 6)
                const thumbs = (w.item_groups || []).filter(g => g.image_url).slice(0, 6);

                return `<div style="position:relative; margin-bottom:20px;">
                    <div style="position:absolute; left:-29px; top:16px; width:10px; height:10px; border-radius:50%; background:${dotColors[st]}; border:2px solid #fff;"></div>
                    <div class="tl-card">
                        <div style="padding:14px 16px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                                <div>
                                    <span style="font-size:14px; font-weight:600; color:#1e293b;">${w.name}</span>
                                    <span class="tl-status tl-status-${st}" style="margin-left:8px;">${st === 'planned' ? 'Planifikuar' : st === 'active' ? 'Aktiv' : 'Kompletuar'}</span>
                                </div>
                                <span style="font-size:11px; color:#94a3b8;">${w.week_start} → ${w.week_end}</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px; font-size:12px; color:#64748b; margin-bottom:10px;">
                                <span>${w.item_groups?.length || 0} groups</span>
                                <span>${w.stats?.total_stock || 0} stock</span>
                                ${w.stats?.min_price ? `<span>€${Number(w.stats.min_price).toFixed(0)} – €${Number(w.stats.max_price).toFixed(0)}</span>` : ''}
                            </div>
                            ${Object.keys(classCounts).length ? '<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:10px;">' + Object.entries(classCounts).map(([c, n]) => {
                                const cc = classColors[c] || classColors.plotesues;
                                return `<span class="tl-class-badge" style="background:${cc.bg};color:${cc.text};">${cc.label} (${n})</span>`;
                            }).join('') + '</div>' : ''}
                            ${thumbs.length ? '<div style="display:flex;gap:4px;flex-wrap:wrap;">' + thumbs.map(g => `<img src="${g.image_url}" class="tl-thumb" alt="" loading="lazy">`).join('') + '</div>' : ''}
                        </div>
                    </div>
                </div>`;
            }).join('') + '</div>';
    }

    document.addEventListener('DOMContentLoaded', loadTimeline);
</script>
@endsection
