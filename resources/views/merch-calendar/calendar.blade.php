@extends('_layouts.app', [
    'title'     => 'Merch Calendar',
    'pageTitle' => 'Merch Calendar',
])

@section('styles')
<style>
    .fc { font-family: Inter, system-ui, sans-serif; font-size: 13px; }
    .fc .fc-toolbar-title { font-size: 17px; font-weight: 600; color: #1e293b; }
    .fc .fc-button { background: #fff; border: 1px solid #e2e8f0; color: #475569; font-size: 12px; font-weight: 500; padding: 5px 12px; border-radius: 6px; box-shadow: none; text-transform: none; }
    .fc .fc-button:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active { background: #6366f1; border-color: #6366f1; color: #fff; }
    .fc .fc-today-button { background: #6366f1; border-color: #6366f1; color: #fff; font-weight: 600; }
    .fc .fc-today-button:hover { background: #4f46e5; }
    .fc .fc-today-button:disabled { background: #e2e8f0; border-color: #e2e8f0; color: #94a3b8; }
    .fc .fc-col-header-cell { background: #fafbfc; border-color: #f1f5f9; padding: 8px 0; }
    .fc .fc-col-header-cell-cushion { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; }
    .fc .fc-daygrid-day { border-color: #f1f5f9; }
    .fc .fc-daygrid-day-number { font-size: 12px; font-weight: 500; color: #64748b; padding: 6px 8px; text-decoration: none; }
    .fc .fc-day-today { background: #eef2ff !important; }
    .fc .fc-day-today .fc-daygrid-day-number { color: #6366f1; font-weight: 700; }
    .fc .fc-daygrid-day-top { justify-content: flex-start; }
    .fc-event { cursor: pointer; border-radius: 4px !important; border: none !important; }
    .fc-bg-event { opacity: 0.2 !important; }
    .fc .fc-daygrid-event { border: none; font-size: 11px; margin: 1px 2px; }
    .fc .fc-daygrid-event .fc-event-main { color: inherit !important; }
    .fc-daygrid-event-dot { display: none; }

    /* Status popover */
    .mc-popover { position: absolute; background: #fff; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); padding: 12px; z-index: 10000; min-width: 180px; }
    .mc-popover-title { font-size: 12px; font-weight: 600; color: #1e293b; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9; }
    .mc-popover-btn { display: flex; align-items: center; gap: 8px; width: 100%; padding: 6px 8px; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; background: transparent; color: #475569; transition: background 0.1s; text-align: left; }
    .mc-popover-btn:hover { background: #f1f5f9; }
    .mc-popover-btn.active { background: #eef2ff; color: #6366f1; font-weight: 600; }
    .mc-popover-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .mc-popover-link { display: block; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; text-align: center; font-size: 11px; color: #6366f1; text-decoration: none; font-weight: 500; }
    .mc-popover-link:hover { text-decoration: underline; }

    /* Modal */
    .mc-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 9998; }
    .mc-modal { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); background: #fff; border-radius: 12px; padding: 24px; width: 480px; max-width: 95vw; z-index: 9999; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
    .mc-modal label { display: block; font-size: 12px; font-weight: 500; color: #64748b; margin-bottom: 4px; }
    .mc-modal input, .mc-modal textarea, .mc-modal select { width: 100%; padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box; font-family: inherit; }
    .mc-modal input:focus, .mc-modal textarea:focus, .mc-modal select:focus { border-color: #6366f1; }
</style>
@endsection

@section('content')
<div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
    {{-- Header with view tabs --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:1px solid #f1f5f9;">
        <div style="display:flex; align-items:center; gap:12px;">
            <iconify-icon icon="heroicons-outline:calendar-days" width="20" style="color:#6366f1;"></iconify-icon>
            <span style="font-size:15px; font-weight:600; color:#1e293b;">Merch Calendar</span>
            <div style="display:flex; gap:0; margin-left:12px;">
                <a href="{{ route('marketing.merch-calendar.calendar') }}" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:6px; text-decoration:none; background:#6366f1; color:#fff;">Calendar</a>
                <a href="{{ route('marketing.merch-calendar.timeline') }}" style="padding:5px 12px; font-size:11px; font-weight:500; border-radius:6px; text-decoration:none; color:#64748b;">Timeline</a>
                <a href="{{ route('marketing.merch-calendar.gantt') }}" style="padding:5px 12px; font-size:11px; font-weight:500; border-radius:6px; text-decoration:none; color:#64748b;">Gantt</a>
            </div>
        </div>
        <button onclick="openWeekModal()" style="display:inline-flex; align-items:center; gap:4px; height:30px; padding:0 14px; font-size:11px; font-weight:600; border-radius:6px; border:none; background:#6366f1; color:#fff; cursor:pointer;">
            <iconify-icon icon="heroicons-outline:plus" width="14"></iconify-icon> New Collection
        </button>
    </div>

    {{-- Calendar --}}
    <div style="padding:16px 20px;">
        <div id="merch-calendar"></div>
    </div>
</div>

{{-- Create/Edit Modal --}}
<div class="mc-modal-backdrop" id="weekModalBackdrop" onclick="closeWeekModal()"></div>
<div class="mc-modal" id="weekModal">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <span id="weekModalTitle" style="font-size:15px; font-weight:600; color:#1e293b;">New Collection</span>
        <button onclick="closeWeekModal()" style="width:28px; height:28px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
            <iconify-icon icon="heroicons-outline:x-mark" width="16" style="color:#94a3b8;"></iconify-icon>
        </button>
    </div>

    <div style="display:flex; flex-direction:column; gap:14px;">
        <div>
            <label>Name *</label>
            <input type="text" id="weekName" placeholder="e.g. Koleksion Verë — Java 15">
        </div>
        <div style="display:flex; gap:10px;">
            <div style="flex:1;">
                <label>Start Date</label>
                <input type="date" id="weekStart">
            </div>
            <div style="flex:1;">
                <label>End Date</label>
                <input type="date" id="weekEnd">
            </div>
        </div>
        <div>
            <label>Status</label>
            <select id="weekStatus">
                <option value="planned">Planifikuar</option>
                <option value="active">Aktiv</option>
                <option value="completed">Kompletuar</option>
            </select>
        </div>
        <div>
            <label>Notes</label>
            <textarea id="weekNotes" rows="2" placeholder="Optional notes..."></textarea>
        </div>
    </div>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:20px;">
        <div>
            <button id="weekDeleteBtn" onclick="deleteWeek()" style="display:none; height:32px; padding:0 14px; font-size:12px; font-weight:500; border-radius:8px; border:none; background:#fee2e2; color:#dc2626; cursor:pointer;">Delete</button>
        </div>
        <div style="display:flex; gap:8px;">
            <button onclick="closeWeekModal()" style="height:32px; padding:0 14px; font-size:12px; font-weight:500; border-radius:8px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer;">Cancel</button>
            <button onclick="saveWeek()" style="height:32px; padding:0 14px; font-size:12px; font-weight:600; border-radius:8px; border:none; background:#6366f1; color:#fff; cursor:pointer;">Save</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
    const WEEKS_API = @json(route('marketing.merch-calendar.api.weeks'));
    const COLLECTION_URL_BASE = @json(url('/marketing/merch-calendar/collection'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let calendarInstance = null;
    let currentWeekId = null;

    const statusPalette = {
        planned:   { bg: '#f1f5f9', text: '#475569', border: '#94a3b8', dot: '#94a3b8', label: 'Planifikuar' },
        active:    { bg: '#dcfce7', text: '#166534', border: '#22c55e', dot: '#22c55e', label: 'Aktiv' },
        completed: { bg: '#dbeafe', text: '#1e40af', border: '#3b82f6', dot: '#3b82f6', label: 'Kompletuar' },
    };

    document.addEventListener('DOMContentLoaded', function() {
        const calEl = document.getElementById('merch-calendar');

        calendarInstance = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            firstDay: 1,
            dayMaxEventRows: 3,
            eventOrder: 'eventOrder',

            eventSources: [{
                url: WEEKS_API,
            }],

            eventDidMount: function(info) {
                const props = info.event.extendedProps;
                if (props?.type === 'collection') {
                    const st = statusPalette[props.status] || statusPalette.planned;
                    info.el.style.backgroundColor = st.bg;
                    info.el.style.borderLeft = '3px solid ' + st.border;
                    info.el.style.color = st.text;
                    info.el.style.setProperty('color', st.text, 'important');
                }
            },

            eventContent: function(arg) {
                const props = arg.event.extendedProps;
                if (props?.type === 'collection') {
                    const st = statusPalette[props.status] || statusPalette.planned;
                    const el = document.createElement('div');
                    el.style.cssText = 'padding:3px 8px;display:flex;align-items:center;gap:5px;overflow:hidden;font-size:11px;font-weight:500;width:100%;cursor:pointer;';
                    el.innerHTML = `<span style="width:6px;height:6px;border-radius:50%;flex-shrink:0;background:${st.dot};"></span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${arg.event.title}</span>`;
                    return { domNodes: [el] };
                }
            },

            dateClick: function(info) {
                openWeekModal(null, info.dateStr);
            },

            eventClick: function(info) {
                const props = info.event.extendedProps;
                if (props?.type === 'collection') {
                    info.jsEvent.preventDefault();
                    info.jsEvent.stopPropagation();
                    showStatusPopover(info.jsEvent, props.distribution_week_id, props.status);
                }
            },
        });

        calendarInstance.render();

        // Close popover on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.mc-popover') && !e.target.closest('.fc-event')) {
                closePopover();
            }
        });
    });

    // ─── Status Popover ──────────────────────

    function closePopover() {
        const old = document.getElementById('mc-status-popover');
        if (old) old.remove();
    }

    function showStatusPopover(jsEvent, weekId, currentStatus) {
        closePopover();

        const pop = document.createElement('div');
        pop.id = 'mc-status-popover';
        pop.className = 'mc-popover';

        let html = '<div class="mc-popover-title">Status</div>';
        ['planned', 'active', 'completed'].forEach(s => {
            const st = statusPalette[s];
            const active = s === currentStatus ? ' active' : '';
            html += `<button class="mc-popover-btn${active}" onclick="changeStatus(${weekId},'${s}')"><span class="mc-popover-dot" style="background:${st.dot}"></span>${st.label}</button>`;
        });
        html += `<button class="mc-popover-btn" onclick="closePopover();openWeekModal(${weekId})" style="margin-top:4px;"><iconify-icon icon="heroicons-outline:pencil" width="12" style="color:#94a3b8;"></iconify-icon> Edit</button>`;
        html += `<a class="mc-popover-link" href="${COLLECTION_URL_BASE}/${Number(weekId)}">View details →</a>`;

        pop.innerHTML = html;
        document.body.appendChild(pop);

        pop.style.left = jsEvent.pageX + 'px';
        pop.style.top = jsEvent.pageY + 'px';

        requestAnimationFrame(() => {
            const rect = pop.getBoundingClientRect();
            if (rect.right > window.innerWidth) pop.style.left = (jsEvent.pageX - rect.width) + 'px';
            if (rect.bottom > window.innerHeight) pop.style.top = (jsEvent.pageY - rect.height) + 'px';
        });
    }

    async function changeStatus(weekId, newStatus) {
        closePopover();
        try {
            await fetch(WEEKS_API + '/' + weekId + '/status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ status: newStatus }),
            });
            calendarInstance.refetchEvents();
        } catch (e) { alert('Error: ' + e.message); }
    }

    // Collection detail now lives at /marketing/merch-calendar/collection/{id}

    // ─── Create/Edit Modal ───────────────────

    function openWeekModal(weekId = null, dateStr = null) {
        currentWeekId = weekId;
        document.getElementById('weekModalTitle').textContent = weekId ? 'Edit Collection' : 'New Collection';
        document.getElementById('weekDeleteBtn').style.display = weekId ? 'inline-flex' : 'none';
        document.getElementById('weekName').value = '';
        document.getElementById('weekNotes').value = '';
        document.getElementById('weekStatus').value = 'planned';

        if (dateStr) {
            document.getElementById('weekStart').value = dateStr;
            // Default end = start + 6 days
            const end = new Date(dateStr);
            end.setDate(end.getDate() + 6);
            document.getElementById('weekEnd').value = end.toISOString().slice(0, 10);
        } else {
            document.getElementById('weekStart').value = '';
            document.getElementById('weekEnd').value = '';
        }

        if (weekId) {
            // Load existing data
            fetch(WEEKS_API + '/' + weekId, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('weekName').value = data.name || '';
                    document.getElementById('weekStart').value = data.week_start || '';
                    document.getElementById('weekEnd').value = data.week_end || '';
                    document.getElementById('weekStatus').value = data.status || 'planned';
                    document.getElementById('weekNotes').value = data.notes || '';
                })
                .catch(e => console.error(e));
        }

        document.getElementById('weekModalBackdrop').style.display = 'block';
        document.getElementById('weekModal').style.display = 'block';
        setTimeout(() => document.getElementById('weekName').focus(), 100);
    }

    function closeWeekModal() {
        document.getElementById('weekModalBackdrop').style.display = 'none';
        document.getElementById('weekModal').style.display = 'none';
        currentWeekId = null;
    }

    async function saveWeek() {
        const name = document.getElementById('weekName').value.trim();
        if (!name) { document.getElementById('weekName').focus(); return; }

        const data = {
            name,
            week_start: document.getElementById('weekStart').value,
            week_end: document.getElementById('weekEnd').value,
            status: document.getElementById('weekStatus').value,
            notes: document.getElementById('weekNotes').value.trim() || null,
        };

        try {
            const url = currentWeekId ? WEEKS_API + '/' + currentWeekId : WEEKS_API;
            const method = currentWeekId ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify(data),
            });
            if (!res.ok) {
                const err = await res.json();
                alert('Error: ' + (err.message || JSON.stringify(err.errors || err)));
                return;
            }
            closeWeekModal();
            calendarInstance.refetchEvents();
        } catch (e) { alert('Failed: ' + e.message); }
    }

    async function deleteWeek() {
        if (!currentWeekId || !confirm('Delete this collection?')) return;
        try {
            await fetch(WEEKS_API + '/' + currentWeekId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            closeWeekModal();
            calendarInstance.refetchEvents();
        } catch (e) { alert('Delete failed: ' + e.message); }
    }
</script>
@endsection
