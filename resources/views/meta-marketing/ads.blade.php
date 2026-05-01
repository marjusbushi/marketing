@extends('_layouts.app', [
    'title' => 'Dashboard — Ads Report',
    'pageTitle' => 'Dashboard'
])

@section('header-actions')
    <nav class="flex items-center gap-1.5">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white">
            <iconify-icon icon="heroicons-outline:megaphone" width="14"></iconify-icon> Ads
        </span>
        <a href="{{ route('marketing.analytics.instagram') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="skill-icons:instagram" width="14"></iconify-icon> Instagram
        </a>
        @if(config('meta.features.facebook_module'))
        <a href="{{ route('marketing.analytics.facebook') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="logos:facebook" width="14"></iconify-icon> Facebook
        </a>
        @endif
        @if(config('tiktok.features.tiktok_module'))
        <a href="{{ route('marketing.analytics.tiktok') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="logos:tiktok-icon" width="14"></iconify-icon> TikTok
        </a>
        @endif
        <a href="{{ route('marketing.analytics.index') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="heroicons-outline:chart-bar-square" width="14"></iconify-icon> Total
        </a>
        @if(config('content-planner.enabled'))
            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>
            <a href="{{ route('marketing.planner.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-indigo-300 text-indigo-500 hover:bg-indigo-50 transition-colors">
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

<div id="ads-report" class="space-y-6">

    {{-- Page header --}}
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:megaphone" width="22" class="text-primary-500"></iconify-icon>
            Ads Report
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Facebook & Instagram Ads Performance &mdash; Graph API {{ $apiVersionV24 }}
            @if($lastSync)
                &mdash; <span id="last-sync-text" class="text-xs">Sync i fundit: {{ $lastSync->created_at->min(now())->diffForHumans() }}</span>
            @endif
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
            @if(config('meta.features.ads_platform_split'))
            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Platforma Ads</label>
                <select id="platformFilter" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none min-w-[155px]">
                    <option value="all" selected>Të gjitha</option>
                    <option value="facebook">Facebook Ads</option>
                    <option value="instagram">Instagram Ads</option>
                </select>
            </div>
            @endif
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
    <div id="kpiCards" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        @php
        $kpis = [
            ['key' => 'spend',       'label' => 'Amount Spent',  'icon' => 'heroicons-outline:currency-euro',    'color' => 'blue'],
            ['key' => 'impressions', 'label' => 'Impressions',   'icon' => 'heroicons-outline:eye',              'color' => 'purple'],
            ['key' => 'reach',       'label' => 'Reach',         'icon' => 'heroicons-outline:users',            'color' => 'teal'],
            ['key' => 'link_clicks', 'label' => 'Link Clicks',   'icon' => 'heroicons-outline:cursor-arrow-rays','color' => 'orange'],
            ['key' => 'ctr',         'label' => 'CTR (Link)',     'icon' => 'heroicons-outline:chart-pie',        'color' => 'blue'],
            ['key' => 'purchases',   'label' => 'Purchases',     'icon' => 'heroicons-outline:shopping-bag',     'color' => 'green'],
            ['key' => 'revenue',     'label' => 'Conv. Value',    'icon' => 'heroicons-outline:banknotes',        'color' => 'green'],
            ['key' => 'roas',        'label' => 'Purchase ROAS',  'icon' => 'heroicons-outline:arrow-trending-up','color' => 'purple'],
        ];
        @endphp

        @foreach($kpis as $kpi)
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="{{ $kpi['icon'] }}" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">{{ $kpi['label'] }}</span>
            </div>
            <div id="kpi-{{ $kpi['key'] }}" class="text-2xl font-bold text-slate-900 tabular-nums">&mdash;</div>
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

        {{-- Impressions vs Clicks --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:chart-bar" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Daily Impressions vs Link Clicks</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="impressionsClicksChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Breakdowns --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

        {{-- Placement Breakdown --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:squares-2x2" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Spend by Placement</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[260px]">
                    <canvas id="placementChart"></canvas>
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
            <div class="flex gap-2">
                <button onclick="expandAll()" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:chevron-double-down" width="14"></iconify-icon> Expand All
                </button>
                <button onclick="collapseAll()" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:chevron-double-up" width="14"></iconify-icon> Collapse All
                </button>
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
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('impressions')">
                            Impressions <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Reach</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('clicks')">
                            Link Clicks <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">CTR (Link)</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('purchases')">
                            Purchases <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Conv. Value</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('roas')">
                            ROAS <iconify-icon icon="heroicons-outline:arrows-up-down" width="12" class="ml-1 opacity-50"></iconify-icon>
                        </th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">CPC (Link)</th>
                        <th class="text-right px-3 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">CPM</th>
                    </tr>
                </thead>
                <tbody id="campaignTableBody">
                    <tr><td colspan="13" class="text-center py-8 text-slate-400">Duke ngarkuar...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
    .adset-row { background: #F8FAFC; }
    .dark .adset-row { background: #0F172A; }
    .campaign-row { cursor: pointer; }
    .campaign-row:hover { background: #FAFAFA; }
    .dark .campaign-row:hover { background: #1E293B; }
    .expand-icon { transition: transform 0.2s; display: inline-block; }
    .expand-icon.rotated { transform: rotate(90deg); }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    let spendRoasChart = null;
    let impressionsClicksChart = null;
    let ageChart = null;
    let genderChart = null;
    let platformChart = null;
    let loadGeneration = 0;
    let placementChart = null;
    let campaignsData = [];
    let sortField = 'spend';
    let sortAsc = false;

    const baseUrl = '{{ route("marketing.analytics.index") }}';
    const datePresetEl = document.getElementById('datePreset');
    const dateFromEl = document.getElementById('dateFrom');
    const dateToEl = document.getElementById('dateTo');
    const platformEl = document.getElementById('platformFilter');
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
            if (e.name === 'AbortError') {
                throw new Error('Kërkesa u anulua ose skadoi.');
            }
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
    if (platformEl) {
        platformEl.addEventListener('change', loadAll);
    }

    datePresetEl.value = 'this_month';
    applyPreset('this_month');

    async function loadAll(event, fresh = false) {
        const from = dateFromEl.value;
        const to = dateToEl.value;
        const platform = platformEl ? (platformEl.value || 'all') : 'all';
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

        // Rifresko → dispatch background sync, then poll for completion
        if (fresh) {
            if (activeBtn) activeBtn.innerHTML = '<iconify-icon icon="line-md:loading-twotone-loop" width="16"></iconify-icon> Duke sinkronizuar...';
            try {
                const { data: syncResp } = await fetchApi('sync', { from, to, force: 1, channel: 'ads' }, 30000);
                if (syncResp.already_running) {
                    showSyncToast('syncing', 'Rifresko tashmë po ekzekutohet...', 'Një sync tjetër ende po punon. Duke pritur përfundimin...');
                }
            } catch (e) {
                showSyncToast('failed', 'Rifresko nuk u nis dot', e.message || 'Gabim rrjeti');
                console.warn('Meta sync dispatch failed:', e);
            }

            // Poll for background job completion
            try {
                await pollSyncStatus(refreshBtn);
            } catch (e) {
                console.warn('Sync polling ended:', e);
            }
        }

        if (thisGen !== loadGeneration) return;
        if (activeBtn) activeBtn.innerHTML = '<iconify-icon icon="line-md:loading-twotone-loop" width="16"></iconify-icon> Duke ngarkuar...';

        const extra = fresh ? { nocache: 1 } : {};

        const results = await Promise.allSettled([
            loadKPIs(from, to, platform, extra, thisGen),
            loadCharts(from, to, platform, extra, thisGen),
            loadCampaigns(from, to, platform, extra, thisGen),
            loadBreakdowns(from, to, extra, thisGen)
        ]);
        if (thisGen !== loadGeneration) return;
        results.forEach((r, i) => {
            if (r.status === 'rejected') console.error(`Load task ${i} failed:`, r.reason);
        });

        // Update "Sync i fundit" timestamp after successful Rifresko
        if (fresh) {
            const syncEl = document.getElementById('last-sync-text');
            if (syncEl) syncEl.textContent = 'Sync i fundit: pak sekonda më parë';
        }

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

    /** Poll /api/sync-status every 5s with toast notification. */
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
                    const items = (data.refreshed || []).join(', ');
                    showSyncToast('done', 'Rifresko u kompletua!', 'U sinkronizuan: ' + items + '. Koha: ' + timeStr + '. Duke ngarkuar...');
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

    async function loadKPIs(from, to, platform, extra = {}, gen = null) {
        const { data } = await fetchApi('ads-kpis', { from, to, platform, ...extra });
        if (gen !== null && gen !== loadGeneration) return;

        const formatters = {
            spend: v => fmtEur(v),
            impressions: v => fmtNum(v),
            reach: v => fmtNum(v),
            link_clicks: v => fmtNum(v),
            ctr: v => fmtPct(v),
            purchases: v => fmtNum(v),
            revenue: v => fmtEur(v),
            roas: v => fmtRoas(v),
        };

        Object.entries(data).forEach(([key, info]) => {
            const valEl = document.getElementById(`kpi-${key}`);
            const changeEl = document.getElementById(`kpi-${key}-change`);
            if (!valEl) return;

            valEl.textContent = formatters[key] ? formatters[key](info.value) : info.value;

            if (changeEl) {
                if (info.change === null || info.change === undefined) {
                    // PR 1: empty until PR 3 fills with delta vs previous period.
                    changeEl.textContent = '';
                    changeEl.className = 'text-[11px] mt-1 font-medium text-slate-400';
                } else if (info.change === 'new') {
                    changeEl.textContent = 'E re — 0 vitin e kaluar';
                    changeEl.className = 'text-[11px] mt-1 font-medium text-blue-600';
                } else {
                    const sign = info.change > 0 ? '+' : '';
                    changeEl.textContent = `${sign}${info.change}% vs 1 vit më parë`;
                    changeEl.className = 'text-[11px] mt-1 font-medium ' + (info.change > 0 ? 'text-emerald-600' : info.change < 0 ? 'text-red-600' : 'text-slate-400');
                }
            }
        });
    }

    async function loadCharts(from, to, platform, extra = {}, gen = null) {
        const { data } = await fetchApi('ads-daily', { from, to, platform, ...extra });
        if (gen !== null && gen !== loadGeneration) return;

        const labels = data.map(d => formatDate(d.date));

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
                        data: data.map(d => d.roas),
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
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (ctx.dataset.yAxisID === 'y') return `Spend: ${fmtEur(ctx.raw)}`;
                                return `ROAS: ${ctx.raw}x`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        type: 'linear', position: 'left',
                        title: { display: true, text: 'Spend (€)' },
                        ticks: { callback: v => '€' + v }
                    },
                    y1: {
                        type: 'linear', position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'ROAS' },
                        ticks: { callback: v => v + 'x' },
                        min: 0,
                    },
                },
            },
        });

        if (impressionsClicksChart) impressionsClicksChart.destroy();
        impressionsClicksChart = new Chart(document.getElementById('impressionsClicksChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Impressions',
                        data: data.map(d => d.impressions),
                        borderColor: '#E65100',
                        backgroundColor: 'rgba(230, 81, 0, 0.08)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y',
                        borderWidth: 2,
                        pointRadius: 2,
                    },
                    {
                        label: 'Link Clicks',
                        data: data.map(d => d.link_clicks),
                        borderColor: '#1565C0',
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
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return `${ctx.dataset.label}: ${fmtNum(ctx.raw)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        type: 'linear', position: 'left',
                        title: { display: true, text: 'Impressions' },
                        ticks: { callback: v => fmtNum(v) }
                    },
                    y1: {
                        type: 'linear', position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Link Clicks' },
                    },
                },
            },
        });
    }

    async function loadCampaigns(from, to, platform, extra = {}, gen = null) {
        const { data } = await fetchApi('ads-campaigns', { from, to, platform, ...extra });
        if (gen !== null && gen !== loadGeneration) return;
        campaignsData = data;
        renderCampaigns();
    }

    function renderCampaigns() {
        const tbody = document.getElementById('campaignTableBody');

        if (!campaignsData.length) {
            tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nuk ka të dhëna për këtë periudhë</td></tr>';
            return;
        }

        const sorted = [...campaignsData].sort((a, b) => {
            let va = a[sortField], vb = b[sortField];
            if (typeof va === 'string') { va = va.toLowerCase(); vb = vb.toLowerCase(); }
            if (sortAsc) return va > vb ? 1 : va < vb ? -1 : 0;
            return va < vb ? 1 : va > vb ? -1 : 0;
        });

        let html = '';
        sorted.forEach((c, idx) => {
            const hasAdSets = c.ad_sets && c.ad_sets.length > 0;
            html += `
                <tr class="campaign-row border-b border-slate-50 hover:bg-slate-50/50" onclick="${hasAdSets ? `toggleAdSets(${idx})` : ''}" data-campaign="${idx}">
                    <td class="text-left px-4 py-3">
                        <div class="flex items-center gap-2">
                            ${hasAdSets ? `<iconify-icon icon="heroicons-outline:chevron-right" width="14" class="expand-icon text-slate-400" id="expand-${idx}"></iconify-icon>` : '<span class="inline-block w-3.5"></span>'}
                            <div>
                                <span class="font-semibold text-slate-900">${escHtml(c.name)}</span>
                                ${hasAdSets ? `<span class="text-[11px] text-slate-400 ml-1">(${c.ad_sets.length} ad sets)</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="text-center px-3 py-3">
                        <span class="inline-block px-2.5 py-1 rounded text-[11px] font-medium ${c.status === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : c.status === 'PAUSED' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-500 border border-slate-200'}">${c.status}</span>
                    </td>
                    <td class="text-center px-3 py-3 text-xs text-slate-500">${c.objective || '—'}</td>
                    <td class="text-right px-3 py-3 font-semibold">${fmtEur(c.spend)}</td>
                    <td class="text-right px-3 py-3">${fmtNum(c.impressions)}</td>
                    <td class="text-right px-3 py-3">${fmtNum(c.reach)}</td>
                    <td class="text-right px-3 py-3">${fmtNum(c.link_clicks)}</td>
                    <td class="text-right px-3 py-3">${fmtPct(c.ctr)}</td>
                    <td class="text-right px-3 py-3">${fmtNum(c.purchases)}</td>
                    <td class="text-right px-3 py-3 font-semibold">${fmtEur(c.revenue)}</td>
                    <td class="text-right px-3 py-3 font-bold" style="color: ${roasColor(c.roas)};">${fmtRoas(c.roas)}</td>
                    <td class="text-right px-3 py-3 text-xs">${fmtEur(c.cpc)}</td>
                    <td class="text-right px-3 py-3 text-xs">${fmtEur(c.cpm)}</td>
                </tr>
            `;

            if (hasAdSets) {
                c.ad_sets.forEach(as => {
                    html += `
                        <tr class="adset-row hidden border-b border-slate-50" data-parent="${idx}">
                            <td class="text-left pl-10 pr-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <iconify-icon icon="heroicons-outline:adjustments-horizontal" width="14" class="text-slate-400"></iconify-icon>
                                    <span class="text-[13px] text-slate-600">${escHtml(as.name)}</span>
                                </div>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] ${as.status === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${as.status}</span>
                            </td>
                            <td class="text-center px-3 py-2.5 text-[11px] text-slate-400">${as.optimization_goal || '—'}</td>
                            <td class="text-right px-3 py-2.5 text-xs">${fmtEur(as.spend)}</td>
                            <td class="text-right px-3 py-2.5 text-xs">${fmtNum(as.impressions)}</td>
                            <td class="text-right px-3 py-2.5 text-xs">—</td>
                            <td class="text-right px-3 py-2.5 text-xs">${fmtNum(as.link_clicks)}</td>
                            <td class="text-right px-3 py-2.5 text-xs">—</td>
                            <td class="text-right px-3 py-2.5 text-xs">${fmtNum(as.purchases)}</td>
                            <td class="text-right px-3 py-2.5 text-xs">${fmtEur(as.revenue)}</td>
                            <td class="text-right px-3 py-2.5 text-xs font-semibold" style="color: ${roasColor(as.roas)};">${fmtRoas(as.roas)}</td>
                            <td class="text-right px-3 py-2.5 text-xs">—</td>
                            <td class="text-right px-3 py-2.5 text-xs">—</td>
                        </tr>
                    `;
                });
            }
        });

        tbody.innerHTML = html;
    }

    function toggleAdSets(idx) {
        const rows = document.querySelectorAll(`tr[data-parent="${idx}"]`);
        const icon = document.getElementById(`expand-${idx}`);
        const isHidden = rows[0]?.classList.contains('hidden');

        rows.forEach(r => r.classList.toggle('hidden'));
        if (icon) icon.classList.toggle('rotated', isHidden);
    }

    function expandAll() {
        document.querySelectorAll('.adset-row').forEach(r => r.classList.remove('hidden'));
        document.querySelectorAll('.expand-icon').forEach(i => i.classList.add('rotated'));
    }

    function collapseAll() {
        document.querySelectorAll('.adset-row').forEach(r => r.classList.add('hidden'));
        document.querySelectorAll('.expand-icon').forEach(i => i.classList.remove('rotated'));
    }

    function sortTable(field) {
        if (sortField === field) {
            sortAsc = !sortAsc;
        } else {
            sortField = field;
            sortAsc = false;
        }
        renderCampaigns();
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    const breakdownColors = ['#1E88E5', '#E65100', '#2E7D32', '#7B1FA2', '#C62828', '#00838F', '#F9A825', '#4E342E', '#546E7A', '#AD1457'];

    function createBreakdownChart(canvasId, chartRef, labels, values, title) {
        if (chartRef) chartRef.destroy();
        const colors = labels.map((_, i) => breakdownColors[i % breakdownColors.length]);
        return new Chart(document.getElementById(canvasId), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 1,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 11 }, padding: 8 } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                return `${ctx.label}: ${fmtEur(ctx.raw)} (${pct}%)`;
                            }
                        }
                    }
                },
            },
        });
    }

    async function loadBreakdowns(from, to, extra = {}, gen = null) {
        const { data } = await fetchApi('ads-breakdowns', { from, to, ...extra });
        if (gen !== null && gen !== loadGeneration) return;

        // Age
        const ageLabels = Object.keys(data.age || {});
        const ageValues = ageLabels.map(k => data.age[k].spend || 0);
        if (ageLabels.length) {
            ageChart = createBreakdownChart('ageChart', ageChart, ageLabels, ageValues, 'Spend by Age');
        }

        // Gender
        const genderLabels = Object.keys(data.gender || {}).map(g => g === 'male' ? 'Male' : g === 'female' ? 'Female' : g);
        const genderValues = Object.values(data.gender || {}).map(v => v.spend || 0);
        if (genderLabels.length) {
            genderChart = createBreakdownChart('genderChart', genderChart, genderLabels, genderValues, 'Spend by Gender');
        }

        // Platform
        const platformLabels = Object.keys(data.platform || {}).map(p => {
            const names = { facebook: 'Facebook', instagram: 'Instagram', audience_network: 'Audience Network', messenger: 'Messenger' };
            return names[p] || p;
        });
        const platformValues = Object.values(data.platform || {}).map(v => v.spend || 0);
        if (platformLabels.length) {
            platformChart = createBreakdownChart('platformChart', platformChart, platformLabels, platformValues, 'Spend by Platform');
        }

        // Placement
        const placementLabels = Object.keys(data.placement || {}).map(p => p.replace(/_/g, ' '));
        const placementValues = Object.values(data.placement || {}).map(v => v.spend || 0);
        if (placementLabels.length) {
            placementChart = createBreakdownChart('placementChart', placementChart, placementLabels, placementValues, 'Spend by Placement');
        }
    }

    document.addEventListener('DOMContentLoaded', () => loadAll());
</script>
@endsection
