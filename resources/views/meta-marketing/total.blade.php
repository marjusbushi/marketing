@extends('_layouts.app', [
    'title' => 'Dashboard — Total Report',
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
        @if(config('tiktok.features.tiktok_module'))
        <a href="{{ route('marketing.analytics.tiktok') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="logos:tiktok-icon" width="14"></iconify-icon> TikTok
        </a>
        @endif
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white">
            <iconify-icon icon="heroicons-outline:chart-bar-square" width="14"></iconify-icon> Total
        </span>
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

<div id="total-report" class="space-y-6">

    {{-- Page header --}}
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:chart-bar-square" width="22" class="text-primary-500"></iconify-icon>
            Total Meta Report
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Facebook Organic + Instagram Organic + Paid Ads — Graph API {{ $apiVersionV24 }}
            @if($lastSync)
                — <span id="last-sync-text" class="text-xs">Sync i fundit: {{ $lastSync->created_at->min(now())->diffForHumans() }}</span>
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
            <div class="flex items-end gap-2">
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
    <div id="kpiCards" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 gap-3">
        @php
        $kpis = [
            ['key' => 'total_reach', 'label' => 'Total Reach*', 'icon' => 'heroicons-outline:users', 'color' => 'violet'],
            ['key' => 'total_impressions', 'label' => 'Views', 'icon' => 'heroicons-outline:eye', 'color' => 'blue'],
            ['key' => 'total_page_views', 'label' => 'Visits', 'icon' => 'heroicons-outline:window', 'color' => 'teal'],
            ['key' => 'total_engagement', 'label' => 'Interactions', 'icon' => 'heroicons-outline:heart', 'color' => 'rose'],
            ['key' => 'combined_link_clicks', 'label' => 'Link Clicks', 'icon' => 'heroicons-outline:cursor-arrow-rays', 'color' => 'indigo'],
            ['key' => 'total_link_clicks', 'label' => 'Organic Clicks', 'icon' => 'heroicons-outline:cursor-arrow-rays', 'color' => 'emerald'],
            ['key' => 'ads_link_clicks', 'label' => 'Ads Clicks', 'icon' => 'heroicons-outline:megaphone', 'color' => 'amber'],
            ['key' => 'new_threads', 'label' => 'New Threads', 'icon' => 'heroicons-outline:chat-bubble-left-right', 'color' => 'orange'],
            ['key' => 'conversations', 'label' => 'Messages', 'icon' => 'heroicons-outline:chat-bubble-bottom-center-text', 'color' => 'cyan'],
            ['key' => 'ads_spend', 'label' => 'Ads Spend', 'icon' => 'heroicons-outline:currency-euro', 'color' => 'blue'],
            ['key' => 'ads_revenue', 'label' => 'Ads Revenue', 'icon' => 'heroicons-outline:banknotes', 'color' => 'green'],
            ['key' => 'roas', 'label' => 'ROAS', 'icon' => 'heroicons-outline:arrow-trending-up', 'color' => 'teal'],
            ['key' => 'fb_reach', 'label' => 'FB Reach', 'icon' => 'logos:facebook', 'color' => 'blue'],
            ['key' => 'ig_reach', 'label' => 'IG Reach', 'icon' => 'skill-icons:instagram', 'color' => 'pink'],
        ];
        @endphp

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

    <p class="text-[11px] text-slate-400 -mt-3">* Combined non-deduplicated across Ads, Facebook, Instagram.</p>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Daily Reach --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:chart-bar" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Daily Reach per Kanal</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[300px]">
                    <canvas id="reachByChannelChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Ads Spend vs Clicks --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:currency-euro" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Ads Spend vs Link Clicks</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[300px]">
                    <canvas id="adsSpendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Channel Comparison --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:scale" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Krahasimi i Kanaleve</h3>
        </div>
        <div class="p-5 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- Facebook --}}
                <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-5">
                    <div class="flex items-center gap-2.5 mb-4">
                        <iconify-icon icon="logos:facebook" width="24"></iconify-icon>
                        <span class="text-[15px] font-bold text-blue-700">Facebook</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Reach</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-fb-reach">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Impressions</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-fb-impressions">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Engagement</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-fb-engagement">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Conversations</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-fb-conversations">&mdash;</div>
                        </div>
                    </div>
                </div>

                {{-- Instagram --}}
                <div class="rounded-xl border border-pink-100 bg-pink-50/40 p-5">
                    <div class="flex items-center gap-2.5 mb-4">
                        <iconify-icon icon="skill-icons:instagram" width="24"></iconify-icon>
                        <span class="text-[15px] font-bold text-pink-700">Instagram</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Reach</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ig-reach">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Profile Views</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ig-profile_views">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Engagement</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ig-engagement">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">DM Conversations</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ig-conversations">&mdash;</div>
                        </div>
                    </div>
                </div>

                {{-- Paid Ads --}}
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-5">
                    <div class="flex items-center gap-2.5 mb-4">
                        <iconify-icon icon="heroicons-outline:megaphone" width="24" class="text-emerald-600"></iconify-icon>
                        <span class="text-[15px] font-bold text-emerald-700">Paid Ads</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Reach</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ads-reach">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Impressions</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ads-impressions">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Link Clicks</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ads-link_clicks">&mdash;</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Spend</div>
                            <div class="text-lg font-bold text-slate-900 tabular-nums" id="comp-ads-spend">&mdash;</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Revenue</div>
                            <div class="text-lg font-bold text-emerald-600 tabular-nums" id="comp-ads-revenue">&mdash;</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Comparison Bar Chart --}}
            <div class="relative w-full h-[300px]">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    let reachByChannelInstance = null;
    let adsSpendInstance = null;
    let comparisonInstance = null;
    let loadGeneration = 0;

    const baseUrl = '{{ route("marketing.analytics.index") }}';
    const datePresetEl = document.getElementById('datePreset');
    const dateFromEl = document.getElementById('dateFrom');
    const dateToEl = document.getElementById('dateTo');
    const customFromWrap = document.getElementById('customDateFromWrap');
    const customToWrap = document.getElementById('customDateToWrap');

    function fmtNum(n) { return Number(n).toLocaleString('de-DE'); }
    function fmtEur(n) { return '\u20AC' + Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fmtRoas(n) { return Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + 'x'; }

    let activeAbortController = null;

    async function fetchApi(endpoint, params = {}, timeoutMs = 120000) {
        const timeoutController = new AbortController();
        const timer = setTimeout(() => timeoutController.abort(), timeoutMs);
        const signals = [timeoutController.signal];
        if (activeAbortController) signals.push(activeAbortController.signal);
        const signal = signals.length > 1 ? AbortSignal.any(signals) : signals[0];
        try {
            const res = await fetch(apiUrl(endpoint, params), { signal, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            clearTimeout(timer);
            const contentType = res.headers.get('Content-Type') || '';
            if (contentType.includes('text/html')) { window.location.reload(); throw new Error('Sesioni ka skaduar.'); }
            if (!res.ok) { const body = await res.text().catch(() => ''); throw new Error(`HTTP ${res.status}: ${body.substring(0, 200) || res.statusText}`); }
            const data = await res.json();
            const cached = res.headers.get('X-Meta-Cache') === 'HIT';
            return { data, cached };
        } catch (e) { clearTimeout(timer); if (e.name === 'AbortError') throw new Error('Kërkesa u anulua.'); throw e; }
    }

    function formatDate(dateString) { const d = new Date(dateString); return d.getDate().toString().padStart(2,'0') + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()]; }
    function apiUrl(endpoint, params = {}) { const url = new URL(baseUrl + '/api/' + endpoint, window.location.origin); Object.entries(params).forEach(([k,v]) => url.searchParams.set(k,v)); return url.toString(); }
    function fmtD(d) { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }

    function applyPreset(preset) {
        if (!preset || preset === '') return;
        if (preset === 'custom') { customFromWrap.classList.remove('hidden'); customToWrap.classList.remove('hidden'); return; }
        customFromWrap.classList.add('hidden'); customToWrap.classList.add('hidden');
        // reportDay = today (perfshi diten e sotme me partial data nga sync hourly).
        const today = new Date(), reportDay = new Date(today);
        let from, to;
        switch(preset) {
            case 'today': from=to=fmtD(today); break;
            case 'yesterday': const y=new Date(today); y.setDate(y.getDate()-1); from=to=fmtD(y); break;
            case 'this_week': const mw=new Date(reportDay); mw.setDate(mw.getDate()-(mw.getDay()||7)+1); from=fmtD(mw); to=fmtD(reportDay); break;
            case 'last_week': const lw=new Date(today); lw.setDate(lw.getDate()-(lw.getDay()||7)-6); const lwe=new Date(lw); lwe.setDate(lwe.getDate()+6); from=fmtD(lw); to=fmtD(lwe); break;
            case 'this_month': from=fmtD(new Date(reportDay.getFullYear(),reportDay.getMonth(),1)); to=fmtD(reportDay); break;
            case 'last_month': from=fmtD(new Date(today.getFullYear(),today.getMonth()-1,1)); to=fmtD(new Date(today.getFullYear(),today.getMonth(),0)); break;
            case 'this_quarter': const qs=Math.floor(reportDay.getMonth()/3)*3; from=fmtD(new Date(reportDay.getFullYear(),qs,1)); to=fmtD(reportDay); break;
            case 'last_quarter': const lqs=Math.floor(today.getMonth()/3)*3-3; from=fmtD(new Date(today.getFullYear(),lqs,1)); to=fmtD(new Date(today.getFullYear(),lqs+3,0)); break;
            case 'this_year': from=fmtD(new Date(reportDay.getFullYear(),0,1)); to=fmtD(reportDay); break;
            case 'last_year': from=fmtD(new Date(today.getFullYear()-1,0,1)); to=fmtD(new Date(today.getFullYear()-1,11,31)); break;
            case 'ytd': from=fmtD(new Date(reportDay.getFullYear(),0,1)); to=fmtD(reportDay); break;
        }
        dateFromEl.value = from; dateToEl.value = to;
    }

    datePresetEl.addEventListener('change', () => { applyPreset(datePresetEl.value); if (datePresetEl.value !== 'custom' && datePresetEl.value !== '') loadAll(datePresetEl.value); });
    datePresetEl.value = 'this_month'; applyPreset('this_month');

    async function loadAll(presetOverride = null, fresh = false) {
        const from = dateFromEl.value, to = dateToEl.value;
        if (!from || !to) return;
        if (activeAbortController) activeAbortController.abort();
        activeAbortController = new AbortController();
        const thisGen = ++loadGeneration;
        const preset = presetOverride || (datePresetEl.value !== 'custom' ? datePresetEl.value : null);
        const btns = document.querySelectorAll('#total-report button[onclick]');
        btns.forEach(b => { b.disabled = true; b.classList.add('opacity-60'); });

        if (fresh) {
            try {
                const { data: syncResp } = await fetchApi('sync', { from, to, force: 1 }, 30000);
                if (syncResp.already_running) showSyncToast('syncing', 'Rifresko po ekzekutohet...', 'Një sync tjetër po punon.');
            } catch (e) { showSyncToast('failed', 'Rifresko dështoi', e.message); }
            try { await pollSyncStatus(); } catch (e) {}
        }
        if (thisGen !== loadGeneration) return;

        const extra = fresh ? { nocache: 1 } : {};
        try { await loadKPIs(from, to, preset, extra, thisGen); } catch (e) {}
        await Promise.allSettled([loadDailyChart(from, to, preset, extra, thisGen), loadComparison(from, to, preset, {}, thisGen)]);
        if (thisGen !== loadGeneration) return;
        if (fresh) { const syncEl = document.getElementById('last-sync-text'); if (syncEl) syncEl.textContent = 'Sync i fundit: pak sekonda më parë'; }
        btns.forEach(b => { b.disabled = false; b.classList.remove('opacity-60'); });
    }

    function showSyncToast(type, title, msg) {
        const toast = document.getElementById('syncToast');
        const styles = { syncing: { bg:'bg-blue-50', border:'border-blue-200', color:'text-blue-700', icon:'line-md:loading-twotone-loop' }, done: { bg:'bg-emerald-50', border:'border-emerald-200', color:'text-emerald-700', icon:'heroicons-outline:check-circle' }, failed: { bg:'bg-red-50', border:'border-red-200', color:'text-red-700', icon:'heroicons-outline:x-circle' } };
        const s = styles[type] || styles.syncing;
        toast.className = `fixed top-4 right-4 z-[9999] min-w-[340px] max-w-[420px] rounded-xl p-4 shadow-xl border ${s.bg} ${s.border}`;
        document.getElementById('syncToastIcon').innerHTML = `<iconify-icon icon="${s.icon}" width="22" class="${s.color}"></iconify-icon>`;
        document.getElementById('syncToastTitle').className = `text-sm font-semibold ${s.color}`;
        document.getElementById('syncToastTitle').textContent = title;
        document.getElementById('syncToastMsg').className = 'text-xs text-slate-600 mt-0.5';
        document.getElementById('syncToastMsg').textContent = msg;
    }
    function hideSyncToast() { document.getElementById('syncToast').classList.add('hidden'); }

    async function pollSyncStatus(maxWaitMs = 600000) {
        const start = Date.now();
        showSyncToast('syncing', 'Rifresko po ekzekutohet...', 'Koha: ~2-5 minuta.');
        while (Date.now() - start < maxWaitMs) {
            await new Promise(r => setTimeout(r, 5000));
            try {
                const { data } = await fetchApi('sync-status', {}, 10000);
                const elapsed = Math.round((Date.now()-start)/1000), mm = Math.floor(elapsed/60), ss = elapsed%60;
                const timeStr = mm > 0 ? mm+'m '+ss+'s' : ss+'s';
                if (data.status === 'done') { showSyncToast('done', 'Kompletuar!', (data.refreshed||[]).join(', ')+'. '+timeStr); setTimeout(hideSyncToast, 8000); return; }
                if (data.status === 'failed') { showSyncToast('failed', 'Dështoi', data.error || 'Gabim'); setTimeout(hideSyncToast, 12000); return; }
                showSyncToast('syncing', 'Duke sinkronizuar...', timeStr + ' kanë kaluar');
            } catch (e) {}
        }
    }

    async function loadKPIs(from, to, preset, extra, gen) {
        const params = { from, to, ...extra }; if (preset) params.preset = preset;
        const { data } = await fetchApi('total-kpis', params, 300000);
        if (gen !== null && gen !== loadGeneration) return;
        const fmt = { total_reach:fmtNum, total_impressions:fmtNum, total_page_views:fmtNum, total_engagement:fmtNum, combined_link_clicks:fmtNum, total_link_clicks:fmtNum, ads_link_clicks:fmtNum, new_threads:fmtNum, conversations:fmtNum, fb_reach:fmtNum, ig_reach:fmtNum, ads_spend:fmtEur, ads_revenue:fmtEur, roas:fmtRoas };
        Object.entries(data).forEach(([key, info]) => {
            const valEl = document.getElementById(`kpi-${key}`), changeEl = document.getElementById(`kpi-${key}-change`);
            if (!valEl) return;
            valEl.textContent = fmt[key] ? fmt[key](info.value) : info.value;
            if (changeEl) {
                if (info.change === null || info.change === undefined) { changeEl.textContent = 'Pa informacion'; changeEl.className = 'text-[11px] mt-1 font-medium text-slate-400'; }
                else if (info.change === 'new') { changeEl.textContent = 'E re'; changeEl.className = 'text-[11px] mt-1 font-medium text-blue-600'; }
                else { changeEl.textContent = (info.change>0?'+':'')+info.change+'% vs 1 vit'; changeEl.className = 'text-[11px] mt-1 font-medium ' + (info.change>0?'text-emerald-600':info.change<0?'text-red-500':'text-slate-400'); }
            }
        });
    }

    async function loadDailyChart(from, to, preset, extra, gen) {
        const params = { from, to, ...extra }; if (preset) params.preset = preset;
        const { data } = await fetchApi('total-daily', params);
        if (gen !== null && gen !== loadGeneration) return;
        const labels = data.map(d => formatDate(d.date));
        const chartOpts = { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{legend:{position:'top',labels:{usePointStyle:true,pointStyle:'circle',padding:16,font:{size:11}}},tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtNum(ctx.raw)}`}}}, scales:{x:{ticks:{maxRotation:0,autoSkip:true,maxTicksLimit:12,font:{size:10}},grid:{display:false}},y:{ticks:{callback:v=>fmtNum(v),font:{size:10}},grid:{color:'#f1f5f9'}}} };

        if (reachByChannelInstance) reachByChannelInstance.destroy();
        reachByChannelInstance = new Chart(document.getElementById('reachByChannelChart'), { type:'line', data:{ labels, datasets:[
            { label:'FB Reach', data:data.map(d=>d.fb_reach), borderColor:'#1d4ed8', backgroundColor:'rgba(29,78,216,.05)', fill:true, tension:.35, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
            { label:'IG Reach', data:data.map(d=>d.ig_reach), borderColor:'#be185d', backgroundColor:'rgba(190,24,93,.05)', fill:true, tension:.35, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
            { label:'Ads Reach', data:data.map(d=>d.ads_reach), borderColor:'#059669', backgroundColor:'rgba(5,150,105,.05)', fill:true, tension:.35, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
        ]}, options: chartOpts });

        if (adsSpendInstance) adsSpendInstance.destroy();
        adsSpendInstance = new Chart(document.getElementById('adsSpendChart'), { type:'line', data:{ labels, datasets:[
            { label:'Spend (€)', data:data.map(d=>d.ads_spend), borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.06)', fill:true, tension:.35, borderWidth:2, pointRadius:0, pointHoverRadius:4, yAxisID:'y' },
            { label:'Link Clicks', data:data.map(d=>d.ads_link_clicks), borderColor:'#ea580c', backgroundColor:'transparent', tension:.35, borderWidth:2, pointRadius:0, pointHoverRadius:4, yAxisID:'y1' },
        ]}, options:{...chartOpts, scales:{x:{ticks:{maxRotation:0,autoSkip:true,maxTicksLimit:12,font:{size:10}},grid:{display:false}},y:{type:'linear',position:'left',title:{display:true,text:'Spend (€)',font:{size:10}},ticks:{callback:v=>'€'+v,font:{size:10}},grid:{color:'#f1f5f9'}},y1:{type:'linear',position:'right',grid:{drawOnChartArea:false},title:{display:true,text:'Clicks',font:{size:10}},ticks:{font:{size:10}}}}} });
    }

    async function loadComparison(from, to, preset, extra, gen) {
        const params = { from, to, ...extra }; if (preset) params.preset = preset;
        const { data } = await fetchApi('total-comparison', params);
        if (gen !== null && gen !== loadGeneration) return;
        document.getElementById('comp-fb-reach').textContent = fmtNum(data.facebook.reach);
        document.getElementById('comp-fb-impressions').textContent = fmtNum(data.facebook.impressions);
        document.getElementById('comp-fb-engagement').textContent = fmtNum(data.facebook.engagement);
        document.getElementById('comp-fb-conversations').textContent = fmtNum(data.facebook.conversations);
        document.getElementById('comp-ig-reach').textContent = fmtNum(data.instagram.reach);
        document.getElementById('comp-ig-profile_views').textContent = fmtNum(data.instagram.profile_views);
        document.getElementById('comp-ig-engagement').textContent = fmtNum(data.instagram.engagement);
        document.getElementById('comp-ig-conversations').textContent = fmtNum(data.instagram.conversations);
        document.getElementById('comp-ads-reach').textContent = fmtNum(data.ads.reach);
        document.getElementById('comp-ads-impressions').textContent = fmtNum(data.ads.impressions);
        document.getElementById('comp-ads-link_clicks').textContent = fmtNum(data.ads.link_clicks);
        document.getElementById('comp-ads-spend').textContent = fmtEur(data.ads.spend);
        document.getElementById('comp-ads-revenue').textContent = fmtEur(data.ads.revenue);

        if (comparisonInstance) comparisonInstance.destroy();
        comparisonInstance = new Chart(document.getElementById('comparisonChart'), { type:'bar', data:{ labels:['Reach','Engagement','Conversations'], datasets:[
            { label:'Facebook', data:[data.facebook.reach,data.facebook.engagement,data.facebook.conversations], backgroundColor:'rgba(29,78,216,.7)', borderRadius:6 },
            { label:'Instagram', data:[data.instagram.reach,data.instagram.engagement,data.instagram.conversations], backgroundColor:'rgba(190,24,93,.7)', borderRadius:6 },
            { label:'Paid Ads', data:[data.ads.reach,data.ads.link_clicks,0], backgroundColor:'rgba(5,150,105,.7)', borderRadius:6 },
        ]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top',labels:{usePointStyle:true,pointStyle:'circle',padding:16,font:{size:11}}},tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtNum(ctx.raw)}`}}}, scales:{y:{ticks:{callback:v=>fmtNum(v),font:{size:10}},grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} } });
    }

    document.addEventListener('DOMContentLoaded', () => loadAll());
</script>
@endsection
