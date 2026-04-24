@extends('_layouts.app', [
    'title'     => ($collection['name'] ?? 'Collection') . ' — Merch Calendar',
    'pageTitle' => $collection['name'] ?? 'Collection',
])

@section('styles')
<style>
    .cd-wrap { font-family: Inter, system-ui, sans-serif; color: #0f172a; }

    /* Breadcrumb */
    .cd-topbar { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 16px; display: flex; align-items: center; gap: 10px; font-size: 13px; margin-bottom: 14px; }
    .cd-topbar a { color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .cd-topbar a:hover { color: #6366f1; }
    .cd-topbar .cd-sep { color: #cbd5e1; }
    .cd-topbar .cd-current { color: #0f172a; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Hero */
    .cd-hero { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px 22px; margin-bottom: 14px; }
    .cd-hero-grid { display: grid; grid-template-columns: 200px 1fr auto; gap: 20px; align-items: center; }
    .cd-hero-cover { width: 200px; height: 130px; border-radius: 10px; background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); display: flex; align-items: center; justify-content: center; color: #6366f1; overflow: hidden; }
    .cd-hero-cover img { width: 100%; height: 100%; object-fit: cover; }
    .cd-hero-info h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.02em; line-height: 1.2; }
    .cd-hero-meta { display: flex; gap: 14px; font-size: 12px; color: #64748b; margin-bottom: 12px; flex-wrap: wrap; }
    .cd-hero-meta span { display: inline-flex; align-items: center; gap: 5px; }
    .cd-hero-statuses { display: flex; gap: 5px; }
    .cd-status-btn { padding: 5px 12px; font-size: 11px; font-weight: 500; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; transition: all 0.1s; }
    .cd-status-btn:hover { border-color: #cbd5e1; }
    .cd-status-btn.active { font-weight: 600; }
    .cd-status-btn.active[data-st="planned"]   { background: #f1f5f9; color: #475569; border-color: #94a3b8; }
    .cd-status-btn.active[data-st="active"]    { background: #dcfce7; color: #166534; border-color: #22c55e; }
    .cd-status-btn.active[data-st="completed"] { background: #dbeafe; color: #1e40af; border-color: #3b82f6; }
    .cd-hero-actions { display: flex; flex-direction: column; gap: 6px; }
    .cd-btn { padding: 7px 14px; font-size: 12px; font-weight: 600; border-radius: 7px; border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.1s; text-decoration: none; }
    .cd-btn:hover { background: #f8fafc; }
    .cd-btn-scan { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
    .cd-btn-scan:hover { background: #e0e7ff; }

    /* Stats */
    .cd-stats { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 22px; margin-bottom: 14px; }
    .cd-stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 18px; }
    .cd-stat { padding-right: 18px; border-right: 1px solid #f1f5f9; }
    .cd-stat:last-child { border-right: 0; padding-right: 0; }
    .cd-stat-lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 4px; }
    .cd-stat-val { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1.2; }
    .cd-stat-val.success { color: #22c55e; }
    .cd-stat-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
    .cd-stat-val.cd-stat-mini { font-size: 12px; font-weight: 700; line-height: 1.5; }
    .cd-stat-val.cd-stat-small { font-size: 14px; }
    .cd-class-tag-brown { color: #92400e; }
    .cd-class-tag-green { color: #065f46; }
    .cd-class-tag-pink  { color: #9d174d; }
    .cd-class-tag-blue  { color: #3730a3; }

    /* Notes */
    .cd-notes { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; display: flex; gap: 10px; align-items: start; font-size: 13px; color: #92400e; line-height: 1.5; }

    /* Toolbar */
    .cd-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .cd-toolbar-left { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .cd-filter-btn { padding: 6px 12px; font-size: 12px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; transition: all 0.1s; }
    .cd-filter-btn:hover:not(.active) { background: #f8fafc; }
    .cd-filter-btn.active { background: #6366f1; color: #fff; border-color: #6366f1; }
    .cd-filter-btn .cd-count { margin-left: 4px; opacity: 0.7; font-size: 10px; }
    .cd-toolbar-right { font-size: 12px; color: #64748b; }

    /* Products grid */
    .cd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
    .cd-empty { grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 13px; background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px; }

    .cd-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: all 0.15s; display: flex; flex-direction: column; }
    .cd-card:hover { border-color: #c7d2fe; box-shadow: 0 6px 18px rgba(99,102,241,0.06); }

    .cd-card-img-wrap { position: relative; height: 170px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); display: flex; align-items: center; justify-content: center; color: #cbd5e1; cursor: pointer; }
    .cd-card-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cd-card-discount { position: absolute; top: 8px; right: 8px; background: #dc2626; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 5px; }

    .cd-card-body { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
    .cd-card-head { display: flex; justify-content: space-between; align-items: start; gap: 8px; cursor: pointer; }
    .cd-card-name { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
    .cd-class-badge { display: inline-flex; font-size: 9px; font-weight: 600; padding: 2px 7px; border-radius: 4px; flex-shrink: 0; }

    .cd-card-meta { font-size: 10px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .cd-card-price-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; background: #f8fafc; border-radius: 8px; }
    .cd-price { font-size: 15px; font-weight: 800; color: #0f172a; }
    .cd-price.discount { color: #dc2626; }
    .cd-old-price { font-size: 10px; color: #94a3b8; text-decoration: line-through; margin-left: 4px; }
    .cd-stock { font-size: 10px; color: #64748b; text-align: right; line-height: 1.4; }
    .cd-stock .cd-sold { color: #22c55e; font-weight: 600; }

    /* Date row — identical structure to sidebar for parity */
    .cd-date-row { display: flex; align-items: center; gap: 6px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; flex-wrap: wrap; position: relative; }
    .cd-date-row-icon { font-size: 12px; color: #6366f1; flex-shrink: 0; }
    .cd-date-pill { display: inline-flex; align-items: center; gap: 4px; background: #eef2ff; color: #3730a3; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 14px; border: 1px solid #c7d2fe; }
    .cd-date-pill.is-remarketing { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    .cd-date-pill-x { color: #94a3b8; font-size: 11px; cursor: pointer; margin-left: 2px; padding: 0 2px; line-height: 1; }
    .cd-date-pill-x:hover { color: #ef4444; }
    .cd-date-add { background: transparent; border: 1px dashed #cbd5e1; color: #64748b; font-size: 11px; padding: 3px 9px; border-radius: 14px; cursor: pointer; }
    .cd-date-add:hover { border-color: #6366f1; color: #6366f1; background: #eef2ff; }
    .cd-date-add.empty-state { width: 100%; text-align: left; padding: 5px 10px; }

    .cd-date-picker { position: absolute; top: 100%; left: 0; margin-top: 4px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 8px; z-index: 60; width: 240px; }
    .cd-date-picker-title { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 8px 8px; }
    .cd-date-picker-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
    .cd-date-wkd { text-align: center; font-size: 9px; color: #94a3b8; padding: 4px 0; text-transform: uppercase; }
    .cd-date-day { text-align: center; padding: 7px 2px; border-radius: 5px; font-size: 11px; cursor: pointer; color: #475569; border: none; background: transparent; }
    .cd-date-day:hover:not(.disabled):not(.assigned):not(.assigned-remarketing) { background: #eef2ff; color: #3730a3; }
    .cd-date-day.disabled { color: #cbd5e1; cursor: not-allowed; }
    .cd-date-day.assigned { background: #dbeafe; color: #1e40af; font-weight: 600; }
    .cd-date-day.assigned-remarketing { background: #fed7aa; color: #9a3412; font-weight: 600; }

    /* Product modal */
    .cd-modal-bg { display: none; position: fixed; inset: 0; z-index: 9990; background: rgba(0,0,0,0.4); }
    .cd-modal-bg.open { display: block; }
    .cd-modal { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 14px; width: 520px; max-width: 95vw; max-height: 95vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }

    @media (max-width: 900px) {
        .cd-hero-grid { grid-template-columns: 1fr; }
        .cd-hero-cover { width: 100%; height: 160px; }
        .cd-stats-grid { grid-template-columns: repeat(2, 1fr); }
        .cd-stat { border-right: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    }
</style>
@endsection

@section('content')
<div class="cd-wrap">

    {{-- Breadcrumb --}}
    <div class="cd-topbar">
        <a href="{{ route('marketing.merch-calendar.calendar') }}">← Merch Calendar</a>
        <span class="cd-sep">/</span>
        <span class="cd-current">{{ $collection['name'] ?? 'Collection' }}</span>
    </div>

    {{-- Hero --}}
    <section class="cd-hero">
        <div class="cd-hero-grid">
            <div class="cd-hero-cover">
                @if(!empty($collection['cover_image_url']))
                    <img src="{{ route('marketing.cdn-image') }}?url={{ urlencode($collection['cover_image_url']) }}" alt="">
                @else
                    <iconify-icon icon="heroicons-outline:photo" width="42"></iconify-icon>
                @endif
            </div>
            <div class="cd-hero-info">
                <h1>{{ $collection['name'] ?? 'Collection' }}</h1>
                <div class="cd-hero-meta">
                    <span>
                        <iconify-icon icon="heroicons-outline:calendar" width="13"></iconify-icon>
                        {{ $collection['week_start'] ?? '' }} → {{ $collection['week_end'] ?? '' }}
                    </span>
                    <span>
                        <iconify-icon icon="heroicons-outline:squares-2x2" width="13"></iconify-icon>
                        {{ $collection['stats']['total_groups'] ?? count($collection['item_groups'] ?? []) }} produkte
                    </span>
                </div>
                <div class="cd-hero-statuses" id="cdStatuses">
                    @php $st = $collection['status'] ?? 'planned'; @endphp
                    <button class="cd-status-btn {{ $st === 'planned'   ? 'active' : '' }}" data-st="planned"   onclick="changeCollectionStatus('planned')">Planifikuar</button>
                    <button class="cd-status-btn {{ $st === 'active'    ? 'active' : '' }}" data-st="active"    onclick="changeCollectionStatus('active')">Aktiv</button>
                    <button class="cd-status-btn {{ $st === 'completed' ? 'active' : '' }}" data-st="completed" onclick="changeCollectionStatus('completed')">Kompletuar</button>
                </div>
            </div>
            <div class="cd-hero-actions">
                <a class="cd-btn cd-btn-scan" href="{{ route('marketing.merch-calendar.quick-scan') }}?week={{ (int)($collection['id'] ?? 0) }}">
                    <iconify-icon icon="heroicons-outline:camera" width="14"></iconify-icon>
                    Quick Scan
                </a>
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="cd-stats">
        <div class="cd-stats-grid" id="cdStatsGrid">
            {{-- Rendered by JS --}}
        </div>
    </section>

    {{-- Notes --}}
    @if(!empty($collection['notes']))
        <div class="cd-notes">
            <iconify-icon icon="heroicons-outline:pencil-square" width="16" style="flex-shrink:0; margin-top:1px;"></iconify-icon>
            <div id="cdNotesText"></div>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="cd-toolbar">
        <div class="cd-toolbar-left" id="cdFilterBar">
            {{-- Rendered by JS --}}
        </div>
        <div class="cd-toolbar-right">
            <span id="cdShownCount">0</span> produkte
        </div>
    </div>

    {{-- Products grid --}}
    <section class="cd-products">
        <div class="cd-grid" id="cdGrid">
            <div class="cd-empty">Loading...</div>
        </div>
    </section>

</div>

{{-- Product modal --}}
<div class="cd-modal-bg" id="cdModalBg" onclick="if(event.target===this)closeProductModal()">
    <div class="cd-modal" id="cdModalInner"></div>
</div>

<script>
(function() {
    const COLLECTION    = @json($collection ?? []);
    const WEEK_ID       = Number(COLLECTION.id || 0);
    const WEEKS_BASE    = @json(url('/marketing/merch-calendar/api/weeks'));
    const STATUS_URL    = WEEKS_BASE + '/' + WEEK_ID + '/status';
    const DATES_URL_FOR = (groupId) => WEEKS_BASE + '/' + WEEK_ID + '/groups/' + Number(groupId) + '/dates';
    const CDN_PROXY     = @json(route('marketing.cdn-image'));
    const CSRF          = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let collectionData = COLLECTION;
    let currentFilter  = 'all';

    const classStyles = {
        best_seller: { bg:'#fef3c7', text:'#92400e', label:'Best Seller' },
        fashion:     { bg:'#fce7f3', text:'#9d174d', label:'Fashion' },
        karrem:      { bg:'#d1fae5', text:'#065f46', label:'Karrem' },
        plotesues:   { bg:'#e0e7ff', text:'#3730a3', label:'Plotësues' },
    };

    const DAY_NAMES_SQ     = ['Die', 'Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht'];
    const DAY_INITIALS_SQ  = ['D', 'H', 'M', 'M', 'E', 'P', 'S'];
    const MONTH_NAMES_SQ   = ['Jan', 'Shk', 'Mar', 'Pri', 'Maj', 'Qer', 'Kor', 'Gsh', 'Sht', 'Tet', 'Nën', 'Dhj'];

    // ─── Helpers ───────────────────────────────────────

    function proxyImg(url) {
        if (!url) return null;
        if (url.startsWith('https://web-cdn.zeroabsolute.com/')) {
            return CDN_PROXY + '?url=' + encodeURIComponent(url);
        }
        return url;
    }

    function fmtLek(val) {
        if (!val && val !== 0) return '—';
        return Math.round(Number(val)).toLocaleString('sq-AL') + ' L';
    }

    function calcDiscount(original, discounted) {
        if (!original || !discounted || discounted >= original) return 0;
        return Math.round((1 - discounted / original) * 100);
    }

    function parseYmd(ymd) {
        const [y, m, d] = String(ymd).split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function toYmd(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function fmtPillLabel(ymd) {
        const dt = parseYmd(ymd);
        return `${DAY_NAMES_SQ[dt.getDay()]} · ${dt.getDate()} ${MONTH_NAMES_SQ[dt.getMonth()]}`;
    }

    function getCollectionDates() {
        if (!collectionData?.week_start || !collectionData?.week_end) return [];
        const start = parseYmd(collectionData.week_start);
        const end = parseYmd(collectionData.week_end);
        const dates = [];
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            dates.push(toYmd(d));
        }
        return dates;
    }

    function clearChildren(node) {
        while (node.firstChild) node.removeChild(node.firstChild);
    }

    function el(tag, className, text) {
        const e = document.createElement(tag);
        if (className) e.className = className;
        if (text != null) e.textContent = String(text);
        return e;
    }

    // ─── Rendering ─────────────────────────────────────

    function renderNotes() {
        const node = document.getElementById('cdNotesText');
        if (node) node.textContent = collectionData?.notes || '';
    }

    function renderStats() {
        const stats = collectionData?.stats || {};
        const groups = collectionData?.item_groups || [];
        const totalSold = groups.reduce((acc, g) => acc + Number(g.total_sold || 0), 0);

        const classCount = { best_seller: 0, karrem: 0, fashion: 0, plotesues: 0 };
        groups.forEach(g => {
            const cls = g.classification || 'plotesues';
            if (classCount[cls] !== undefined) classCount[cls]++;
        });

        const grid = document.getElementById('cdStatsGrid');
        clearChildren(grid);

        const cells = [
            { lbl: 'Grupe produktesh', val: String(stats.total_groups || groups.length || 0) },
            { lbl: 'Stok total',       val: (stats.total_stock || 0).toLocaleString('sq-AL'), sub: 'copë' },
            { lbl: 'Vlera stokut',     val: fmtLek(stats.total_stock_value) },
            { lbl: 'Shitur',           val: String(totalSold), success: totalSold > 0 },
            { lbl: 'Range çmimi',
                val: (stats.min_price || stats.max_price) ? (fmtLek(stats.min_price) + ' – ' + fmtLek(stats.max_price)) : '—',
                small: true },
            { lbl: 'Klasifikime', classification: classCount },
        ];

        cells.forEach(c => {
            const wrap = el('div', 'cd-stat');
            wrap.appendChild(el('div', 'cd-stat-lbl', c.lbl));

            if (c.classification) {
                const val = el('div', 'cd-stat-val cd-stat-mini');
                val.appendChild(el('span', 'cd-class-tag-brown', c.classification.best_seller + ' Best'));
                val.appendChild(document.createTextNode(' · '));
                val.appendChild(el('span', 'cd-class-tag-green', c.classification.karrem + ' Karr'));
                val.appendChild(el('br'));
                val.appendChild(el('span', 'cd-class-tag-pink', c.classification.fashion + ' Fash'));
                val.appendChild(document.createTextNode(' · '));
                val.appendChild(el('span', 'cd-class-tag-blue', c.classification.plotesues + ' Plot'));
                wrap.appendChild(val);
            } else {
                const valClass = 'cd-stat-val'
                    + (c.success ? ' success' : '')
                    + (c.small ? ' cd-stat-small' : '');
                wrap.appendChild(el('div', valClass, c.val));
            }

            if (c.sub) wrap.appendChild(el('div', 'cd-stat-sub', c.sub));
            grid.appendChild(wrap);
        });
    }

    function renderFilterBar() {
        const groups = collectionData?.item_groups || [];
        const classCount = {};
        groups.forEach(g => {
            const cls = g.classification || 'plotesues';
            classCount[cls] = (classCount[cls] || 0) + 1;
        });

        const bar = document.getElementById('cdFilterBar');
        clearChildren(bar);

        const filters = [
            { key: 'all', label: 'Të gjitha', count: groups.length },
            { key: 'best_seller', label: 'Best Seller', count: classCount.best_seller || 0 },
            { key: 'karrem', label: 'Karrem', count: classCount.karrem || 0 },
            { key: 'fashion', label: 'Fashion', count: classCount.fashion || 0 },
            { key: 'plotesues', label: 'Plotësues', count: classCount.plotesues || 0 },
        ];

        filters.forEach(f => {
            if (f.key !== 'all' && f.count === 0) return;
            const btn = el('button', 'cd-filter-btn' + (currentFilter === f.key ? ' active' : ''), f.label);
            btn.type = 'button';
            btn.appendChild(el('span', 'cd-count', '(' + f.count + ')'));
            btn.addEventListener('click', () => filterByClass(f.key));
            bar.appendChild(btn);
        });
    }

    function renderProducts() {
        const allGroups = collectionData?.item_groups || [];
        const groups = currentFilter === 'all'
            ? allGroups
            : allGroups.filter(g => (g.classification || 'plotesues') === currentFilter);

        const grid = document.getElementById('cdGrid');
        clearChildren(grid);

        document.getElementById('cdShownCount').textContent = groups.length;

        if (!groups.length) {
            const empty = el('div', 'cd-empty',
                allGroups.length === 0 ? 'Asnjë produkt në këtë koleksion.' : 'Asnjë produkt me këtë klasifikim.');
            grid.appendChild(empty);
            return;
        }

        const realIndexMap = new Map(allGroups.map((g, i) => [g, i]));

        groups.forEach(g => {
            grid.appendChild(buildCard(g, realIndexMap.get(g) ?? 0));
        });
    }

    function buildCard(g, realIdx) {
        const cls = g.classification || 'plotesues';
        const cs = classStyles[cls] || classStyles.plotesues;
        const hasDiscount = g.pricelist_price && g.avg_price && Number(g.pricelist_price) < Number(g.avg_price);
        const discount = hasDiscount ? calcDiscount(g.avg_price, g.pricelist_price) : 0;
        const finalPrice = hasDiscount ? g.pricelist_price : g.avg_price;

        const card = el('div', 'cd-card');
        card.setAttribute('data-group-id', String(Number(g.id)));

        // Image area
        const imgWrap = el('div', 'cd-card-img-wrap');
        imgWrap.addEventListener('click', () => openProductModal(realIdx));

        if (g.image_url) {
            const img = document.createElement('img');
            img.src = proxyImg(g.image_url);
            img.alt = '';
            img.loading = 'lazy';
            imgWrap.appendChild(img);
        } else {
            const icon = document.createElement('iconify-icon');
            icon.setAttribute('icon', 'heroicons-outline:photo');
            icon.setAttribute('width', '32');
            imgWrap.appendChild(icon);
        }

        if (hasDiscount) {
            imgWrap.appendChild(el('span', 'cd-card-discount', '-' + discount + '%'));
        }

        card.appendChild(imgWrap);

        // Body
        const body = el('div', 'cd-card-body');

        // Head — name + badge
        const head = el('div', 'cd-card-head');
        head.addEventListener('click', () => openProductModal(realIdx));

        const name = el('div', 'cd-card-name', g.name || '');
        name.title = g.name || '';
        head.appendChild(name);

        const badge = el('span', 'cd-class-badge', cs.label);
        badge.style.background = cs.bg;
        badge.style.color = cs.text;
        head.appendChild(badge);

        body.appendChild(head);

        // Meta
        const parts = [g.code, g.vendor_name, g.category_name].filter(Boolean);
        const meta = el('div', 'cd-card-meta', parts.join(' · '));
        meta.title = meta.textContent;
        body.appendChild(meta);

        // Price + stock row
        const priceRow = el('div', 'cd-card-price-row');

        const priceLeft = document.createElement('div');
        if (hasDiscount) {
            priceLeft.appendChild(el('span', 'cd-price discount', fmtLek(g.pricelist_price)));
            priceLeft.appendChild(el('span', 'cd-old-price', fmtLek(g.avg_price)));
        } else {
            priceLeft.appendChild(el('span', 'cd-price', fmtLek(finalPrice)));
        }
        priceRow.appendChild(priceLeft);

        const stock = el('div', 'cd-stock');
        stock.appendChild(document.createTextNode(
            Number(g.variations_count || 0) + ' var · ' + Number(g.total_stock || 0) + ' stk'
        ));
        if (g.total_sold) {
            stock.appendChild(el('br'));
            stock.appendChild(el('span', 'cd-sold', Number(g.total_sold) + ' shitur'));
        }
        priceRow.appendChild(stock);

        body.appendChild(priceRow);

        // Date assignment row
        body.appendChild(buildDateRow(g));

        card.appendChild(body);
        return card;
    }

    function buildDateRow(g) {
        const groupId = Number(g.id);
        const assigned = Array.isArray(g.assigned_dates) ? [...g.assigned_dates] : [];
        assigned.sort((a, b) => String(a.date).localeCompare(String(b.date)));

        const row = el('div', 'cd-date-row');
        row.addEventListener('click', e => e.stopPropagation());

        row.appendChild(el('span', 'cd-date-row-icon', '📅'));

        if (assigned.length === 0) {
            const btn = el('button', 'cd-date-add empty-state', '+ Cakto datë për këtë produkt');
            btn.type = 'button';
            btn.addEventListener('click', (e) => { e.stopPropagation(); openDatePicker(groupId, btn); });
            row.appendChild(btn);
            return row;
        }

        assigned.forEach(a => {
            const pill = el('span', a.is_primary ? 'cd-date-pill' : 'cd-date-pill is-remarketing',
                (a.is_primary ? '' : '🔁 ') + fmtPillLabel(a.date));

            const x = el('span', 'cd-date-pill-x', '×');
            x.title = 'Hiq caktimin';
            x.addEventListener('click', (e) => { e.stopPropagation(); removeDate(groupId, Number(a.id)); });
            pill.appendChild(x);

            row.appendChild(pill);
        });

        const addBtn = el('button', 'cd-date-add', '+ ditë re-marketing');
        addBtn.type = 'button';
        addBtn.addEventListener('click', (e) => { e.stopPropagation(); openDatePicker(groupId, addBtn); });
        row.appendChild(addBtn);

        return row;
    }

    // ─── Status change ─────────────────────────────────

    window.changeCollectionStatus = async function(newStatus) {
        if (!WEEK_ID) return;
        try {
            const res = await fetch(STATUS_URL, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                body: JSON.stringify({ status: newStatus }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            collectionData.status = newStatus;
            document.querySelectorAll('#cdStatuses .cd-status-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.st === newStatus);
            });
        } catch (e) {
            alert('Ndryshimi i statusit dështoi: ' + e.message);
        }
    };

    // ─── Filter ────────────────────────────────────────

    function filterByClass(key) {
        currentFilter = key;
        renderFilterBar();
        renderProducts();
    }

    // ─── Date picker ───────────────────────────────────

    window.openDatePicker = function(groupId, anchorBtn) {
        document.querySelectorAll('.cd-date-picker').forEach(p => p.remove());

        const group = (collectionData?.item_groups || []).find(g => Number(g.id) === Number(groupId));
        if (!group) return;

        const assigned = Array.isArray(group.assigned_dates) ? group.assigned_dates : [];
        const assignedMap = new Map(assigned.map(a => [a.date, a]));
        const collectionDates = getCollectionDates();
        if (collectionDates.length === 0) return;

        const picker = el('div', 'cd-date-picker');
        picker.addEventListener('click', e => e.stopPropagation());

        picker.appendChild(el('div', 'cd-date-picker-title', 'Zgjidh ditë (vetëm brenda koleksionit)'));

        const grid = el('div', 'cd-date-picker-grid');

        DAY_INITIALS_SQ.forEach(letter => {
            grid.appendChild(el('div', 'cd-date-wkd', letter));
        });

        const firstDay = parseYmd(collectionDates[0]);
        const startWeekday = firstDay.getDay();
        for (let i = 0; i < startWeekday; i++) {
            grid.appendChild(document.createElement('div'));
        }

        collectionDates.forEach(ymd => {
            const dt = parseYmd(ymd);
            const isAssigned = assignedMap.has(ymd);
            const dayBtn = el('button', null, String(dt.getDate()));
            dayBtn.type = 'button';
            if (isAssigned) {
                dayBtn.className = assignedMap.get(ymd).is_primary
                    ? 'cd-date-day assigned'
                    : 'cd-date-day assigned-remarketing';
                dayBtn.title = 'Tashmë i caktuar — përdor × për ta hequr';
            } else {
                dayBtn.className = 'cd-date-day';
                dayBtn.title = 'Cakto për ' + fmtPillLabel(ymd);
                dayBtn.addEventListener('click', () => {
                    const primaries = (group.assigned_dates || []).filter(a => a.is_primary).length;
                    assignDate(groupId, ymd, primaries === 0);
                    picker.remove();
                });
            }
            grid.appendChild(dayBtn);
        });

        picker.appendChild(grid);
        anchorBtn.parentNode.appendChild(picker);

        const closeOnOutside = (e) => {
            if (!picker.contains(e.target) && e.target !== anchorBtn) {
                picker.remove();
                document.removeEventListener('click', closeOnOutside, true);
            }
        };
        setTimeout(() => document.addEventListener('click', closeOnOutside, true), 0);
    };

    async function assignDate(groupId, ymd, isPrimary) {
        try {
            const res = await fetch(DATES_URL_FOR(groupId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ date: ymd, is_primary: isPrimary }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const body = await res.json();
            applyLocalAssignmentChange(groupId, group => {
                group.assigned_dates = group.assigned_dates || [];
                if (body.duplicate) return;
                group.assigned_dates.push({
                    id: body.id,
                    date: body.date,
                    is_primary: body.is_primary,
                });
            });
        } catch (e) {
            alert('Caktimi i ditës dështoi: ' + e.message);
        }
    }

    async function removeDate(groupId, dateId) {
        try {
            const res = await fetch(DATES_URL_FOR(groupId) + '/' + Number(dateId), {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            applyLocalAssignmentChange(groupId, group => {
                group.assigned_dates = (group.assigned_dates || []).filter(a => Number(a.id) !== Number(dateId));
            });
        } catch (e) {
            alert('Heqja e caktimit dështoi: ' + e.message);
        }
    }

    function applyLocalAssignmentChange(groupId, mutator) {
        const group = (collectionData?.item_groups || []).find(g => Number(g.id) === Number(groupId));
        if (!group) return;
        mutator(group);
        renderProducts();
    }

    // ─── Product modal ─────────────────────────────────

    window.openProductModal = function(groupIndex) {
        const g = (collectionData?.item_groups || [])[groupIndex];
        if (!g) return;

        const cls = g.classification || 'plotesues';
        const cs = classStyles[cls] || classStyles.plotesues;
        const hasDiscount = g.pricelist_price && g.avg_price && Number(g.pricelist_price) < Number(g.avg_price);
        const discount = hasDiscount ? calcDiscount(g.avg_price, g.pricelist_price) : 0;
        const stockValue = (g.total_stock || 0) * ((hasDiscount ? g.pricelist_price : g.avg_price) || 0);

        const inner = document.getElementById('cdModalInner');
        clearChildren(inner);

        // Image section
        const imgWrap = document.createElement('div');
        imgWrap.style.cssText = 'position:relative; background:#f8fafc;';

        if (g.image_url) {
            const img = document.createElement('img');
            img.src = proxyImg(g.image_url);
            img.style.cssText = 'max-width:100%;max-height:70vh;display:block;margin:0 auto;';
            imgWrap.appendChild(img);
        } else {
            const ph = document.createElement('div');
            ph.style.cssText = 'height:160px;display:flex;align-items:center;justify-content:center;';
            const icon = document.createElement('iconify-icon');
            icon.setAttribute('icon', 'heroicons-outline:photo');
            icon.setAttribute('width', '48');
            icon.style.color = '#d1d5db';
            ph.appendChild(icon);
            imgWrap.appendChild(ph);
        }

        const closeBtn = document.createElement('button');
        closeBtn.style.cssText = 'position:absolute;top:8px;right:8px;width:30px;height:30px;border:none;background:rgba(255,255,255,0.9);cursor:pointer;border-radius:8px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.1);';
        const closeIcon = document.createElement('iconify-icon');
        closeIcon.setAttribute('icon', 'heroicons-outline:x-mark');
        closeIcon.setAttribute('width', '16');
        closeIcon.style.color = '#475569';
        closeBtn.appendChild(closeIcon);
        closeBtn.addEventListener('click', closeProductModal);
        imgWrap.appendChild(closeBtn);

        if (hasDiscount) {
            const badge = document.createElement('span');
            badge.style.cssText = 'position:absolute;top:8px;left:8px;background:#dc2626;color:#fff;font-size:12px;font-weight:700;padding:4px 10px;border-radius:6px;';
            badge.textContent = '-' + discount + '%';
            imgWrap.appendChild(badge);
        }

        inner.appendChild(imgWrap);

        // Info section
        const info = document.createElement('div');
        info.style.cssText = 'padding:14px 16px;';

        const row1 = document.createElement('div');
        row1.style.cssText = 'display:flex; justify-content:space-between; align-items:center;';
        const nm = el('div', null, g.name || '');
        nm.style.cssText = 'font-size:15px; font-weight:700; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1;';
        row1.appendChild(nm);
        const bg = el('span', 'cd-class-badge', cs.label);
        bg.style.cssText = `background:${cs.bg};color:${cs.text};font-size:9px;padding:2px 7px;margin-left:8px;flex-shrink:0;`;
        row1.appendChild(bg);
        info.appendChild(row1);

        const row2 = el('div', null, [g.code, g.vendor_name, g.category_name].filter(Boolean).join(' · '));
        row2.style.cssText = 'font-size:10px; color:#94a3b8; margin-top:2px;';
        info.appendChild(row2);

        // Price + stats bar
        const row3 = document.createElement('div');
        row3.style.cssText = 'display:flex; align-items:center; margin-top:10px; padding:10px 12px; background:#f8fafc; border-radius:8px;';

        const priceBlock = document.createElement('div');
        priceBlock.style.cssText = 'flex-shrink:0; padding-right:12px; border-right:1px solid #e2e8f0;';
        const priceTop = el('div', null, fmtLek(hasDiscount ? g.pricelist_price : g.avg_price));
        priceTop.style.cssText = `font-size:17px; font-weight:800; line-height:1; color:${hasDiscount ? '#dc2626' : '#0f172a'};`;
        priceBlock.appendChild(priceTop);
        if (hasDiscount) {
            const old = el('span', 'cd-old-price', fmtLek(g.avg_price));
            old.style.fontSize = '10px';
            priceBlock.appendChild(old);
        }
        row3.appendChild(priceBlock);

        const statsWrap = document.createElement('div');
        statsWrap.style.cssText = 'display:flex; gap:10px; padding-left:12px; flex:1; justify-content:space-around;';
        [
            { v: Number(g.total_stock || 0),       lbl: 'Stok',   color: '#0f172a' },
            { v: Number(g.total_sold || 0),        lbl: 'Shitur', color: g.total_sold ? '#22c55e' : '#94a3b8' },
            { v: Number(g.variations_count || 0),  lbl: 'Var',    color: '#0f172a' },
            { v: fmtLek(stockValue),               lbl: 'Vlerë',  color: '#166534', small: true },
        ].forEach(s => {
            const cell = document.createElement('div');
            cell.style.textAlign = 'center';
            const val = el('div', null, s.v);
            val.style.cssText = `font-size:${s.small ? '12' : '14'}px;font-weight:700;color:${s.color};`;
            cell.appendChild(val);
            const lb = el('div', null, s.lbl);
            lb.style.cssText = 'font-size:8px;color:#94a3b8;text-transform:uppercase;';
            cell.appendChild(lb);
            statsWrap.appendChild(cell);
        });
        row3.appendChild(statsWrap);
        info.appendChild(row3);

        inner.appendChild(info);

        document.getElementById('cdModalBg').classList.add('open');
    };

    window.closeProductModal = function() {
        document.getElementById('cdModalBg').classList.remove('open');
    };

    // ─── Init ──────────────────────────────────────────

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('cdModalBg').classList.contains('open')) {
            closeProductModal();
        }
    });

    renderNotes();
    renderStats();
    renderFilterBar();
    renderProducts();
})();
</script>
@endsection
