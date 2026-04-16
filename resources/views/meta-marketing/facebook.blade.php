@extends('_layouts.app', [
    'title' => 'Dashboard — Facebook',
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
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white">
            <iconify-icon icon="logos:facebook" width="14"></iconify-icon> Facebook
        </span>
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

<div id="fb-report" class="space-y-6">

    {{-- Page header --}}
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <iconify-icon icon="logos:facebook" width="22"></iconify-icon>
            Facebook Report
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Facebook Organic + Messenger &mdash; Graph API {{ $apiVersionV24 }}
            @if($lastSync)
                &mdash; <span id="last-sync-text" class="text-xs">Sync i fundit: {{ $lastSync->created_at->diffForHumans() }}</span>
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
    <div id="kpiCards" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
        @php
        $kpis = [
            ['key' => 'post_impressions', 'label' => 'Views', 'icon' => 'heroicons-outline:eye',
             'tooltip' => 'Post impressions + Video views + Reels plays. Does not include Stories (no API available).<br><span class="text-slate-400">Përshtypjet e postimeve + Shikimet e videove + Luajtjet e Reels. Nuk përfshin Stories (nuk ka API).</span>'],
            ['key' => 'reach', 'label' => 'Reach', 'icon' => 'heroicons-outline:users',
             'tooltip' => 'Unique people who saw feed posts. FB Pro "Viewers" is broader (includes Reels/Stories viewers) — not available via API.<br><span class="text-slate-400">Përdorues unikë që panë postimet. "Viewers" i FB Pro përfshin edhe Reels/Stories — nuk disponohet nga API.</span>'],
            ['key' => 'page_engagements', 'label' => 'Content Interactions', 'icon' => 'heroicons-outline:hand-thumb-up',
             'tooltip' => 'Post reactions (date-scoped) + Reel likes &amp; comments. Approximates Meta\'s "Content interactions".<br><span class="text-slate-400">Reagime postimesh + pëlqime &amp; komente Reels. Përafron "Content interactions" të Meta.</span>'],
            ['key' => 'page_views', 'label' => 'Visits', 'icon' => 'heroicons-outline:window', 'tooltip' => null],
            ['key' => 'ads_link_clicks', 'label' => 'Link Clicks', 'icon' => 'heroicons-outline:cursor-arrow-rays',
             'tooltip' => 'Ads link clicks only. Organic page link clicks metric was deprecated by Meta (Mar 2024) with no API replacement. Difference with Meta Pro is typically &lt;2%.<br><span class="text-slate-400">Vetëm klikimet nga reklamat. Metrika organike u hoq nga Meta (Mar 2024) pa zëvendësim API. Diferenca me Meta Pro zakonisht &lt;2%.</span>'],
            ['key' => 'new_threads', 'label' => 'New Threads', 'icon' => 'heroicons-outline:chat-bubble-left-right',
             'tooltip' => 'New conversations started with your page (organic + paid).<br><span class="text-slate-400">Biseda të reja me faqen tuaj (organike + me pagesë).</span>'],
        ];
        @endphp

        @foreach($kpis as $kpi)
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow relative group">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="{{ $kpi['icon'] }}" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">{{ $kpi['label'] }}</span>
            </div>
            <div id="kpi-{{ $kpi['key'] }}" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-{{ $kpi['key'] }}-change" class="text-[11px] mt-1 font-medium"></div>
            @if($kpi['tooltip'])
            <div class="absolute top-2 right-2.5 w-[18px] h-[18px] rounded-full bg-slate-200 flex items-center justify-center cursor-pointer z-[2]" tabindex="0">
                <span class="text-[11px] font-bold text-slate-500 italic leading-none">i</span>
                <div class="hidden group-hover:block absolute top-6 right-0 bg-slate-900 text-slate-200 p-2.5 rounded-lg text-[11px] leading-relaxed w-60 shadow-lg z-10">{!! $kpi['tooltip'] !!}</div>
            </div>
            @endif
        </div>
        @endforeach
    </div>

    <p class="text-[11px] text-slate-400 -mt-3 leading-relaxed">
        * Reach is deduplicated — each unique user is counted only once, even if they saw content on both Facebook and Instagram. This may show a lower number than Meta Insights, which counts each platform separately.<br>
        <span class="text-slate-300">* Reach është i de-duplikuar — çdo përdorues unik numërohet vetëm një herë, edhe nëse ka parë përmbajtje në Facebook dhe Instagram. Kjo mund të tregojë një numër më të ulët se Meta Insights, i cili i numëron platformat veçmas.</span>
    </p>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Daily Organic Metrics --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:chart-bar" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Daily Organic Metrics</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Messenger --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:chat-bubble-left-right" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Messenger</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="messengerChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Follower Growth --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:users" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Follower Growth</h3>
            </div>
            <div class="flex items-center gap-2">
                <span id="followerNetBadge" class="hidden text-[13px] font-semibold px-2.5 py-1 rounded-md"></span>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-blue-50 text-blue-700 border border-blue-200 text-sm font-semibold">
                    <iconify-icon icon="heroicons-outline:user-group" width="16"></iconify-icon>
                    <span id="totalFollowersBadge">&mdash;</span> Total Followers
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="relative w-full h-[280px]">
                <canvas id="followersChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Daily Breakdown Table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:table-cells" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Daily Breakdown (Views, Reach, Interactions, SMS)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[940px]">
                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="text-left px-4 py-3 text-slate-600 font-medium">Data</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">Reach</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">Post Impressions</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">Page Views</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">Page Engagements</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">Post Engagement</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">SMS New Threads</th>
                        <th class="text-right px-4 py-3 text-slate-600 font-medium">Paid Conversations</th>
                    </tr>
                </thead>
                <tbody id="dailyBreakdownBody">
                    <tr>
                        <td colspan="8" class="px-5 py-5 text-center text-slate-400">Duke ngarkuar...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Top Facebook Posts --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:fire" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Top Facebook Posts</h3>
        </div>
        <div class="p-5">
            <div id="topPostsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                <div class="text-center py-8 text-slate-400 col-span-full">Duke ngarkuar...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    // Crosshair plugin — draws a vertical line at hover position
    const crosshairPlugin = {
        id: 'crosshair',
        afterDraw(chart) {
            if (chart.tooltip?._active?.length) {
                const x = chart.tooltip._active[0].element.x;
                const top = chart.scales.y ? chart.scales.y.top : chart.chartArea.top;
                const bottom = chart.scales.y ? chart.scales.y.bottom : chart.chartArea.bottom;
                const ctx = chart.ctx;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x, top);
                ctx.lineTo(x, bottom);
                ctx.lineWidth = 1;
                ctx.strokeStyle = 'rgba(0, 0, 0, 0.12)';
                ctx.setLineDash([4, 3]);
                ctx.stroke();
                ctx.restore();
            }
        }
    };
    Chart.register(crosshairPlugin);

    let dailyChartInstance = null;
    let messengerChartInstance = null;
    let followersChartInstance = null;
    let lastDailyRows = [];
    let loadGeneration = 0;

    const baseUrl = '{{ route("marketing.analytics.index") }}';
    const datePresetEl = document.getElementById('datePreset');
    const dateFromEl = document.getElementById('dateFrom');
    const dateToEl = document.getElementById('dateTo');
    const customFromWrap = document.getElementById('customDateFromWrap');
    const customToWrap = document.getElementById('customDateToWrap');

    function fmtNum(n) { return Number(n).toLocaleString('de-DE'); }

    function parseDisplayedNumber(text) {
        const normalized = String(text || '')
            .replace(/[^\d,.-]/g, '')
            .replace(/\./g, '')
            .replace(',', '.');
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function applyDailyKpiFallback(data = lastDailyRows) {
        if (!Array.isArray(data) || !data.length) return;

        const totals = {
            reach: data.reduce((sum, row) => sum + Number(row.reach || 0), 0),
            post_impressions: data.reduce((sum, row) => sum + Number(row.post_impressions || 0), 0),
            page_views: data.reduce((sum, row) => sum + Number(row.page_views || 0), 0),
            page_engagements: data.reduce((sum, row) => sum + Number(row.page_engagements || 0), 0),
            post_engagement: data.reduce((sum, row) => sum + Number(row.post_engagement || 0), 0),
            new_threads: data.reduce((sum, row) => sum + Number(row.new_threads || 0), 0),
        };

        Object.entries(totals).forEach(([key, value]) => {
            if (!value) return;

            const el = document.getElementById(`kpi-${key}`);
            if (!el) return;

            if (parseDisplayedNumber(el.textContent) <= 0) {
                el.textContent = fmtNum(value);
            }
        });
    }
    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = date.getDate().toString().padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${day} ${months[date.getMonth()]}`;
    }
    function apiUrl(endpoint, params = {}) {
        const url = new URL(baseUrl + '/api/' + endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        return url.toString();
    }
    function fmtD(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
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
            presetDebounce = setTimeout(() => loadAll(datePresetEl.value), 250);
        }
    });

    datePresetEl.value = 'this_month';
    applyPreset('this_month');

    async function loadAll(presetOverride = null, fresh = false) {
        const from = dateFromEl.value;
        const to = dateToEl.value;
        if (!from || !to) return;

        if (activeAbortController) activeAbortController.abort();
        activeAbortController = new AbortController();

        const thisGen = ++loadGeneration;
        lastDailyRows = [];

        const preset = presetOverride || (datePresetEl.value !== 'custom' ? datePresetEl.value : null);

        const btns = document.querySelectorAll('#fb-report button[onclick]');
        btns.forEach(b => { b.disabled = true; b.classList.add('opacity-60'); });

        // Rifresko → dispatch background sync, then poll for completion
        if (fresh) {
            try {
                const { data: syncResp } = await fetchApi('sync', { from, to, force: 1, channel: 'facebook' }, 30000);
                if (syncResp.already_running) {
                    showSyncToast('syncing', 'Rifresko tashmë po ekzekutohet...', 'Një sync tjetër ende po punon. Duke pritur përfundimin...');
                }
            } catch (e) {
                showSyncToast('failed', 'Rifresko nuk u nis dot', e.message || 'Gabim rrjeti');
                console.warn('Meta sync dispatch failed:', e.message);
            }

            // Poll for background job completion
            try {
                await pollSyncStatus();
            } catch (e) {
                console.warn('Sync polling ended:', e);
            }
        }

        if (thisGen !== loadGeneration) return;

        const extra = fresh ? { nocache: 1 } : {};

        const results = [];
        results.push(await Promise.allSettled([
            loadDaily(from, to, preset, extra, thisGen)
        ]));
        if (thisGen !== loadGeneration) return;
        results.push(await Promise.allSettled([
            loadKPIs(from, to, preset, extra, thisGen),
            loadTopPosts(from, to, preset, extra, thisGen)
        ]));
        applyDailyKpiFallback();
        results.flat().forEach((r, i) => {
            if (r.status === 'rejected') console.error(`Load task ${i} failed:`, r.reason);
        });

        if (thisGen !== loadGeneration) return;

        // Update "Sync i fundit" timestamp after successful Rifresko
        if (fresh) {
            const syncEl = document.getElementById('last-sync-text');
            if (syncEl) syncEl.textContent = 'Sync i fundit: pak sekonda më parë';
        }

        btns.forEach(b => { b.disabled = false; b.classList.remove('opacity-60'); });
    }

    /** Show/hide sync toast notification */
    function showSyncToast(type, title, msg) {
        const toast = document.getElementById('syncToast');
        const styles = {
            syncing: { bg: 'bg-blue-50', border: 'border-blue-200', color: 'text-blue-700', icon: 'line-md:loading-twotone-loop' },
            done:    { bg: 'bg-emerald-50', border: 'border-emerald-200', color: 'text-emerald-700', icon: 'heroicons-outline:check-circle' },
            failed:  { bg: 'bg-red-50', border: 'border-red-200', color: 'text-red-700', icon: 'heroicons-outline:x-circle' },
        };
        const s = styles[type] || styles.syncing;
        toast.className = `fixed top-4 right-4 z-[9999] min-w-[340px] max-w-[420px] rounded-xl p-4 shadow-xl border ${s.bg} ${s.border}`;
        document.getElementById('syncToastIcon').innerHTML = `<iconify-icon icon="${s.icon}" width="22" class="${s.color}"></iconify-icon>`;
        document.getElementById('syncToastTitle').className = `text-sm font-semibold ${s.color}`;
        document.getElementById('syncToastTitle').textContent = title;
        document.getElementById('syncToastMsg').className = 'text-xs text-slate-600 mt-0.5';
        document.getElementById('syncToastMsg').textContent = msg;
    }
    function hideSyncToast() {
        document.getElementById('syncToast').classList.add('hidden');
    }

    /** Poll /api/sync-status every 5s with toast notification. */
    async function pollSyncStatus(maxWaitMs = 600000) {
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

    async function loadKPIs(from, to, preset = null, extra = {}, gen = null) {
        const params = { from, to, ...extra };
        if (preset) params.preset = preset;
        const { data } = await fetchApi('fb-kpis', params);
        if (gen !== null && gen !== loadGeneration) return;

        Object.entries(data).forEach(([key, info]) => {
            const valEl = document.getElementById(`kpi-${key}`);
            const changeEl = document.getElementById(`kpi-${key}-change`);
            if (!valEl) return;
            valEl.textContent = fmtNum(info.value);
            if (changeEl) {
                changeEl.style.fontSize = '11px';
                changeEl.style.marginTop = '4px';
                changeEl.style.fontWeight = '500';
                if (info.change === null || info.change === undefined) {
                    changeEl.textContent = 'Metrik\u00eb e re \u2014 pa informacion';
                    changeEl.style.color = '#9E9E9E';
                } else if (info.change === 'new') {
                    changeEl.textContent = 'E re \u2014 0 vitin e kaluar';
                    changeEl.style.color = '#1565C0';
                } else {
                    const sign = info.change > 0 ? '+' : '';
                    changeEl.textContent = `${sign}${info.change}% vs 1 vit m\u00eb par\u00eb`;
                    changeEl.style.color = info.change > 0 ? '#2E7D32' : info.change < 0 ? '#C62828' : '#9E9E9E';
                }
            }
        });
    }

    async function loadDaily(from, to, preset = null, extra = {}, gen = null) {
        const params = { from, to, ...extra };
        if (preset) params.preset = preset;
        const { data } = await fetchApi('fb-daily', params);
        if (gen !== null && gen !== loadGeneration) return;
        lastDailyRows = Array.isArray(data) ? data : [];
        const labels = data.map(d => formatDate(d.date));

        if (dailyChartInstance) dailyChartInstance.destroy();
        dailyChartInstance = new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Reach', data: data.map(d => d.reach || 0), borderColor: '#1565C0', tension: 0.3, fill: false },
                    { label: 'Post Impressions', data: data.map(d => d.post_impressions || 0), borderColor: '#0D47A1', tension: 0.3, fill: false },
                    { label: 'Page Views', data: data.map(d => d.page_views || 0), borderColor: '#E65100', tension: 0.3, fill: false },
                    { label: 'Page Engagements', data: data.map(d => d.page_engagements || 0), borderColor: '#FF6F00', tension: 0.3, fill: false, hidden: true },
                    { label: 'Post Engagement', data: data.map(d => d.post_engagement || 0), borderColor: '#AD1457', tension: 0.3, fill: false, hidden: true },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: { x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }
            },
        });

        renderDailyBreakdown(data);

        if (messengerChartInstance) messengerChartInstance.destroy();
        messengerChartInstance = new Chart(document.getElementById('messengerChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'New Threads', data: data.map(d => d.new_threads || 0), borderColor: '#2E7D32', tension: 0.3, fill: false },
                    { label: 'Paid Conversations', data: data.map(d => d.messages_received || 0), borderColor: '#6A1B9A', tension: 0.3, fill: false },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: { x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }
            },
        });

        // Follower Growth chart
        const totalFollowersBadge = document.getElementById('totalFollowersBadge');
        const followerNetBadge = document.getElementById('followerNetBadge');
        if (totalFollowersBadge && data.length > 0) {
            let latestFollowers = [...data].reverse().find(d => d.page_followers > 0)?.page_followers || 0;
            if (!latestFollowers) {
                latestFollowers = [...data].reverse().find(d => d.page_fans > 0)?.page_fans || 0;
            }
            totalFollowersBadge.textContent = fmtNum(latestFollowers);

            // Net change badge
            const netChange = data.reduce((sum, d) => sum + (d.page_daily_follows || 0), 0);
            if (followerNetBadge) {
                const sign = netChange >= 0 ? '+' : '';
                followerNetBadge.textContent = sign + fmtNum(netChange) + ' in period';
                followerNetBadge.classList.remove('hidden');
                followerNetBadge.style.backgroundColor = netChange >= 0 ? '#E8F5E9' : '#FFEBEE';
                followerNetBadge.style.color = netChange >= 0 ? '#2E7D32' : '#C62828';
                followerNetBadge.style.border = '1px solid ' + (netChange >= 0 ? '#A5D6A7' : '#EF9A9A');
            }
        }

        if (followersChartInstance) followersChartInstance.destroy();
        followersChartInstance = new Chart(document.getElementById('followersChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'New Followers',
                        data: data.map(d => d.page_daily_follows || 0),
                        backgroundColor: '#1565C0',
                        borderColor: '#0D47A1',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                    y: { type: 'linear', position: 'left', title: { display: true, text: 'New Followers' } },
                },
            },
        });
    }

    function renderDailyBreakdown(data) {
        const tbody = document.getElementById('dailyBreakdownBody');
        if (!tbody) return;

        if (!Array.isArray(data) || !data.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-5 py-5 text-center text-slate-400">Nuk ka të dhëna ditore</td></tr>';
            return;
        }

        tbody.innerHTML = data.map((row, index) => {
            const bg = index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50';
            return `
                <tr class="${bg} border-b border-slate-100">
                    <td class="px-4 py-2.5 text-slate-800">${row.date || ''}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.reach || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.post_impressions || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.page_views || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.page_engagements || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.post_engagement || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.new_threads || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-800 tabular-nums">${fmtNum(row.messages_received || 0)}</td>
                </tr>
            `;
        }).join('');
    }

    async function loadTopPosts(from, to, preset = null, extra = {}, gen = null) {
        const grid = document.getElementById('topPostsGrid');
        const params = { from, to, limit: 12, ...extra };
        if (preset) params.preset = preset;
        const { data } = await fetchApi('fb-top-posts', params);
        if (gen !== null && gen !== loadGeneration) return;
        if (!data.length) {
            grid.innerHTML = '<div class="text-center py-8 text-slate-400 col-span-full">Nuk ka postime</div>';
            return;
        }
        grid.innerHTML = data.map(p => `
            <a href="${p.permalink_url || '#'}" target="_blank" class="block rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
                ${p.media_url ? `<img src="${p.media_url}" class="w-full h-40 object-cover" alt="" loading="lazy" />` : `<div class="w-full h-40 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center"><iconify-icon icon="heroicons-outline:photo" width="40" class="text-slate-300"></iconify-icon></div>`}
                <div class="p-3">
                    <div class="text-[11px] text-slate-400">${p.post_type || 'post'} &bull; ${p.created_at || ''}</div>
                    <div class="text-[13px] text-slate-700 mt-1.5 leading-snug line-clamp-2">${p.message || 'Pa tekst'}</div>
                    <div class="flex gap-2.5 mt-2 text-xs text-slate-500 flex-wrap">
                        <span>&#10084;&#65039; ${p.likes || 0}</span>
                        <span>&#128172; ${p.comments || 0}</span>
                        <span>&#128260; ${p.shares || 0}</span>
                    </div>
                </div>
            </a>
        `).join('');
    }

    document.addEventListener('DOMContentLoaded', () => loadAll());
</script>
@endsection
