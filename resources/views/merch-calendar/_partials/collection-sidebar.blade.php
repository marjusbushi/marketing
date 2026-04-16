{{-- Collection Detail Sidebar — accessible from Calendar, Timeline, Gantt --}}
<div id="collectionSidebar" style="display:none; position:fixed; top:0; right:0; bottom:0; width:420px; z-index:9985; background:#fff; border-left:1px solid #e5e7eb; box-shadow:-4px 0 24px rgba(0,0,0,0.08); font-family:Inter,system-ui,sans-serif; flex-direction:column; overflow:hidden;">
    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0;">
        <span id="csSidebarTitle" style="font-size:16px; font-weight:700; color:#0f172a;">Collection</span>
        <button onclick="closeCollectionSidebar()" style="width:28px; height:28px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
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

    function fmtLek(val) {
        if (!val && val !== 0) return '—';
        return Math.round(Number(val)).toLocaleString('sq-AL') + ' L';
    }

    function calcDiscount(original, discounted) {
        if (!original || !discounted || discounted >= original) return 0;
        return Math.round((1 - discounted / original) * 100);
    }

    // ─── Sidebar ────────────────────────────────

    window.showWeekDetail = async function(weekId) {
        currentSidebarWeekId = weekId;
        currentFilter = 'all';
        const sidebar = document.getElementById('collectionSidebar');
        sidebar.style.display = 'flex';
        document.getElementById('csSidebarBody').innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;font-size:12px;">Loading...</div>';

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

                html += `<div class="cs-product-card" onclick="openProductModal(${realIdx})">
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
                </div>`;
            });
        }

        body.innerHTML = html;
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
