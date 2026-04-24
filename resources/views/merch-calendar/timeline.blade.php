@extends('_layouts.app', [
    'title'     => 'Merch Calendar — Timeline',
    'pageTitle' => 'Merch Calendar',
])

@section('styles')
<link rel="preconnect" href="https://web-cdn.zeroabsolute.com" crossorigin>
<style>
    .tl-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; transition:box-shadow 0.15s, transform 0.1s; cursor:pointer; }
    .tl-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); transform:translateY(-1px); }
    .tl-card:active { transform:translateY(0); }
    .tl-status { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:600; padding:2px 8px; border-radius:10px; }
    .tl-status-planned { background:#f1f5f9; color:#64748b; }
    .tl-status-active { background:#dcfce7; color:#166534; }
    .tl-status-completed { background:#dbeafe; color:#1e40af; }
    .tl-class-badge { display:inline-flex; align-items:center; gap:3px; font-size:9px; font-weight:600; padding:2px 6px; border-radius:4px; }
    .tl-thumb { width:48px; height:48px; border-radius:6px; object-fit:cover; background:#f1f5f9; }
    .tl-thumb-placeholder { width:48px; height:48px; border-radius:6px; background:linear-gradient(135deg,#f1f5f9,#e2e8f0); }
    .tl-filter-btn { padding:5px 12px; font-size:11px; font-weight:500; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer; transition:all 0.1s; }
    .tl-filter-btn:hover { border-color:#cbd5e1; }
    .tl-filter-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; }
    .tl-stat { display:flex; align-items:baseline; gap:3px; }
    .tl-stat-val { font-size:13px; font-weight:700; color:#0f172a; }
    .tl-stat-lbl { font-size:10px; color:#94a3b8; }
    .tl-progress { height:4px; background:#f1f5f9; border-radius:2px; overflow:hidden; margin-top:6px; }
    .tl-progress-bar { height:100%; border-radius:2px; transition:width 0.3s; }
    .tl-progress-bar.planned { background:#cbd5e1; }
    .tl-progress-bar.active { background:linear-gradient(90deg,#22c55e,#16a34a); }
    .tl-progress-bar.completed { background:#3b82f6; }

    /* Skeleton loader */
    .tl-skel { position:relative; overflow:hidden; background:#f1f5f9; border-radius:6px; }
    .tl-skel::after { content:''; position:absolute; inset:0; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.6),transparent); animation:tl-shimmer 1.2s infinite; }
    @keyframes tl-shimmer { 0%{transform:translateX(-100%);} 100%{transform:translateX(100%);} }
    .tl-skel-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-bottom:20px; }
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
            {{-- Skeleton while loading --}}
            <div id="tlSkeleton" style="position:relative; padding-left:24px; border-left:2px solid #e2e8f0;">
                @for ($i = 0; $i < 3; $i++)
                <div style="position:relative; margin-bottom:20px;">
                    <div style="position:absolute; left:-29px; top:16px; width:10px; height:10px; border-radius:50%; background:#e2e8f0; border:2px solid #fff;"></div>
                    <div class="tl-skel-card">
                        <div class="tl-skel" style="height:14px; width:40%; margin-bottom:10px;"></div>
                        <div class="tl-skel" style="height:10px; width:60%; margin-bottom:10px;"></div>
                        <div style="display:flex; gap:4px;">
                            <div class="tl-skel" style="width:48px; height:48px;"></div>
                            <div class="tl-skel" style="width:48px; height:48px;"></div>
                            <div class="tl-skel" style="width:48px; height:48px;"></div>
                        </div>
                    </div>
                </div>
                @endfor
            </div>
        </div>
    </div>
</div>

<script>
    const SUMMARY_API = @json(route('marketing.merch-calendar.api.weeks.summary'));
    const COLLECTION_URL_BASE = @json(url('/marketing/merch-calendar/collection'));
    const CDN_PROXY = @json(route('marketing.cdn-image'));
    let allWeeks = [];
    let currentFilter = 'all';
    let thumbObserver = null;

    const classColors = {
        best_seller: { bg:'#fef3c7', text:'#92400e', label:'Best Seller' },
        fashion:     { bg:'#fce7f3', text:'#9d174d', label:'Fashion' },
        karrem:      { bg:'#d1fae5', text:'#065f46', label:'Karrem' },
        plotesues:   { bg:'#e0e7ff', text:'#3730a3', label:'Plotësues' },
    };

    // HTML escape to prevent XSS when composing template strings with DB-sourced text
    const _escDiv = document.createElement('div');
    function esc(str) {
        if (str == null) return '';
        _escDiv.textContent = String(str);
        return _escDiv.innerHTML;
    }

    function proxyImg(url) {
        if (!url) return '';
        if (url.startsWith('https://web-cdn.zeroabsolute.com/')) {
            return `${CDN_PROXY}?url=${encodeURIComponent(url)}`;
        }
        return url;
    }

    function fmtNum(n) {
        return Number(n || 0).toLocaleString('sq-AL');
    }

    function fmtLek(v) {
        if (!v && v !== 0) return '—';
        return Math.round(Number(v)).toLocaleString('sq-AL') + ' L';
    }

    // Returns { label, pct, cls } based on week status + dates
    function computeProgress(w) {
        const st = w.status || 'planned';
        const today = new Date(); today.setHours(0,0,0,0);
        const start = new Date(w.week_start);
        const end = new Date(w.week_end);
        const msDay = 86400000;

        if (st === 'completed') return { label:'Përfunduar', pct:100, cls:'completed' };

        if (st === 'planned' || today < start) {
            const daysLeft = Math.max(0, Math.ceil((start - today) / msDay));
            return { label: daysLeft === 0 ? 'Nis sot' : `Nis pas ${daysLeft} ditë`, pct:0, cls:'planned' };
        }

        // active
        if (today > end) return { label:'Tejkaluar', pct:100, cls:'active' };
        const total = Math.max(1, Math.round((end - start) / msDay) + 1);
        const elapsed = Math.min(total, Math.round((today - start) / msDay) + 1);
        const pct = Math.round((elapsed / total) * 100);
        return { label:`Dita ${elapsed}/${total}`, pct, cls:'active' };
    }

    async function loadTimeline() {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth() - 2, 1).toISOString().slice(0,10);
        const end = new Date(now.getFullYear(), now.getMonth() + 4, 0).toISOString().slice(0,10);

        try {
            const res = await fetch(`${SUMMARY_API}?start=${start}&end=${end}`, { headers:{'Accept':'application/json'} });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            allWeeks = (await res.json()).sort((a,b) => a.week_start.localeCompare(b.week_start));

            renderTimeline();
        } catch(e) {
            document.getElementById('timelineContainer').textContent = '';
            const err = document.createElement('div');
            err.style.cssText = 'text-align:center;padding:40px;color:#ef4444;';
            err.textContent = 'Gabim: ' + (e.message || 'Failed to load');
            document.getElementById('timelineContainer').appendChild(err);
        }
    }

    function filterTimeline(status, btn) {
        currentFilter = status;
        document.querySelectorAll('.tl-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderTimeline();
    }

    function setupThumbObserver() {
        if (thumbObserver) thumbObserver.disconnect();
        if (!('IntersectionObserver' in window)) return;
        thumbObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const src = img.dataset.src;
                    if (src) {
                        img.src = src;
                        img.removeAttribute('data-src');
                    }
                    thumbObserver.unobserve(img);
                }
            });
        }, { rootMargin: '150px 0px' });
    }

    function renderTimeline() {
        const container = document.getElementById('timelineContainer');
        const weeks = currentFilter === 'all' ? allWeeks : allWeeks.filter(w => w.status === currentFilter);

        if (!weeks.length) {
            container.textContent = '';
            const empty = document.createElement('div');
            empty.style.cssText = 'text-align:center;padding:40px;color:#94a3b8;font-size:13px;';
            empty.textContent = 'Asnjë kolekcion';
            container.appendChild(empty);
            return;
        }

        const html = '<div style="position:relative; padding-left:24px; border-left:2px solid #e2e8f0;">' +
            weeks.map(w => {
                const st = w.status || 'planned';
                const dotColors = { planned:'#94a3b8', active:'#22c55e', completed:'#3b82f6' };
                const stLabel = st === 'planned' ? 'Planifikuar' : st === 'active' ? 'Aktiv' : 'Kompletuar';

                const stats = w.stats || {};
                const classCounts = w.class_counts || {};
                const thumbs = w.thumbnails || [];
                const totalGroups = w.item_groups_count ?? stats.total_groups ?? 0;
                const hasPriceRange = stats.min_price != null && stats.max_price != null;
                const progress = computeProgress(w);
                const weekId = Number(w.id) || 0;

                return `<div style="position:relative; margin-bottom:20px;">
                    <div style="position:absolute; left:-29px; top:16px; width:10px; height:10px; border-radius:50%; background:${dotColors[st]}; border:2px solid #fff; box-shadow:0 0 0 2px ${dotColors[st]}33;"></div>
                    <a class="tl-card" href="${COLLECTION_URL_BASE}/${weekId}" style="text-decoration:none; color:inherit; display:block;">
                        <div style="padding:14px 16px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; gap:8px;">
                                <div style="min-width:0; flex:1;">
                                    <span style="font-size:14px; font-weight:600; color:#1e293b;">${esc(w.name)}</span>
                                    <span class="tl-status tl-status-${st}" style="margin-left:8px;">${stLabel}</span>
                                </div>
                                <span style="font-size:11px; color:#94a3b8; white-space:nowrap;">${esc(w.week_start)} → ${esc(w.week_end)}</span>
                            </div>

                            <div style="display:flex; align-items:center; gap:14px; margin-bottom:8px; flex-wrap:wrap;">
                                <div class="tl-stat"><span class="tl-stat-val">${fmtNum(totalGroups)}</span><span class="tl-stat-lbl">grupe</span></div>
                                <div class="tl-stat"><span class="tl-stat-val">${fmtNum(stats.total_stock)}</span><span class="tl-stat-lbl">stok</span></div>
                                ${stats.total_sold ? `<div class="tl-stat"><span class="tl-stat-val" style="color:#16a34a;">${fmtNum(stats.total_sold)}</span><span class="tl-stat-lbl">shitur</span></div>` : ''}
                                ${stats.total_stock_value ? `<div class="tl-stat"><span class="tl-stat-val">${fmtLek(stats.total_stock_value)}</span><span class="tl-stat-lbl">vlerë</span></div>` : ''}
                                ${hasPriceRange ? `<div class="tl-stat"><span class="tl-stat-val">${fmtLek(stats.min_price)}–${fmtLek(stats.max_price)}</span></div>` : ''}
                            </div>

                            ${Object.keys(classCounts).length ? '<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:10px;">' + Object.entries(classCounts).map(([c, n]) => {
                                const cc = classColors[c] || classColors.plotesues;
                                return `<span class="tl-class-badge" style="background:${cc.bg};color:${cc.text};">${esc(cc.label)} (${Number(n)||0})</span>`;
                            }).join('') + '</div>' : ''}

                            ${thumbs.length ? '<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;">' + thumbs.map(g => {
                                const src = proxyImg(g.image_url);
                                const name = esc(g.name || '');
                                return src
                                    ? `<img data-src="${esc(src)}" class="tl-thumb" alt="${name}" title="${name}" loading="lazy" onerror="this.style.display='none';">`
                                    : `<div class="tl-thumb-placeholder" title="${name}"></div>`;
                            }).join('') + '</div>' : ''}

                            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px;">
                                <span style="font-size:10px; color:#64748b; font-weight:500;">${esc(progress.label)}</span>
                                <span style="font-size:10px; color:#cbd5e1;">${progress.pct}%</span>
                            </div>
                            <div class="tl-progress">
                                <div class="tl-progress-bar ${progress.cls}" style="width:${progress.pct}%;"></div>
                            </div>
                        </div>
                    </a>
                </div>`;
            }).join('') + '</div>';

        // Single innerHTML set — source composed from escaped DB values + safe constants
        container.innerHTML = html; // eslint-disable-line no-unsanitized/property

        // Lazy-load thumbs via IntersectionObserver (or eager fallback)
        setupThumbObserver();
        const imgs = container.querySelectorAll('img.tl-thumb[data-src]');
        if (thumbObserver) {
            imgs.forEach(img => thumbObserver.observe(img));
        } else {
            imgs.forEach(img => { img.src = img.dataset.src; img.removeAttribute('data-src'); });
        }
    }

    document.addEventListener('DOMContentLoaded', loadTimeline);
</script>
@endsection
