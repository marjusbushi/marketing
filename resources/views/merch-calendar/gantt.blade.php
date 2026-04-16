@extends('_layouts.app', [
    'title'     => 'Merch Calendar — Gantt',
    'pageTitle' => 'Merch Calendar',
])

@section('styles')
<style>
    .gantt-wrap { overflow-x:auto; }
    .gantt-header-cell { padding:6px 0; text-align:center; font-size:10px; font-weight:600; color:#94a3b8; border-right:1px solid #f1f5f9; min-width:40px; }
    .gantt-header-cell.today { background:#eef2ff; color:#6366f1; }
    .gantt-row { display:flex; align-items:center; border-bottom:1px solid #f8fafc; }
    .gantt-label { width:160px; flex-shrink:0; padding:8px 12px; font-size:12px; font-weight:500; color:#1e293b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border-right:1px solid #f1f5f9; }
    .gantt-cells { display:flex; flex:1; position:relative; }
    .gantt-cell { min-width:40px; height:32px; border-right:1px solid #f8fafc; position:relative; }
    .gantt-cell.today { background:#eef2ff; }
    .gantt-bar { position:absolute; top:4px; height:24px; border-radius:4px; display:flex; align-items:center; padding:0 8px; font-size:10px; font-weight:500; overflow:hidden; white-space:nowrap; z-index:1; }
    .gantt-bar-planned { background:#e2e8f0; color:#475569; }
    .gantt-bar-active { background:#bbf7d0; color:#166534; }
    .gantt-bar-completed { background:#bfdbfe; color:#1e40af; }
    .gantt-month-header { text-align:center; font-size:11px; font-weight:600; color:#475569; padding:4px 0; border-bottom:1px solid #e5e7eb; }
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
                <a href="{{ route('marketing.merch-calendar.timeline') }}" style="padding:5px 12px; font-size:11px; font-weight:500; border-radius:6px; text-decoration:none; color:#64748b;">Timeline</a>
                <a href="{{ route('marketing.merch-calendar.gantt') }}" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:6px; text-decoration:none; background:#6366f1; color:#fff;">Gantt</a>
            </div>
        </div>
        <div style="display:flex; gap:6px;">
            <button onclick="shiftRange(-30)" style="height:28px; padding:0 10px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer;">← Prev</button>
            <button onclick="shiftRange(0)" style="height:28px; padding:0 10px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer;">Today</button>
            <button onclick="shiftRange(30)" style="height:28px; padding:0 10px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#64748b; cursor:pointer;">Next →</button>
        </div>
    </div>

    {{-- Gantt chart --}}
    <div class="gantt-wrap" style="padding:0 0 16px;">
        <div id="ganttChart">
            <div style="text-align:center; padding:40px; color:#94a3b8; font-size:13px;">Loading...</div>
        </div>
    </div>
</div>

@include('merch-calendar._partials.collection-sidebar')
<script>
    const WEEKS_API = @json(route('marketing.merch-calendar.api.weeks'));
    let ganttOffset = 0; // days offset from today

    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function daysBetween(a, b) { return Math.round((new Date(b) - new Date(a)) / 86400000); }
    function addDays(dateStr, n) { const d = new Date(dateStr); d.setDate(d.getDate() + n); return d.toISOString().slice(0,10); }
    function toDateStr(d) { return d.toISOString().slice(0,10); }

    function shiftRange(days) {
        if (days === 0) ganttOffset = 0;
        else ganttOffset += days;
        loadGantt();
    }

    async function loadGantt() {
        const today = new Date();
        today.setDate(today.getDate() + ganttOffset);
        // Show 90 days: 15 days before + 75 days after
        const rangeStart = new Date(today); rangeStart.setDate(rangeStart.getDate() - 15);
        const rangeEnd = new Date(today); rangeEnd.setDate(rangeEnd.getDate() + 75);

        const startStr = toDateStr(rangeStart);
        const endStr = toDateStr(rangeEnd);
        const todayStr = toDateStr(new Date());
        const totalDays = daysBetween(startStr, endStr);

        try {
            const res = await fetch(`${WEEKS_API}?start=${startStr}&end=${endStr}`, { headers:{'Accept':'application/json'} });
            const events = await res.json();

            // Extract weeks
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
                    };
                }
            });
            const weeks = Object.values(weekMap).sort((a,b) => a.week_start.localeCompare(b.week_start));

            renderGantt(weeks, startStr, totalDays, todayStr);
        } catch(e) {
            document.getElementById('ganttChart').innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;">Failed to load</div>';
        }
    }

    function renderGantt(weeks, startStr, totalDays, todayStr) {
        const chart = document.getElementById('ganttChart');

        if (!weeks.length) {
            chart.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;font-size:13px;">No collections in this range</div>';
            return;
        }

        // Build day columns info
        const days = [];
        for (let i = 0; i < totalDays; i++) {
            const d = new Date(startStr);
            d.setDate(d.getDate() + i);
            days.push({
                date: toDateStr(d),
                day: d.getDate(),
                month: d.getMonth(),
                year: d.getFullYear(),
                isToday: toDateStr(d) === todayStr,
                isMonday: d.getDay() === 1,
            });
        }

        // Month headers
        let monthHeaders = '';
        let currentMonth = -1;
        let spanCount = 0;
        let monthSpans = [];
        days.forEach((d, i) => {
            if (d.month !== currentMonth) {
                if (currentMonth !== -1) monthSpans.push({ label: months[currentMonth] + ' ' + days[i-1].year, span: spanCount });
                currentMonth = d.month;
                spanCount = 1;
            } else { spanCount++; }
        });
        monthSpans.push({ label: months[currentMonth] + ' ' + days[days.length-1].year, span: spanCount });

        monthHeaders = '<div style="display:flex;"><div style="width:160px;flex-shrink:0;border-right:1px solid #f1f5f9;"></div><div style="display:flex;flex:1;">' +
            monthSpans.map(m => `<div class="gantt-month-header" style="min-width:${m.span * 40}px;width:${m.span * 40}px;">${m.label}</div>`).join('') + '</div></div>';

        // Day headers (show only Mondays)
        const dayHeaders = '<div style="display:flex;"><div style="width:160px;flex-shrink:0;border-right:1px solid #f1f5f9;padding:6px 12px;font-size:10px;font-weight:600;color:#94a3b8;">Collection</div><div style="display:flex;flex:1;">' +
            days.map(d => `<div class="gantt-header-cell${d.isToday ? ' today' : ''}">${d.isMonday || d.day === 1 ? d.day : ''}</div>`).join('') + '</div></div>';

        // Rows
        const rows = weeks.map(w => {
            const barStart = Math.max(0, daysBetween(startStr, w.week_start));
            const barEnd = Math.min(totalDays, daysBetween(startStr, addDays(w.week_end, 1)));
            const barWidth = Math.max(1, barEnd - barStart);

            const cells = days.map(d => `<div class="gantt-cell${d.isToday ? ' today' : ''}"></div>`).join('');

            const barClass = 'gantt-bar gantt-bar-' + (w.status || 'planned');
            const bar = `<div class="${barClass}" style="left:${barStart * 40}px;width:${barWidth * 40 - 2}px;">${w.name.replace(/ \(\d+\)$/, '')}</div>`;

            return `<div class="gantt-row">
                <div class="gantt-label" title="${w.name}">${w.name.replace(/ \(\d+\)$/, '')}</div>
                <div class="gantt-cells">${cells}${bar}</div>
            </div>`;
        }).join('');

        chart.innerHTML = monthHeaders + dayHeaders + rows;
    }

    document.addEventListener('DOMContentLoaded', loadGantt);
</script>
@endsection
