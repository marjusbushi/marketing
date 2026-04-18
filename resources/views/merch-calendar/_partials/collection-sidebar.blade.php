{{-- Collection Detail Sidebar — accessible from Calendar, Timeline, Gantt --}}
<div id="collectionSidebar" style="display:none; position:fixed; top:0; right:0; bottom:0; width:420px; z-index:9985; background:#fff; border-left:1px solid #e5e7eb; box-shadow:-4px 0 24px rgba(0,0,0,0.08); font-family:Inter,system-ui,sans-serif; flex-direction:column; overflow:hidden;">
    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0; gap:8px;">
        <span id="csSidebarTitle" style="font-size:16px; font-weight:700; color:#0f172a; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Collection</span>
        <a id="csQuickScanBtn" href="#" title="Quick Scan me palmar" style="display:inline-flex; align-items:center; gap:4px; padding:5px 10px; font-size:11px; font-weight:600; color:#4338ca; background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px; text-decoration:none; flex-shrink:0;">
            📷 Quick Scan
        </a>
        <button onclick="closeCollectionSidebar()" style="width:28px; height:28px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px; flex-shrink:0;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
            <iconify-icon icon="heroicons-outline:x-mark" width="18" style="color:#94a3b8;"></iconify-icon>
        </button>
    </div>
    {{-- Scrollable content --}}
    <div id="csSidebarBody" style="flex:1; overflow-y:auto; padding:0;">
        <div style="text-align:center; padding:40px; color:#94a3b8; font-size:12px;">Loading...</div>
    </div>
</div>

{{-- Product Preview Modal --}}
<div id="csProductModal" style="display:none; position:fixed; inset:0; z-index:9990; background:rgba(0,0,0,0.4);" onclick="if(event.target===this)closeProductModal()">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:14px; width:520px; max-width:95vw; max-height:95vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div id="csProductModalBody" style="padding:0;"></div>
    </div>
</div>

<style>
    .cs-status-btn { display:inline-flex; align-items:center; gap:4px; padding:5px 12px; font-size:11px; font-weight:500; border-radius:6px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; transition:all 0.1s; }
    .cs-status-btn:hover { border-color:#cbd5e1; }
    .cs-status-btn.active { font-weight:600; }
    .cs-status-btn.active[data-st="planned"] { background:#f1f5f9; color:#475569; border-color:#94a3b8; }
    .cs-status-btn.active[data-st="active"] { background:#dcfce7; color:#166534; border-color:#22c55e; }
    .cs-status-btn.active[data-st="completed"] { background:#dbeafe; color:#1e40af; border-color:#3b82f6; }

    .cs-product-card { display:flex; gap:10px; padding:10px 16px; border-bottom:1px solid #f8fafc; transition:background 0.1s; cursor:pointer; }
    .cs-product-card:hover { background:#f8fafc; }
    .cs-product-img { width:64px; height:64px; border-radius:8px; object-fit:cover; flex-shrink:0; background:#f1f5f9; }
    .cs-product-placeholder { width:64px; height:64px; border-radius:8px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .cs-class-badge { display:inline-flex; font-size:9px; font-weight:600; padding:2px 6px; border-radius:4px; }
    .cs-vendor-tag { font-size:9px; color:#94a3b8; background:#f8fafc; padding:1px 5px; border-radius:3px; }
    .cs-discount-badge { display:inline-flex; font-size:9px; font-weight:700; padding:2px 6px; border-radius:4px; background:#fee2e2; color:#dc2626; }
    .cs-old-price { font-size:10px; color:#94a3b8; text-decoration:line-through; }

    .cs-stat { text-align:center; }
    .cs-stat-value { font-size:16px; font-weight:700; color:#0f172a; }
    .cs-stat-label { font-size:9px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.3px; margin-top:2px; }

    .cs-filter-btn { padding:3px 8px; font-size:10px; border-radius:4px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; }
    .cs-filter-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; }
    .cs-filter-btn:hover:not(.active) { background:#f8fafc; }

    /* ─── Inline date assignment row (per produkt) ──────────────── */
    .cs-date-row { display:flex; align-items:center; gap:8px; margin-top:10px; padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:7px; flex-wrap:wrap; position:relative; }
    .cs-date-row-icon { font-size:12px; color:#6366f1; flex-shrink:0; }
    .cs-date-pill { display:inline-flex; align-items:center; gap:4px; background:#eef2ff; color:#3730a3; font-size:11px; font-weight:600; padding:4px 9px; border-radius:14px; border:1px solid #c7d2fe; }
    .cs-date-pill.is-remarketing { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
    .cs-date-pill-x { color:#94a3b8; font-size:11px; cursor:pointer; margin-left:2px; padding:0 2px; line-height:1; }
    .cs-date-pill-x:hover { color:#ef4444; }
    .cs-date-add { background:transparent; border:1px dashed #cbd5e1; color:#64748b; font-size:11px; padding:4px 9px; border-radius:14px; cursor:pointer; }
    .cs-date-add:hover { border-color:#6366f1; color:#6366f1; background:#eef2ff; }
    .cs-date-add.empty-state { width:100%; text-align:left; padding:5px 10px; }

    /* Date picker dropdown */
    .cs-date-picker { position:absolute; top:100%; left:0; margin-top:4px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.08); padding:8px; z-index:60; width:240px; }
    .cs-date-picker-title { font-size:10px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.05em; padding:4px 8px 8px; }
    .cs-date-picker-grid { display:grid; grid-template-columns:repeat(7, 1fr); gap:2px; }
    .cs-date-wkd { text-align:center; font-size:9px; color:#94a3b8; padding:4px 0; text-transform:uppercase; }
    .cs-date-day { text-align:center; padding:7px 2px; border-radius:5px; font-size:11px; cursor:pointer; color:#475569; border:none; background:transparent; }
    .cs-date-day:hover:not(.disabled) { background:#eef2ff; color:#3730a3; }
    .cs-date-day.disabled { color:#cbd5e1; cursor:not-allowed; }
    .cs-date-day.assigned { background:#dbeafe; color:#1e40af; font-weight:600; }
    .cs-date-day.assigned-remarketing { background:#fed7aa; color:#9a3412; font-weight:600; }
    .cs-date-add-mode { font-size:10px; color:#64748b; padding:6px 8px 4px; border-top:1px solid #f1f5f9; margin-top:6px; }
    .cs-date-add-mode label { display:flex; align-items:center; gap:5px; cursor:pointer; }
    .cs-date-add-mode input { margin:0; }
</style>

<script>
(function() {
    const DETAIL_API = @json(url('/marketing/merch-calendar/api/weeks'));
    const WEEKS_API = @json(route('marketing.merch-calendar.api.weeks'));
    const CDN_PROXY = @json(route('marketing.cdn-image'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function proxyImg(url) {
        if (!url) return null;
        if (url.startsWith('https://web-cdn.zeroabsolute.com/')) {
            return CDN_PROXY + '?url=' + encodeURIComponent(url);
        }
        return url;
    }

    let currentSidebarWeekId = null;
    let currentSidebarData = null;
    let currentFilter = 'all';
    const weekDetailCache = new Map();

    const classStyles = {
        best_seller: { bg:'#fef3c7', text:'#92400e', label:'Best Seller' },
        fashion:     { bg:'#fce7f3', text:'#9d174d', label:'Fashion' },
        karrem:      { bg:'#d1fae5', text:'#065f46', label:'Karrem' },
        plotesues:   { bg:'#e0e7ff', text:'#3730a3', label:'Plotësues' },
    };

    const DAY_NAMES_SQ = ['Die', 'Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht'];
    const DAY_INITIALS_SQ = ['D', 'H', 'M', 'M', 'E', 'P', 'S'];
    const MONTH_NAMES_SQ = ['Jan', 'Shk', 'Mar', 'Pri', 'Maj', 'Qer', 'Kor', 'Gsh', 'Sht', 'Tet', 'Nën', 'Dhj'];

    function fmtLek(val) {
        if (!val && val !== 0) return '—';
        return Math.round(Number(val)).toLocaleString('sq-AL') + ' L';
    }

    function calcDiscount(original, discounted) {
        if (!original || !discounted || discounted >= original) return 0;
        return Math.round((1 - discounted / original) * 100);
    }

    // ─── Date helpers ───────────────────────────

    // Parse 'YYYY-MM-DD' as a local date (no timezone shenanigans).
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
        if (!currentSidebarData) return [];
        const start = parseYmd(currentSidebarData.week_start);
        const end = parseYmd(currentSidebarData.week_end);
        const dates = [];
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            dates.push(toYmd(d));
        }
        return dates;
    }

    // ─── Sidebar ────────────────────────────────

    window.showWeekDetail = async function(weekId) {
        currentSidebarWeekId = weekId;
        currentFilter = 'all';
        const sidebar = document.getElementById('collectionSidebar');
        sidebar.style.display = 'flex';
        document.getElementById('csSidebarBody').innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;font-size:12px;">Loading...</div>';
        const quickScanBtn = document.getElementById('csQuickScanBtn');
        if (quickScanBtn) {
            quickScanBtn.href = '/marketing/merch-calendar/quick-scan?week=' + Number(weekId);
        }

        if (weekDetailCache.has(weekId)) {
            currentSidebarData = weekDetailCache.get(weekId);
            renderSidebarContent(currentSidebarData);
            return;
        }

        try {
            const res = await fetch(`${DETAIL_API}/${weekId}`, { headers:{'Accept':'application/json'} });
            const data = await res.json();
            weekDetailCache.set(weekId, data);
            currentSidebarData = data;
            renderSidebarContent(data);
        } catch(e) {
            document.getElementById('csSidebarBody').innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;font-size:12px;">Failed to load</div>';
        }
    };

    window.closeCollectionSidebar = function() {
        document.getElementById('collectionSidebar').style.display = 'none';
        currentSidebarWeekId = null;
        currentSidebarData = null;
    };

    window.changeSidebarStatus = async function(newStatus) {
        if (!currentSidebarWeekId) return;
        try {
            await fetch(`${WEEKS_API}/${currentSidebarWeekId}/status`, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
                body:JSON.stringify({status:newStatus}),
            });
            weekDetailCache.delete(currentSidebarWeekId);
            showWeekDetail(currentSidebarWeekId);
            if (typeof calendarInstance !== 'undefined' && calendarInstance) calendarInstance.refetchEvents();
        } catch(e) { console.error(e); }
    };

    window.filterSidebarGroups = function(filter) {
        currentFilter = filter;
        if (currentSidebarData) renderSidebarContent(currentSidebarData);
    };

    // ─── Product Preview Modal ──────────────────

    window.openProductModal = function(groupIndex) {
        if (!currentSidebarData) return;
        const g = currentSidebarData.item_groups[groupIndex];
        if (!g) return;

        const cls = g.classification || 'plotesues';
        const cs = classStyles[cls] || classStyles.plotesues;
        const hasDiscount = g.pricelist_price && g.avg_price && Number(g.pricelist_price) < Number(g.avg_price);
        const discount = hasDiscount ? calcDiscount(g.avg_price, g.pricelist_price) : 0;
        const finalPrice = hasDiscount ? g.pricelist_price : g.avg_price;
        const stockValue = (g.total_stock || 0) * (finalPrice || 0);

        let html = '';

        // Image — fits in modal without scroll
        html += `<div style="position:relative; background:#f8fafc;">`;
        if (g.image_url) {
            html += `<img src="${proxyImg(g.image_url)}" style="max-width:100%;max-height:70vh;display:block;margin:0 auto;">`;
        } else {
            html += `<div style="height:160px;display:flex;align-items:center;justify-content:center;"><iconify-icon icon="heroicons-outline:photo" width="48" style="color:#d1d5db;"></iconify-icon></div>`;
        }
        html += `<button onclick="closeProductModal()" style="position:absolute;top:8px;right:8px;width:30px;height:30px;border:none;background:rgba(255,255,255,0.9);backdrop-filter:blur(4px);cursor:pointer;border-radius:8px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <iconify-icon icon="heroicons-outline:x-mark" width="16" style="color:#475569;"></iconify-icon>
        </button>`;
        if (hasDiscount) {
            html += `<span style="position:absolute;top:8px;left:8px;background:#dc2626;color:#fff;font-size:12px;font-weight:700;padding:4px 10px;border-radius:6px;">-${discount}%</span>`;
        }
        html += `</div>`;

        // Info — compact, no scroll needed
        html += `<div style="padding:14px 16px;">`;

        // Row 1: Name + badge
        html += `<div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:15px; font-weight:700; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1;">${g.name}</div>
            <span class="cs-class-badge" style="background:${cs.bg};color:${cs.text};font-size:9px;padding:2px 7px;flex-shrink:0;margin-left:8px;">${cs.label}</span>
        </div>`;

        // Row 2: Code + vendor + category
        html += `<div style="font-size:10px; color:#94a3b8; margin-top:2px;">${g.code || ''} · ${g.vendor_name || ''} · ${g.category_name || ''}</div>`;

        // Row 3: Price + Stats — all in one compact bar
        html += `<div style="display:flex; align-items:center; margin-top:10px; padding:10px 12px; background:#f8fafc; border-radius:8px; gap:0;">`;

        // Price block
        if (hasDiscount) {
            html += `<div style="flex-shrink:0; padding-right:12px; border-right:1px solid #e2e8f0;">
                <div style="font-size:17px; font-weight:800; color:#dc2626; line-height:1;">${fmtLek(g.pricelist_price)}</div>
                <span class="cs-old-price" style="font-size:10px;">${fmtLek(g.avg_price)}</span>
            </div>`;
        } else {
            html += `<div style="flex-shrink:0; padding-right:12px; border-right:1px solid #e2e8f0;">
                <div style="font-size:17px; font-weight:800; color:#0f172a; line-height:1;">${fmtLek(g.avg_price)}</div>
            </div>`;
        }

        // Stats
        html += `<div style="display:flex; gap:10px; padding-left:12px; flex:1; justify-content:space-around;">
            <div style="text-align:center;"><div style="font-size:14px;font-weight:700;color:#0f172a;">${g.total_stock || 0}</div><div style="font-size:8px;color:#94a3b8;text-transform:uppercase;">Stok</div></div>
            <div style="text-align:center;"><div style="font-size:14px;font-weight:700;color:${g.total_sold ? '#22c55e' : '#94a3b8'};">${g.total_sold || 0}</div><div style="font-size:8px;color:#94a3b8;text-transform:uppercase;">Shitur</div></div>
            <div style="text-align:center;"><div style="font-size:14px;font-weight:700;color:#0f172a;">${g.variations_count || 0}</div><div style="font-size:8px;color:#94a3b8;text-transform:uppercase;">Var</div></div>
            <div style="text-align:center;"><div style="font-size:12px;font-weight:700;color:#166534;">${fmtLek(stockValue)}</div><div style="font-size:8px;color:#94a3b8;text-transform:uppercase;">Vlerë</div></div>
        </div>`;

        html += `</div></div>`;

        document.getElementById('csProductModalBody').innerHTML = html;
        document.getElementById('csProductModal').style.display = 'block';
    };

    window.closeProductModal = function() {
        document.getElementById('csProductModal').style.display = 'none';
    };

    // ─── Render Sidebar ─────────────────────────

    function renderSidebarContent(data) {
        document.getElementById('csSidebarTitle').textContent = data.name || 'Collection';
        const body = document.getElementById('csSidebarBody');
        const allGroups = data.item_groups || [];
        const stats = data.stats || {};
        const st = data.status || 'planned';
        const groupIndexMap = new Map(allGroups.map((group, index) => [group, index]));

        const groups = currentFilter === 'all'
            ? allGroups
            : allGroups.filter(g => (g.classification || 'plotesues') === currentFilter);

        const classCount = {};
        allGroups.forEach(g => {
            const cls = g.classification || 'plotesues';
            classCount[cls] = (classCount[cls] || 0) + 1;
        });

        let html = '';

        // Cover image
        if (data.cover_image_url) {
            html += `<div style="width:100%;"><img src="${proxyImg(data.cover_image_url)}" style="width:100%;max-height:180px;object-fit:cover;display:block;"></div>`;
        }

        // Dates + status
        html += `<div style="padding:14px 16px; border-bottom:1px solid #f1f5f9;">
            <div style="font-size:12px; color:#64748b; margin-bottom:10px;">
                <iconify-icon icon="heroicons-outline:calendar" width="13" style="vertical-align:-2px;"></iconify-icon>
                ${data.week_start} → ${data.week_end}
            </div>
            <div style="display:flex; gap:4px;">
                ${['planned','active','completed'].map(s => {
                    const labels = {planned:'Planifikuar',active:'Aktiv',completed:'Kompletuar'};
                    return `<button class="cs-status-btn${s===st?' active':''}" data-st="${s}" onclick="changeSidebarStatus('${s}')">${labels[s]}</button>`;
                }).join('')}
            </div>
        </div>`;

        // Stats
        html += `<div style="display:grid; grid-template-columns:repeat(5,1fr); padding:14px 16px; border-bottom:1px solid #f1f5f9; gap:4px;">
            <div class="cs-stat"><div class="cs-stat-value">${stats.total_groups || 0}</div><div class="cs-stat-label">Grupe</div></div>
            <div class="cs-stat"><div class="cs-stat-value">${(stats.total_stock || 0).toLocaleString('sq-AL')}</div><div class="cs-stat-label">Stok</div></div>
            <div class="cs-stat"><div class="cs-stat-value">${fmtLek(stats.total_stock_value)}</div><div class="cs-stat-label">Vlerë</div></div>
            <div class="cs-stat"><div class="cs-stat-value">${fmtLek(stats.min_price)}</div><div class="cs-stat-label">Min</div></div>
            <div class="cs-stat"><div class="cs-stat-value">${fmtLek(stats.max_price)}</div><div class="cs-stat-label">Max</div></div>
        </div>`;

        // Notes
        if (data.notes) {
            html += `<div style="padding:10px 16px; border-bottom:1px solid #f1f5f9; font-size:12px; color:#64748b; line-height:1.5; max-height:80px; overflow-y:auto;">${data.notes}</div>`;
        }

        // Filter header
        html += `<div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px 8px;">
            <span style="font-size:13px; font-weight:600; color:#0f172a;">Produkte (${groups.length})</span>
            <div style="display:flex; gap:3px; flex-wrap:wrap;">
                <button class="cs-filter-btn${currentFilter==='all'?' active':''}" onclick="filterSidebarGroups('all')">Të gjitha</button>
                ${classCount.best_seller ? `<button class="cs-filter-btn${currentFilter==='best_seller'?' active':''}" onclick="filterSidebarGroups('best_seller')">Best (${classCount.best_seller})</button>` : ''}
                ${classCount.karrem ? `<button class="cs-filter-btn${currentFilter==='karrem'?' active':''}" onclick="filterSidebarGroups('karrem')">Karrem (${classCount.karrem})</button>` : ''}
                ${classCount.fashion ? `<button class="cs-filter-btn${currentFilter==='fashion'?' active':''}" onclick="filterSidebarGroups('fashion')">Fashion (${classCount.fashion})</button>` : ''}
                ${classCount.plotesues ? `<button class="cs-filter-btn${currentFilter==='plotesues'?' active':''}" onclick="filterSidebarGroups('plotesues')">Plotës (${classCount.plotesues})</button>` : ''}
            </div>
        </div>`;

        // Product cards
        if (!groups.length) {
            html += '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;">Asnjë produkt</div>';
        } else {
            groups.forEach((g, idx) => {
                const cls = g.classification || 'plotesues';
                const cs = classStyles[cls] || classStyles.plotesues;
                const hasDiscount = g.pricelist_price && g.avg_price && Number(g.pricelist_price) < Number(g.avg_price);
                const discount = hasDiscount ? calcDiscount(g.avg_price, g.pricelist_price) : 0;
                const finalPrice = hasDiscount ? g.pricelist_price : g.avg_price;

                const realIdx = groupIndexMap.get(g) ?? idx;

                html += `<div class="cs-product-card" data-product-card="${Number(g.id)}" onclick="openProductModal(${realIdx})">
                    <div style="display:flex; gap:10px; width:100%;">
                        ${g.image_url
                            ? `<img class="cs-product-img" src="${proxyImg(g.image_url)}" alt="">`
                            : `<div class="cs-product-placeholder"><iconify-icon icon="heroicons-outline:photo" width="20" style="color:#d1d5db;"></iconify-icon></div>`}
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:12px; font-weight:600; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${g.name}</div>
                            <div style="font-size:10px; color:#94a3b8; margin-top:1px;">${g.code || ''} · <span class="cs-vendor-tag">${g.vendor_name || ''}</span></div>
                            <div style="display:flex; align-items:center; gap:6px; margin-top:5px;">
                                <span class="cs-class-badge" style="background:${cs.bg};color:${cs.text};">${cs.label}</span>
                                <span style="font-size:10px; color:#94a3b8;">${g.category_name || ''}</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px; margin-top:5px;">
                                <span style="font-size:10px; color:#475569;">📦 ${g.variations_count || 0} var</span>
                                <span style="font-size:10px; color:#475569;">📊 ${g.total_stock || 0} stk</span>
                            </div>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; justify-content:center; flex-shrink:0; min-width:75px;">
                            ${hasDiscount
                                ? `<span class="cs-discount-badge">-${discount}%</span>
                                   <div style="font-size:13px; font-weight:700; color:#dc2626; margin-top:3px;">${fmtLek(g.pricelist_price)}</div>
                                   <span class="cs-old-price">${fmtLek(g.avg_price)}</span>`
                                : `<div style="font-size:13px; font-weight:700; color:#0f172a;">${fmtLek(finalPrice)}</div>`}
                            <div style="font-size:10px; color:#64748b; margin-top:2px;">${g.total_stock || 0} copë</div>
                            ${g.total_sold ? `<div style="font-size:10px; color:#22c55e; font-weight:500; margin-top:1px;">${g.total_sold} shitur</div>` : ''}
                        </div>
                    </div>
                </div>`;
            });
        }

        body.innerHTML = html;

        // Date row mounted via DOM API (no innerHTML for user-controllable
        // fields — assigned_dates flow through fmtPillLabel which is a fixed
        // formatter, but defense in depth keeps this off the innerHTML path).
        if (groups.length) {
            groups.forEach(g => {
                const card = body.querySelector(`[data-product-card="${Number(g.id)}"]`);
                if (card) card.appendChild(buildDateRow(g));
            });
        }
    }

    // ─── Inline date assignment row (DOM-built, no innerHTML) ──

    function buildDateRow(g) {
        const groupId = Number(g.id);
        const assigned = Array.isArray(g.assigned_dates) ? [...g.assigned_dates] : [];
        assigned.sort((a, b) => String(a.date).localeCompare(String(b.date)));

        const row = document.createElement('div');
        row.className = 'cs-date-row';
        row.addEventListener('click', e => e.stopPropagation());

        const icon = document.createElement('span');
        icon.className = 'cs-date-row-icon';
        icon.textContent = '📅';
        row.appendChild(icon);

        if (assigned.length === 0) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cs-date-add empty-state';
            btn.textContent = '+ Cakto dite per kete produkt';
            btn.addEventListener('click', (e) => { e.stopPropagation(); csOpenDatePicker(groupId, btn); });
            row.appendChild(btn);
            return row;
        }

        assigned.forEach(a => {
            const pill = document.createElement('span');
            pill.className = a.is_primary ? 'cs-date-pill' : 'cs-date-pill is-remarketing';
            pill.textContent = (a.is_primary ? '' : '🔁 ') + fmtPillLabel(a.date);

            const x = document.createElement('span');
            x.className = 'cs-date-pill-x';
            x.textContent = '×';
            x.title = 'Hiq caktimin';
            x.addEventListener('click', (e) => { e.stopPropagation(); csRemoveDate(groupId, Number(a.id)); });
            pill.appendChild(x);

            row.appendChild(pill);
        });

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'cs-date-add';
        addBtn.textContent = '+ dite re-marketing';
        addBtn.addEventListener('click', (e) => { e.stopPropagation(); csOpenDatePicker(groupId, addBtn); });
        row.appendChild(addBtn);

        return row;
    }

    // Mini-calendar dropdown anchored to the clicked button.
    // Single-picker policy: any other open picker is removed first.
    window.csOpenDatePicker = function(groupId, anchorBtn) {
        document.querySelectorAll('.cs-date-picker').forEach(p => p.remove());

        const group = (currentSidebarData?.item_groups || []).find(g => Number(g.id) === Number(groupId));
        if (!group) return;

        const assigned = Array.isArray(group.assigned_dates) ? group.assigned_dates : [];
        const assignedMap = new Map(assigned.map(a => [a.date, a]));
        const collectionDates = getCollectionDates();
        if (collectionDates.length === 0) return;

        const picker = document.createElement('div');
        picker.className = 'cs-date-picker';
        picker.addEventListener('click', e => e.stopPropagation());

        const title = document.createElement('div');
        title.className = 'cs-date-picker-title';
        title.textContent = 'Zgjidh dite (vetem brenda kolekcionit)';
        picker.appendChild(title);

        const grid = document.createElement('div');
        grid.className = 'cs-date-picker-grid';

        DAY_INITIALS_SQ.forEach(letter => {
            const wkd = document.createElement('div');
            wkd.className = 'cs-date-wkd';
            wkd.textContent = letter;
            grid.appendChild(wkd);
        });

        const firstDay = parseYmd(collectionDates[0]);
        const startWeekday = firstDay.getDay(); // 0=Sun..6=Sat
        for (let i = 0; i < startWeekday; i++) {
            grid.appendChild(document.createElement('div'));
        }

        collectionDates.forEach(ymd => {
            const dt = parseYmd(ymd);
            const isAssigned = assignedMap.has(ymd);
            const dayBtn = document.createElement('button');
            dayBtn.type = 'button';
            dayBtn.textContent = String(dt.getDate());
            if (isAssigned) {
                dayBtn.className = assignedMap.get(ymd).is_primary
                    ? 'cs-date-day assigned'
                    : 'cs-date-day assigned-remarketing';
                dayBtn.title = 'Tashme i caktuar — perdor × per ta hequr';
            } else {
                dayBtn.className = 'cs-date-day';
                dayBtn.title = 'Cakto per ' + fmtPillLabel(ymd);
                dayBtn.addEventListener('click', () => {
                    // First click on any product = primary; subsequent = re-marketing.
                    const primaries = (group.assigned_dates || []).filter(a => a.is_primary).length;
                    csAssignDate(groupId, ymd, primaries === 0);
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

    // POST proxy → DIS internal API. On success, mutate local cache and re-render.
    window.csAssignDate = async function(groupId, ymd, isPrimary) {
        if (!currentSidebarWeekId) return;
        try {
            const url = `/marketing/merch-calendar/api/weeks/${Number(currentSidebarWeekId)}/groups/${Number(groupId)}/dates`;
            const res = await fetch(url, {
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
            console.error('Caktimi deshtoi', e);
            alert('Caktimi i dites deshtoi: ' + e.message);
        }
    };

    window.csRemoveDate = async function(groupId, dateId) {
        if (!currentSidebarWeekId) return;
        try {
            const url = `/marketing/merch-calendar/api/weeks/${Number(currentSidebarWeekId)}/groups/${Number(groupId)}/dates/${Number(dateId)}`;
            const res = await fetch(url, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            applyLocalAssignmentChange(groupId, group => {
                group.assigned_dates = (group.assigned_dates || []).filter(a => Number(a.id) !== Number(dateId));
            });
        } catch (e) {
            console.error('Heqja e caktimit deshtoi', e);
            alert('Heqja e caktimit deshtoi: ' + e.message);
        }
    };

    function applyLocalAssignmentChange(groupId, mutator) {
        if (!currentSidebarData) return;
        const group = (currentSidebarData.item_groups || []).find(g => Number(g.id) === Number(groupId));
        if (!group) return;
        mutator(group);
        if (currentSidebarWeekId) {
            weekDetailCache.set(currentSidebarWeekId, currentSidebarData);
        }
        renderSidebarContent(currentSidebarData);
    }

    // ESC to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('csProductModal').style.display === 'block') {
                closeProductModal();
            } else if (document.getElementById('collectionSidebar').style.display === 'flex') {
                closeCollectionSidebar();
            }
        }
    });
})();
</script>
