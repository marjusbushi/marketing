{{-- Collection Detail Sidebar — accessible from Calendar, Timeline, Gantt --}}
<div id="collectionSidebar" style="display:none; position:fixed; top:0; right:0; bottom:0; width:380px; z-index:9985; background:#fff; border-left:1px solid #e5e7eb; box-shadow:-4px 0 24px rgba(0,0,0,0.08); font-family:Inter,system-ui,sans-serif; flex-direction:column; overflow:hidden;">

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0;">
        <span id="csSidebarTitle" style="font-size:15px; font-weight:600; color:#1e293b;">Collection</span>
        <button onclick="closeCollectionSidebar()" style="width:28px; height:28px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
            <iconify-icon icon="heroicons-outline:x-mark" width="18" style="color:#94a3b8;"></iconify-icon>
        </button>
    </div>

    {{-- Scrollable content --}}
    <div id="csSidebarBody" style="flex:1; overflow-y:auto; padding:0;">
        <div style="text-align:center; padding:40px; color:#94a3b8; font-size:12px;">Loading...</div>
    </div>
</div>

<style>
    .cs-status-btn { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; font-size:11px; font-weight:500; border-radius:6px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; transition:all 0.1s; }
    .cs-status-btn:hover { border-color:#cbd5e1; }
    .cs-status-btn.active { font-weight:600; }
    .cs-status-btn.active[data-st="planned"] { background:#f1f5f9; color:#475569; border-color:#94a3b8; }
    .cs-status-btn.active[data-st="active"] { background:#dcfce7; color:#166534; border-color:#22c55e; }
    .cs-status-btn.active[data-st="completed"] { background:#dbeafe; color:#1e40af; border-color:#3b82f6; }
    .cs-group-card { border:1px solid #f1f5f9; border-radius:8px; padding:10px; margin-bottom:8px; transition:background 0.1s; }
    .cs-group-card:hover { background:#fafbfc; }
    .cs-class-badge { display:inline-flex; font-size:9px; font-weight:600; padding:2px 6px; border-radius:4px; }
    .cs-stat { text-align:center; }
    .cs-stat-value { font-size:16px; font-weight:700; color:#1e293b; }
    .cs-stat-label { font-size:10px; color:#94a3b8; margin-top:1px; }
</style>

<script>
(function() {
    const DETAIL_API = @json(url('/marketing/merch-calendar/api/weeks'));
    const WEEKS_API = @json(route('marketing.merch-calendar.api.weeks'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let currentSidebarWeekId = null;

    const classStyles = {
        best_seller: { bg:'#fef3c7', text:'#92400e', label:'Best Seller' },
        fashion:     { bg:'#fce7f3', text:'#9d174d', label:'Fashion' },
        karrem:      { bg:'#d1fae5', text:'#065f46', label:'Karrem' },
        plotesues:   { bg:'#e0e7ff', text:'#3730a3', label:'Plotësues' },
    };

    window.showWeekDetail = async function(weekId) {
        currentSidebarWeekId = weekId;
        const sidebar = document.getElementById('collectionSidebar');
        sidebar.style.display = 'flex';
        document.getElementById('csSidebarBody').innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;font-size:12px;">Loading...</div>';

        try {
            const res = await fetch(`${DETAIL_API}/${weekId}`, { headers:{'Accept':'application/json'} });
            const data = await res.json();
            renderSidebarContent(data);
        } catch(e) {
            document.getElementById('csSidebarBody').innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;font-size:12px;">Failed to load</div>';
        }
    };

    window.closeCollectionSidebar = function() {
        document.getElementById('collectionSidebar').style.display = 'none';
        currentSidebarWeekId = null;
    };

    window.changeSidebarStatus = async function(newStatus) {
        if (!currentSidebarWeekId) return;
        try {
            await fetch(`${WEEKS_API}/${currentSidebarWeekId}/status`, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
                body:JSON.stringify({status:newStatus}),
            });
            showWeekDetail(currentSidebarWeekId);
            if (typeof calendarInstance !== 'undefined' && calendarInstance) calendarInstance.refetchEvents();
        } catch(e) { console.error(e); }
    };

    function renderSidebarContent(data) {
        document.getElementById('csSidebarTitle').textContent = data.name || 'Collection';
        const body = document.getElementById('csSidebarBody');
        const groups = data.item_groups || [];
        const stats = data.stats || {};
        const st = data.status || 'planned';

        let html = '';

        // Cover image
        if (data.cover_image_url) {
            html += `<div style="width:100%;"><img src="${data.cover_image_url}" style="width:100%;max-height:180px;object-fit:cover;display:block;"></div>`;
        }

        // Dates + status
        html += `<div style="padding:14px 16px; border-bottom:1px solid #f1f5f9;">
            <div style="font-size:12px; color:#64748b; margin-bottom:8px;">
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

        // Stats row
        html += `<div style="display:flex; gap:0; padding:12px 16px; border-bottom:1px solid #f1f5f9;">
            <div class="cs-stat" style="flex:1;"><div class="cs-stat-value">${stats.total_groups || 0}</div><div class="cs-stat-label">Groups</div></div>
            <div class="cs-stat" style="flex:1;"><div class="cs-stat-value">${stats.total_stock || 0}</div><div class="cs-stat-label">Stock</div></div>
            <div class="cs-stat" style="flex:1;"><div class="cs-stat-value">${stats.min_price ? '€' + Number(stats.min_price).toFixed(0) : '—'}</div><div class="cs-stat-label">Min Price</div></div>
            <div class="cs-stat" style="flex:1;"><div class="cs-stat-value">${stats.max_price ? '€' + Number(stats.max_price).toFixed(0) : '—'}</div><div class="cs-stat-label">Max Price</div></div>
        </div>`;

        // Notes
        if (data.notes) {
            html += `<div style="padding:10px 16px; border-bottom:1px solid #f1f5f9; font-size:12px; color:#64748b; line-height:1.5;">${data.notes}</div>`;
        }

        // Item groups
        html += `<div style="padding:12px 16px;">
            <div style="font-size:12px; font-weight:600; color:#1e293b; margin-bottom:10px;">Item Groups (${groups.length})</div>`;

        if (!groups.length) {
            html += '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;">No groups assigned</div>';
        } else {
            groups.forEach(g => {
                const cls = g.classification || 'plotesues';
                const cs = classStyles[cls] || classStyles.plotesues;

                html += `<div class="cs-group-card">
                    <div style="display:flex; gap:10px;">
                        ${g.image_url ? `<img src="${g.image_url}" style="width:52px;height:52px;border-radius:6px;object-fit:cover;flex-shrink:0;background:#f1f5f9;">` : `<div style="width:52px;height:52px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><iconify-icon icon="heroicons-outline:photo" width="18" style="color:#d1d5db;"></iconify-icon></div>`}
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:12px; font-weight:600; color:#1e293b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${g.name}</div>
                            <div style="font-size:10px; color:#94a3b8; margin-top:1px;">${g.code || ''}${g.vendor_name ? ' · ' + g.vendor_name : ''}</div>
                            <div style="display:flex; align-items:center; gap:6px; margin-top:4px;">
                                <span class="cs-class-badge" style="background:${cs.bg};color:${cs.text};">${cs.label}</span>
                                ${g.pricelist_price ? `<span style="font-size:10px;color:#64748b;">€${Number(g.pricelist_price).toFixed(0)}</span>` : g.avg_price ? `<span style="font-size:10px;color:#64748b;">€${Number(g.avg_price).toFixed(0)}</span>` : ''}
                                ${g.total_stock ? `<span style="font-size:10px;color:#94a3b8;">${g.total_stock} stk</span>` : ''}
                            </div>
                        </div>
                    </div>
                    ${g.look_title ? `<div style="margin-top:8px; padding-top:8px; border-top:1px solid #f8fafc;">
                        <div style="font-size:10px; font-weight:600; color:#6366f1; margin-bottom:2px;">${g.look_title}</div>
                        ${g.look_description ? `<div style="font-size:10px; color:#64748b; line-height:1.4;">${g.look_description}</div>` : ''}
                    </div>` : ''}
                </div>`;
            });
        }

        html += '</div>';
        body.innerHTML = html;
    }

    // ESC to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('collectionSidebar').style.display === 'flex') {
            closeCollectionSidebar();
        }
    });
})();
</script>
