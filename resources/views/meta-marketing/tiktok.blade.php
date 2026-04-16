@extends('_layouts.app', [
    'title' => 'Dashboard — TikTok',
    'pageTitle' => 'Dashboard'
])

@section('header-actions')
    <nav class="flex items-center gap-1.5">
        <a href="{{ route('marketing.analytics.ads') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="heroicons-outline:megaphone" width="14"></iconify-icon> Ads
        </a>
        <a href="{{ route('marketing.analytics.instagram') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="skill-icons:instagram" width="14"></iconify-icon> Instagram
        </a>
        @if(config('meta.features.facebook_module'))
        <a href="{{ route('marketing.analytics.facebook') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="logos:facebook" width="14"></iconify-icon> Facebook
        </a>
        @endif
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white">
            <iconify-icon icon="logos:tiktok-icon" width="14"></iconify-icon> TikTok
        </span>
        <a href="{{ route('marketing.analytics.index') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="heroicons-outline:chart-bar-square" width="14"></iconify-icon> Total
        </a>
        @if(config('content-planner.enabled'))
            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>
            <a href="{{ route('marketing.planner.calendar') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-indigo-300 text-indigo-500 hover:bg-indigo-50 transition-colors">
                <iconify-icon icon="heroicons-outline:calendar-days" width="14"></iconify-icon> Planner
            </a>
        @endif
    </nav>
@endsection

@section('content')

{{-- Sync Toast --}}
<div id="syncToast" class="hidden fixed top-4 right-4 z-[9999] min-w-[340px] max-w-[420px] rounded-xl p-4 shadow-xl transition-opacity duration-300">
    <div class="flex items-start gap-3">
        <div id="syncToastIcon" class="shrink-0 mt-0.5"></div>
        <div class="flex-1 min-w-0">
            <div id="syncToastTitle" class="text-sm font-semibold"></div>
            <div id="syncToastMsg" class="text-xs leading-relaxed mt-0.5"></div>
        </div>
        <button onclick="hideSyncToast()" class="shrink-0 text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
    </div>
</div>

<div id="tiktok-report" class="space-y-6">

    {{-- Page header --}}
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <iconify-icon icon="logos:tiktok-icon" width="22"></iconify-icon>
            TikTok Report
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            TikTok Ads Performance &mdash; Marketing API v1.3
        </p>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Periudha</label>
                <select id="datePreset" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none min-w-[170px]">
                    <option value="">Zgjidh periudhën...</option>
                    <option value="today">Sot</option>
                    <option value="yesterday">Dje</option>
                    <option value="this_week">Kjo Javë</option>
                    <option value="last_week">Java e Kaluar</option>
                    <option value="this_month" selected>Ky Muaj</option>
                    <option value="last_month">Muaji i Kaluar</option>
                    <option value="this_quarter">Ky Tremujor</option>
                    <option value="last_quarter">Tremujori i Kaluar</option>
                    <option value="this_year">Ky Vit</option>
                    <option value="last_year">Viti i Kaluar</option>
                    <option value="ytd">Nga Fillimi i Vitit</option>
                    <option value="custom">Custom...</option>
                </select>
            </div>
            <div id="customDateFromWrap" class="hidden flex flex-col gap-1">
                <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Nga</label>
                <input type="date" id="dateFrom" class="h-[30px] rounded-md border border-slate-200 px-2.5 text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
            </div>
            <div id="customDateToWrap" class="hidden flex flex-col gap-1">
                <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Deri</label>
                <input type="date" id="dateTo" class="h-[30px] rounded-md border border-slate-200 px-2.5 text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
            </div>
            <div class="flex items-end gap-2" id="filterActions">
                <button onclick="loadAll()" class="h-[30px] inline-flex items-center gap-1.5 px-3.5 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors">
                    <iconify-icon icon="heroicons-outline:arrow-path" width="15"></iconify-icon> Ngarko
                </button>
                <button onclick="loadAll(null, true)" class="h-[30px] inline-flex items-center gap-1 px-2.5 rounded-md border border-slate-200 text-slate-500 text-xs font-medium hover:bg-slate-50 transition-colors" title="Rifresko pa cache">
                    <iconify-icon icon="heroicons-outline:bolt" width="15"></iconify-icon> Rifresko
                </button>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    @php
    $kpis = [
        ['key' => 'spend',       'label' => 'Amount Spent',  'icon' => 'heroicons-outline:currency-euro'],
        ['key' => 'impressions', 'label' => 'Impressions',   'icon' => 'heroicons-outline:eye'],
        ['key' => 'reach',       'label' => 'Reach',         'icon' => 'heroicons-outline:users'],
        ['key' => 'clicks',      'label' => 'Clicks',        'icon' => 'heroicons-outline:cursor-arrow-rays'],
        ['key' => 'video_views', 'label' => 'Video Views',   'icon' => 'heroicons-outline:play'],
        ['key' => 'engagement',  'label' => 'Engagement',    'icon' => 'heroicons-outline:heart'],
        ['key' => 'purchases',   'label' => 'Purchases',     'icon' => 'heroicons-outline:shopping-bag'],
        ['key' => 'revenue',     'label' => 'Revenue',       'icon' => 'heroicons-outline:banknotes'],
        ['key' => 'roas',        'label' => 'ROAS',          'icon' => 'heroicons-outline:arrow-trending-up'],
        ['key' => 'cpc',         'label' => 'CPC',           'icon' => 'heroicons-outline:cursor-arrow-ripple'],
        ['key' => 'cpm',         'label' => 'CPM',           'icon' => 'heroicons-outline:presentation-chart-bar'],
        ['key' => 'conversions', 'label' => 'Conversions',   'icon' => 'heroicons-outline:check-badge'],
    ];
    @endphp

    <div id="kpiCards" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="{{ $kpi['icon'] }}" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">{{ $kpi['label'] }}</span>
            </div>
            <div id="kpi-{{ $kpi['key'] }}" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-{{ $kpi['key'] }}-change" class="text-[11px] mt-1 font-medium"></div>
        </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Spend + ROAS Dual Axis --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:currency-euro" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Daily Spend & ROAS</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="spendRoasChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Video Views & Engagement --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:play" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Daily Video Views & Engagement</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="viewsEngagementChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Breakdowns --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Age Breakdown --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:user-group" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Spend by Age</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[260px]">
                    <canvas id="ageChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Gender Breakdown --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:users" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Spend by Gender</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[260px]">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Platform Breakdown --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:device-phone-mobile" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Spend by Platform</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[260px]">
                    <canvas id="platformChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Campaign Table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:megaphone" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Campaigns Performance</h3>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="campaignsTable">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/60">
                        <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[22%] cursor-pointer" onclick="sortTable('name')">
                            Campaign <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[7%]">Status</th>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[8%]">Objective</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('spend')">
                            Spend <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Impressions</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Reach</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('clicks')">
                            Clicks <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Video Views</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('purchases')">
                            Purchases <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Revenue</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('roas')">
                            ROAS <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                    </tr>
                </thead>
                <tbody id="campaignTableBody">
                    <tr><td colspan="11" class="text-center py-8 text-slate-400">Duke ngarkuar...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Top Videos (if organic enabled) --}}
    @if(config('tiktok.features.tiktok_organic'))
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:film" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Top Videos</h3>
        </div>
        <div class="p-5">
            <div id="topVideosContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div class="text-center py-8 text-slate-400 col-span-full">Duke ngarkuar...</div>
            </div>
        </div>
    </div>
    @endif

</div>

<style>
    .campaign-row:hover { background: #FAFAFA; }
    .dark .campaign-row:hover { background: #1E293B; }
    .tiktok-video-card {
        border: 1px solid #E2E8F0;
        border-radius: 0.75rem;
        overflow: hidden;
        transition: box-shadow 0.2s;
    }
    .tiktok-video-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .dark .tiktok-video-card { border-color: #334155; }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    let spendRoasChart = null;
    let viewsEngagementChart = null;
    let ageChart = null;
    let genderChart = null;
    let platformChart = null;
    let loadGeneration = 0;
    let campaignsData = [];
    let sortField = 'spend';
    let sortAsc = false;

    const baseUrl = '{{ route("marketing.analytics.index") }}';
    const datePresetEl = document.getElementById('datePreset');
    const dateFromEl = document.getElementById('dateFrom');
    const dateToEl = document.getElementById('dateTo');
    const customFromWrap = document.getElementById('customDateFromWrap');
    const customToWrap = document.getElementById('customDateToWrap');

    function fmtNum(n) { return Number(n).toLocaleString('de-DE'); }
    function fmtEur(n) { return '€' + Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fmtPct(n) { return Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%'; }
    function fmtRoas(n) { return Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + 'x'; }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = date.getDate().toString().padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${day} ${months[date.getMonth()]}`;
    }

    function roasColor(roas) {
        if (roas >= 2) return '#2E7D32';
        if (roas >= 1) return '#E65100';
        return '#C62828';
    }

    function apiUrl(endpoint, params = {}) {
        const url = new URL(baseUrl + '/api/' + endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        return url.toString();
    }

    let activeAbortController = null;

    async function fetchApi(endpoint, params = {}, timeoutMs = 120000) {
        const timeoutController = new AbortController();
        const timer = setTimeout(() => timeoutController.abort(), timeoutMs);
        const signals = [timeoutController.signal];
        if (activeAbortController) signals.push(activeAbortController.signal);
        const signal = signals.length > 1 ? AbortSignal.any(signals) : signals[0];

        try {
            const res = await fetch(apiUrl(endpoint, params), { signal });
            clearTimeout(timer);

            const contentType = res.headers.get('Content-Type') || '';
            if (contentType.includes('text/html')) {
                window.location.reload();
                throw new Error('Sesioni ka skaduar. Duke ri-drejtuar...');
            }

            if (!res.ok) {
                const body = await res.text().catch(() => '');
                throw new Error(`HTTP ${res.status}: ${body.substring(0, 200) || res.statusText}`);
            }
            const data = await res.json();
            const cached = res.headers.get('X-Meta-Cache') === 'HIT';
            return { data, cached };
        } catch (e) {
            clearTimeout(timer);
            if (e.name === 'AbortError') throw new Error('Kërkesa u anulua ose skadoi.');
            throw e;
        }
    }

    function fmtD(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    function applyPreset(preset) {
        if (!preset || preset === '') return;
        if (preset === 'custom') {
            customFromWrap.classList.remove('hidden');
            customToWrap.classList.remove('hidden');
            return;
        }
        customFromWrap.classList.add('hidden');
        customToWrap.classList.add('hidden');

        const today = new Date();
        const reportDay = new Date(today);
        reportDay.setDate(reportDay.getDate() - 1);
        let from, to;
        switch(preset) {
            case 'today': from = to = fmtD(today); break;
            case 'yesterday': const y = new Date(today); y.setDate(y.getDate()-1); from = to = fmtD(y); break;
            case 'this_week': const mw = new Date(reportDay); mw.setDate(mw.getDate()-(mw.getDay()||7)+1); from = fmtD(mw); to = fmtD(reportDay); break;
            case 'last_week': const lw = new Date(today); lw.setDate(lw.getDate()-(lw.getDay()||7)-6); const lwe = new Date(lw); lwe.setDate(lwe.getDate()+6); from = fmtD(lw); to = fmtD(lwe); break;
            case 'this_month': from = fmtD(new Date(reportDay.getFullYear(), reportDay.getMonth(), 1)); to = fmtD(reportDay); break;
            case 'last_month': from = fmtD(new Date(today.getFullYear(), today.getMonth()-1, 1)); to = fmtD(new Date(today.getFullYear(), today.getMonth(), 0)); break;
            case 'this_quarter': const qs = Math.floor(reportDay.getMonth()/3)*3; from = fmtD(new Date(reportDay.getFullYear(), qs, 1)); to = fmtD(reportDay); break;
            case 'last_quarter': const lqs = Math.floor(today.getMonth()/3)*3-3; from = fmtD(new Date(today.getFullYear(), lqs, 1)); to = fmtD(new Date(today.getFullYear(), lqs+3, 0)); break;
            case 'this_year': from = fmtD(new Date(reportDay.getFullYear(), 0, 1)); to = fmtD(reportDay); break;
            case 'last_year': from = fmtD(new Date(today.getFullYear()-1, 0, 1)); to = fmtD(new Date(today.getFullYear()-1, 11, 31)); break;
            case 'ytd': from = fmtD(new Date(reportDay.getFullYear(), 0, 1)); to = fmtD(reportDay); break;
            default: return;
        }
        dateFromEl.value = from;
        dateToEl.value = to;
    }

    let presetDebounce;
    datePresetEl.addEventListener('change', () => {
        applyPreset(datePresetEl.value);
        clearTimeout(presetDebounce);
        if (datePresetEl.value !== 'custom' && datePresetEl.value !== '') {
            presetDebounce = setTimeout(() => loadAll(), 250);
        }
    });

    datePresetEl.value = 'this_month';
    applyPreset('this_month');

    async function loadAll(event, fresh = false) {
        const from = dateFromEl.value;
        const to = dateToEl.value;
        if (!from || !to) return;

        if (activeAbortController) activeAbortController.abort();
        activeAbortController = new AbortController();

        const thisGen = ++loadGeneration;
        const actionBtns = document.querySelectorAll('#filterActions button');
        actionBtns.forEach(b => {
            b.dataset.originalHtml = b.dataset.originalHtml || b.innerHTML;
            b.disabled = true;
            b.classList.add('opacity-60');
        });
        const primaryBtn = document.querySelector('#filterActions button:first-child');
        const refreshBtn = document.querySelector('#filterActions button:last-child');
        const activeBtn = fresh ? refreshBtn : primaryBtn;

        if (fresh) {
            if (activeBtn) activeBtn.innerHTML = '<iconify-icon icon="line-md:loading-twotone-loop" width="16"></iconify-icon> Duke sinkronizuar...';
            try {
                const { data: syncResp } = await fetchApi('sync', { from, to, force: 1, channel: 'tiktok' }, 30000);
                if (syncResp.already_running) {
                    showSyncToast('syncing', 'Rifresko tashmë po ekzekutohet...', 'Një sync tjetër ende po punon. Duke pritur përfundimin...');
                }
            } catch (e) {
                showSyncToast('failed', 'Rifresko nuk u nis dot', e.message || 'Gabim rrjeti');
            }
            try { await pollSyncStatus(refreshBtn); } catch (e) { }
        }

        if (thisGen !== loadGeneration) return;
        if (activeBtn) activeBtn.innerHTML = '<iconify-icon icon="line-md:loading-twotone-loop" width="16"></iconify-icon> Duke ngarkuar...';

        const extra = fresh ? { nocache: 1 } : {};

        const tasks = [
            loadKPIs(from, to, extra, thisGen),
            loadCharts(from, to, extra, thisGen),
            loadCampaigns(from, to, extra, thisGen),
            loadBreakdowns(from, to, extra, thisGen),
        ];
        @if(config('tiktok.features.tiktok_organic'))
        tasks.push(loadTopVideos(from, to, extra, thisGen));
        @endif

        const results = await Promise.allSettled(tasks);
        if (thisGen !== loadGeneration) return;
        results.forEach((r, i) => {
            if (r.status === 'rejected') console.error(`Load task ${i} failed:`, r.reason);
        });

        actionBtns.forEach(b => {
            b.innerHTML = b.dataset.originalHtml;
            b.disabled = false;
            b.classList.remove('opacity-60');
        });
    }

    /** Show/hide sync toast notification */
    function showSyncToast(type, title, msg) {
        const toast = document.getElementById('syncToast');
        const icon = document.getElementById('syncToastIcon');
        const titleEl = document.getElementById('syncToastTitle');
        const msgEl = document.getElementById('syncToastMsg');
        const styles = {
            syncing: { bg: 'bg-blue-50', border: 'border-blue-200', color: 'text-blue-700', icon: 'line-md:loading-twotone-loop' },
            done:    { bg: 'bg-emerald-50', border: 'border-emerald-200', color: 'text-emerald-700', icon: 'heroicons-outline:check-circle' },
            failed:  { bg: 'bg-red-50', border: 'border-red-200', color: 'text-red-700', icon: 'heroicons-outline:x-circle' },
        };
        const s = styles[type] || styles.syncing;
        toast.className = `fixed top-4 right-4 z-[9999] min-w-[340px] max-w-[420px] rounded-xl p-4 shadow-xl border ${s.bg} ${s.border}`;
        icon.innerHTML = `<iconify-icon icon="${s.icon}" width="22" class="${s.color}"></iconify-icon>`;
        titleEl.className = `text-sm font-semibold ${s.color}`;
        titleEl.textContent = title;
        msgEl.className = 'text-xs text-slate-600 mt-0.5';
        msgEl.textContent = msg;
    }
    function hideSyncToast() {
        document.getElementById('syncToast').classList.add('hidden');
    }

    async function pollSyncStatus(btn, maxWaitMs = 600000) {
        const start = Date.now();
        showSyncToast('syncing', 'Rifresko po ekzekutohet...', 'Koha e vlerësuar: ~2-5 minuta. Ju lutem prisni...');
        while (Date.now() - start < maxWaitMs) {
            await new Promise(r => setTimeout(r, 5000));
            try {
                const { data } = await fetchApi('sync-status', {}, 10000);
                const elapsed = Math.round((Date.now() - start) / 1000);
                const mm = Math.floor(elapsed / 60), ss = elapsed % 60;
                const timeStr = mm > 0 ? mm + 'm ' + ss + 's' : ss + 's';
                if (data.status === 'done') {
                    showSyncToast('done', 'Rifresko u kompletua!', 'Koha: ' + timeStr + '. Duke ngarkuar...');
                    setTimeout(hideSyncToast, 8000);
                    return;
                }
                if (data.status === 'failed') {
                    showSyncToast('failed', 'Rifresko dështoi', data.error || 'Gabim i panjohur');
                    setTimeout(hideSyncToast, 12000);
                    return;
                }
                showSyncToast('syncing', 'Rifresko po ekzekutohet...', 'Koha e vlerësuar: ~2-5 min • ' + timeStr + ' kanë kaluar');
            } catch (e) { /* keep polling */ }
        }
        showSyncToast('failed', 'Rifresko skadoi', 'Kaloi koha maksimale e pritjes (10 min).');
        setTimeout(hideSyncToast, 10000);
    }

    async function loadKPIs(from, to, extra = {}, gen = null) {
        const { data } = await fetchApi('tiktok-kpis', { from, to, ...extra });
        if (gen !== null && gen !== loadGeneration) return;

        const formatters = {
            spend: v => fmtEur(v),
            impressions: v => fmtNum(v),
            reach: v => fmtNum(v),
            clicks: v => fmtNum(v),
            ctr: v => fmtPct(v),
            video_views: v => fmtNum(v),
            purchases: v => fmtNum(v),
            revenue: v => fmtEur(v),
            roas: v => fmtRoas(v),
            cpc: v => fmtEur(v),
            cpm: v => fmtEur(v),
            engagement: v => fmtNum(v),
            conversions: v => fmtNum(v),
            cost_per_conversion: v => fmtEur(v),
            likes: v => fmtNum(v),
            comments: v => fmtNum(v),
            shares: v => fmtNum(v),
            follows: v => fmtNum(v),
            add_to_cart: v => fmtNum(v),
            initiate_checkout: v => fmtNum(v),
        };

        Object.entries(data).forEach(([key, info]) => {
            const valEl = document.getElementById(`kpi-${key}`);
            const changeEl = document.getElementById(`kpi-${key}-change`);
            if (!valEl) return;

            valEl.textContent = formatters[key] ? formatters[key](info.value) : info.value;

            if (changeEl) {
                if (info.change === null || info.change === undefined) {
                    changeEl.textContent = 'Metrikë e re — pa informacion';
                    changeEl.className = 'text-[11px] mt-1 font-medium text-slate-400';
                } else if (info.change === 'new') {
                    changeEl.textContent = 'E re — 0 vitin e kaluar';
                    changeEl.className = 'text-[11px] mt-1 font-medium text-blue-600';
                } else {
                    const sign = info.change > 0 ? '+' : '';
                    changeEl.textContent = `${sign}${info.change}% vs 1 vit më parë`;
                    const color = info.change > 0 ? 'text-emerald-600' : info.change < 0 ? 'text-red-600' : 'text-slate-400';
                    changeEl.className = `text-[11px] mt-1 font-medium ${color}`;
                }
            }
        });
    }

    async function loadCharts(from, to, extra = {}, gen = null) {
        const { data } = await fetchApi('tiktok-daily', { from, to, ...extra });
        if (gen !== null && gen !== loadGeneration) return;

        const labels = data.map(d => formatDate(d.date));

        // Spend & ROAS
        if (spendRoasChart) spendRoasChart.destroy();
        spendRoasChart = new Chart(document.getElementById('spendRoasChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Spend (€)',
                        data: data.map(d => d.spend),
                        borderColor: '#1E88E5',
                        backgroundColor: 'rgba(30, 136, 229, 0.08)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y',
                        borderWidth: 2,
                        pointRadius: 2,
                    },
                    {
                        label: 'ROAS',
                        data: data.map(d => d.spend > 0 ? ((d.purchase_value || 0) / d.spend).toFixed(2) : 0),
                        borderColor: '#2E7D32',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        yAxisID: 'y1',
                        borderWidth: 2,
                        borderDash: [5, 3],
                        pointRadius: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 16, font: { size: 11 } } } },
                scales: {
                    y: { position: 'left', title: { display: true, text: 'Spend (€)', font: { size: 11 } }, ticks: { font: { size: 10 } } },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'ROAS', font: { size: 11 } }, ticks: { font: { size: 10 } } },
                    x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
                },
            },
        });

        // Video Views & Engagement
        if (viewsEngagementChart) viewsEngagementChart.destroy();
        viewsEngagementChart = new Chart(document.getElementById('viewsEngagementChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Video Views',
                        data: data.map(d => d.video_views || 0),
                        borderColor: '#7C3AED',
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y',
                        borderWidth: 2,
                        pointRadius: 2,
                    },
                    {
                        label: 'Engagement',
                        data: data.map(d => (d.likes || 0) + (d.comments || 0) + (d.shares || 0)),
                        borderColor: '#E91E63',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        yAxisID: 'y1',
                        borderWidth: 2,
                        pointRadius: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 16, font: { size: 11 } } } },
                scales: {
                    y: { position: 'left', title: { display: true, text: 'Video Views', font: { size: 11 } }, ticks: { font: { size: 10 } } },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Engagement', font: { size: 11 } }, ticks: { font: { size: 10 } } },
                    x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
                },
            },
        });
    }

    async function loadBreakdowns(from, to, extra = {}, gen = null) {
        try {
            const { data } = await fetchApi('tiktok-breakdowns', { from, to, ...extra });
            if (gen !== null && gen !== loadGeneration) return;

            renderBreakdownChart('ageChart', data.age || {}, 'Age');
            renderBreakdownChart('genderChart', data.gender || {}, 'Gender');
            renderBreakdownChart('platformChart', data.platform || {}, 'Platform');
        } catch (e) {
            console.warn('Breakdowns failed:', e);
        }
    }

    function renderBreakdownChart(canvasId, data, label) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        const labels = Object.keys(data);
        const spendValues = labels.map(k => data[k]?.spend || 0);
        const colors = ['#1E88E5', '#7C3AED', '#E91E63', '#FF9800', '#4CAF50', '#00BCD4', '#795548', '#607D8B'];

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: spendValues,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, padding: 8, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const val = ctx.raw;
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return `${ctx.label}: €${val.toLocaleString('de-DE', { minimumFractionDigits: 2 })} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    async function loadCampaigns(from, to, extra = {}, gen = null) {
        try {
            const { data } = await fetchApi('tiktok-campaigns', { from, to, ...extra });
            if (gen !== null && gen !== loadGeneration) return;
            campaignsData = data;
            renderCampaigns();
        } catch (e) {
            document.getElementById('campaignTableBody').innerHTML = '<tr><td colspan="11" class="text-center py-8 text-red-600">Gabim: ' + e.message + '</td></tr>';
        }
    }

    function sortTable(field) {
        if (sortField === field) { sortAsc = !sortAsc; } else { sortField = field; sortAsc = false; }
        renderCampaigns();
    }

    function renderCampaigns() {
        const sorted = [...campaignsData].sort((a, b) => {
            let va = a[sortField] ?? 0, vb = b[sortField] ?? 0;
            if (typeof va === 'string') va = va.toLowerCase();
            if (typeof vb === 'string') vb = vb.toLowerCase();
            return sortAsc ? (va > vb ? 1 : -1) : (va < vb ? 1 : -1);
        });

        const tbody = document.getElementById('campaignTableBody');
        if (!sorted.length) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center py-8 text-slate-400">Nuk ka fushata për këtë periudhë.</td></tr>';
            return;
        }

        tbody.innerHTML = sorted.map(c => {
            const roas = c.roas || 0;
            const statusBadge = c.status === 'ENABLE' || c.status === 'ACTIVE'
                ? '<span class="inline-block px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700">Active</span>'
                : '<span class="inline-block px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-500">' + (c.status || '\u2014') + '</span>';

            return `<tr class="campaign-row border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                <td class="text-left px-4 py-3 font-medium text-slate-800">${c.name || '\u2014'}</td>
                <td class="text-center px-3 py-3">${statusBadge}</td>
                <td class="text-center px-3 py-3 text-[11px] text-slate-500">${c.objective || '\u2014'}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtEur(c.spend || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtNum(c.impressions || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtNum(c.reach || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtNum(c.clicks || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtNum(c.video_views || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtNum(c.purchases || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums">${fmtEur(c.purchase_value || 0)}</td>
                <td class="text-right px-3 py-3 tabular-nums font-semibold" style="color:${roasColor(roas)}">${fmtRoas(roas)}</td>
            </tr>`;
        }).join('');
    }

    @if(config('tiktok.features.tiktok_organic'))
    async function loadTopVideos(from, to, extra = {}, gen = null) {
        try {
            const { data } = await fetchApi('tiktok-top-videos', { from, to, limit: 10, ...extra });
            if (gen !== null && gen !== loadGeneration) return;

            const container = document.getElementById('topVideosContainer');
            if (!data.length) {
                container.innerHTML = '<div class="text-center py-8 text-slate-400 col-span-full">Nuk ka video për këtë periudhë.</div>';
                return;
            }

            container.innerHTML = data.map(v => `
                <div class="tiktok-video-card">
                    ${v.cover_image_url ? `<img src="${v.cover_image_url}" alt="" class="w-full h-40 object-cover">` : '<div class="w-full h-40 bg-slate-100 flex items-center justify-center text-slate-300"><iconify-icon icon="heroicons-outline:film" width="48"></iconify-icon></div>'}
                    <div class="p-3">
                        <div class="text-[13px] font-semibold text-slate-800 mb-2 truncate">${v.title || 'Untitled'}</div>
                        <div class="grid grid-cols-2 gap-1 text-[11px] text-slate-500">
                            <span>👁 ${fmtNum(v.view_count || 0)}</span>
                            <span>❤ ${fmtNum(v.like_count || 0)}</span>
                            <span>💬 ${fmtNum(v.comment_count || 0)}</span>
                            <span>↗ ${fmtNum(v.share_count || 0)}</span>
                        </div>
                        ${v.share_url ? `<a href="${v.share_url}" target="_blank" rel="noopener" class="inline-block mt-2 text-[11px] text-primary-600 hover:text-primary-700">Shiko në TikTok →</a>` : ''}
                    </div>
                </div>
            `).join('');
        } catch (e) {
            console.warn('Top videos failed:', e);
        }
    }
    @endif

    // Auto-load on page load
    loadAll();
</script>
@endsection
