@extends('_layouts.app', [
    'title' => 'Dashboard — Instagram',
    'pageTitle' => 'Dashboard'
])

@section('header-actions')
    <nav class="flex items-center gap-1.5">
        <a href="{{ route('marketing.analytics.ads') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
            <iconify-icon icon="heroicons-outline:megaphone" width="14"></iconify-icon> Ads
        </a>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white">
            <iconify-icon icon="skill-icons:instagram" width="14"></iconify-icon> Instagram
        </span>
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

<div id="ig-report" class="space-y-6">

    {{-- Page header --}}
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <iconify-icon icon="skill-icons:instagram" width="22" class="text-primary-500"></iconify-icon>
            Instagram Report
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Instagram Business Account &mdash; Graph API {{ $apiVersionV24 }}
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
                    <option value="">Zgjidh periudh&euml;n...</option>
                    <option value="today">Sot</option>
                    <option value="yesterday">Dje</option>
                    <option value="this_week">Kjo Jav&euml;</option>
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
            <div class="flex items-end gap-2 zoho-filter-actions">
                <button onclick="loadAll()" class="h-[30px] inline-flex items-center gap-1.5 px-3.5 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors zoho-btn-primary">
                    <iconify-icon icon="heroicons-outline:arrow-path" width="15"></iconify-icon> Ngarko
                </button>
                <button onclick="loadAll(null, true)" class="h-[30px] inline-flex items-center gap-1 px-2.5 rounded-md border border-slate-200 text-slate-500 text-xs font-medium hover:bg-slate-50 transition-colors zoho-btn-secondary" title="Rifresko pa cache">
                    <iconify-icon icon="heroicons-outline:bolt" width="15"></iconify-icon> Rifresko
                </button>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div id="kpiCards" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="views">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:eye" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Views</span>
            </div>
            <div id="kpi-views" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-views-change" class="text-[11px] mt-1 font-medium"></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="reach">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:users" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Reach</span>
            </div>
            <div id="kpi-reach" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-reach-change" class="text-[11px] mt-1 font-medium"></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="content_interactions">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:hand-thumb-up" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Content Interactions</span>
            </div>
            <div id="kpi-content_interactions" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-content_interactions-change" class="text-[11px] mt-1 font-medium"></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="profile_views">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:user-circle" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Visits</span>
            </div>
            <div id="kpi-profile_views" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-profile_views-change" class="text-[11px] mt-1 font-medium"></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="combined_link_clicks" title="Bio-Link-Taps + Ads Link Clicks (kombiniert). Meta Insights zeigt nur Story/Ads-Klicks.">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:cursor-arrow-rays" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Link Clicks</span>
            </div>
            <div id="kpi-combined_link_clicks" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-combined_link_clicks-change" class="text-[11px] mt-1 font-medium"></div>
            <div class="text-[10px] text-slate-400 mt-0.5">Bio-Taps + Ads</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="link_clicks" title="Taps auf den Website-Link im Instagram-Profil (Bio). Entspricht 'Website Taps' in Instagram Insights.">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:cursor-arrow-rays" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Bio Link Taps</span>
            </div>
            <div id="kpi-link_clicks" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-link_clicks-change" class="text-[11px] mt-1 font-medium"></div>
            <div class="text-[10px] text-slate-400 mt-0.5">Profil-Website-Klicks</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="ads_link_clicks" title="Link Clicks aus Instagram Ads (actions[link_click] mit Attribution). Entspricht Ads Manager.">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:megaphone" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">Ads Link Clicks</span>
            </div>
            <div id="kpi-ads_link_clicks" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-ads_link_clicks-change" class="text-[11px] mt-1 font-medium"></div>
            <div class="text-[10px] text-slate-400 mt-0.5">Ads Manager</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 hover:shadow-sm transition-shadow" data-key="new_threads">
            <div class="flex items-center gap-2 mb-2">
                <iconify-icon icon="heroicons-outline:chat-bubble-left-right" width="16" class="text-slate-400"></iconify-icon>
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">DM Kontakte</span>
            </div>
            <div id="kpi-new_threads" class="text-xl font-bold text-slate-900 tabular-nums">&mdash;</div>
            <div id="kpi-new_threads-change" class="text-[11px] mt-1 font-medium"></div>
        </div>
    </div>

    {{-- Charts Row 1 --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Reach & Views --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:chart-bar" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Daily Reach, Visits, Views & Link Clicks</h3>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="reachChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Content Interactions --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <iconify-icon icon="heroicons-outline:hand-thumb-up" width="18" class="text-slate-400"></iconify-icon>
                    <h3 class="text-sm font-semibold text-slate-800">Content Interactions</h3>
                </div>
                <div class="flex items-center gap-2">
                    <span id="interactionsTotalBadge" class="text-lg font-bold text-blue-700">&mdash;</span>
                    <span id="interactionsChangeBadge" class="hidden text-[13px] font-semibold px-2.5 py-1 rounded-md"></span>
                </div>
            </div>
            <div class="p-5">
                <div class="relative w-full h-[280px]">
                    <canvas id="interactionsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row 2 — Followers --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:users" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Follower Growth</h3>
            </div>
            <div class="flex items-center gap-2">
                <span id="followerNetBadge" class="hidden text-[13px] font-semibold px-2.5 py-1 rounded-md"></span>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-purple-50 text-purple-700 border border-purple-300 text-sm font-semibold pointer-events-none">
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

    {{-- Messaging Section --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:chat-bubble-left-right" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Instagram DMs</h3>
        </div>
        <div class="p-5">
            <div class="flex items-center justify-center gap-6 mb-4" id="messagingSection">
                <div class="px-6 py-4 rounded-lg bg-pink-50 border border-pink-200 text-center">
                    <iconify-icon icon="heroicons-outline:chat-bubble-left-right" width="28" class="text-pink-800"></iconify-icon>
                    <div id="msg-conversations" class="text-2xl font-bold text-pink-800 mt-2">&mdash;</div>
                    <div class="text-xs text-slate-500">Kontakte Aktive</div>
                </div>
                <div class="text-[13px] text-slate-500 leading-relaxed">
                    Aktiviteti ditor i bisedave n&euml; Instagram DM (organike + ads).
                </div>
            </div>
            <div class="relative w-full h-[280px]">
                <canvas id="messengerChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Daily Breakdown Table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:table-cells" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Daily Breakdown (Views, Reach, Interactions, DMs)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left px-4 py-3 text-slate-700 font-semibold">Data</th>
                        <th class="text-right px-4 py-3 text-slate-700 font-semibold">Views</th>
                        <th class="text-right px-4 py-3 text-slate-700 font-semibold">Reach</th>
                        <th class="text-right px-4 py-3 text-slate-700 font-semibold">Visits</th>
                        <th class="text-right px-4 py-3 text-slate-700 font-semibold">Interactions</th>
                        <th class="text-right px-4 py-3 text-slate-700 font-semibold">DM Kontakte</th>
                    </tr>
                </thead>
                <tbody id="dailyBreakdownBody">
                    <tr>
                        <td colspan="6" class="px-5 py-5 text-center text-slate-400">Duke ngarkuar...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Top Posts --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:fire" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Top Posts (by engagement)</h3>
            </div>
            <div class="flex gap-1.5">
                <button onclick="filterPosts(null)" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white post-filter active" data-type="all">T&euml; gjitha</button>
                <button onclick="filterPosts('photo')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors post-filter" data-type="photo">Foto</button>
                <button onclick="filterPosts('reel')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors post-filter" data-type="reel">Reels</button>
                <button onclick="filterPosts('video')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors post-filter" data-type="video">Video</button>
                <button onclick="filterPosts('carousel_album')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors post-filter" data-type="carousel_album">Carousel</button>
                <button onclick="filterPosts('story')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors post-filter" data-type="story">Stories</button>
            </div>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4" id="topPostsGrid">
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

    let reachChartInstance = null;
    let interactionsChartInstance = null;
    let followersChartInstance = null;
    let messengerChartInstance = null;
    let currentPostFilter = null;
    let loadGeneration = 0;

    const baseUrl = '{{ route("marketing.analytics.index") }}';
    const datePresetEl = document.getElementById('datePreset');
    const dateFromEl = document.getElementById('dateFrom');
    const dateToEl = document.getElementById('dateTo');
    const customFromWrap = document.getElementById('customDateFromWrap');
    const customToWrap = document.getElementById('customDateToWrap');

    function fmtNum(n) { return Number(n).toLocaleString('de-DE'); }
    function fmtPct(n) { return Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%'; }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = date.getDate().toString().padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${day} ${months[date.getMonth()]}`;
    }

    function apiUrl(endpoint, params = {}) {
        const url = new URL(baseUrl + '/api/' + endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined) url.searchParams.set(k, v); });
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

    datePresetEl.addEventListener('change', () => {
        applyPreset(datePresetEl.value);
        if (datePresetEl.value !== 'custom' && datePresetEl.value !== '') loadAll(datePresetEl.value);
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

        const preset = presetOverride || (datePresetEl.value !== 'custom' ? datePresetEl.value : null);

        // Disable ALL buttons in .zoho-filter-actions during loading
        const actionBtns = document.querySelectorAll('.zoho-filter-actions button');
        actionBtns.forEach(b => {
            b.dataset.originalHtml = b.dataset.originalHtml || b.innerHTML;
            b.disabled = true;
            b.style.opacity = '0.7';
        });
        const primaryBtn = document.querySelector('.zoho-filter-actions button.zoho-btn-primary');
        const refreshBtn = document.querySelector('.zoho-filter-actions button.zoho-btn-secondary');
        const activeBtn = fresh ? refreshBtn : primaryBtn;

        // Rifresko → dispatch background sync, then poll for completion
        if (fresh) {
            if (activeBtn) activeBtn.innerHTML = '<iconify-icon icon="line-md:loading-twotone-loop" width="16"></iconify-icon> Duke sinkronizuar...';
            try {
                const { data: syncResp } = await fetchApi('sync', { from, to, force: 1, channel: 'instagram' }, 30000);
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
            loadKPIs(from, to, preset, extra, thisGen),
            loadCharts(from, to, preset, extra, thisGen),
            loadTopPosts(null, from, to, preset, extra, thisGen),
            loadMessaging(from, to, preset, extra, thisGen)
        ]);

        results.forEach((r, i) => {
            if (r.status === 'rejected') {
                console.error(`Dashboard loader [${i}] failed:`, r.reason);
            }
        });

        if (thisGen !== loadGeneration) return;

        // Update "Sync i fundit" timestamp after successful Rifresko
        if (fresh) {
            const syncEl = document.getElementById('last-sync-text');
            if (syncEl) syncEl.textContent = 'Sync i fundit: pak sekonda më parë';
        }

        // Re-enable ALL buttons
        actionBtns.forEach(b => {
            b.innerHTML = b.dataset.originalHtml;
            b.disabled = false;
            b.style.opacity = '1';
        });
    }

    /** Show/hide sync toast notification */
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

    // KPIs
    async function loadKPIs(from, to, preset = null, extra = {}, gen = null) {
        const params = { from, to, ...extra };
        if (preset) params.preset = preset;
        const { data } = await fetchApi('ig-kpis', params);
        if (gen !== null && gen !== loadGeneration) return;

        const formatters = {
            views: v => fmtNum(v),
            reach: v => fmtNum(v),
            profile_views: v => fmtNum(v),
            followers: v => fmtNum(v),
            new_followers: v => (v >= 0 ? '+' : '') + fmtNum(v),
            website_clicks: v => fmtNum(v),
            content_interactions: v => fmtNum(v),
            combined_link_clicks: v => fmtNum(v),
            link_clicks: v => fmtNum(v),
            ads_link_clicks: v => fmtNum(v),
            new_threads: v => fmtNum(v),
            conversations: v => fmtNum(v),
        };

        Object.entries(data).forEach(([key, info]) => {
            const valEl = document.getElementById(`kpi-${key}`);
            const changeEl = document.getElementById(`kpi-${key}-change`);
            if (!valEl) return;

            valEl.textContent = formatters[key] ? formatters[key](info.value) : info.value;

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

    // Charts
    async function loadCharts(from, to, preset = null, extra = {}, gen = null) {
        const params = { from, to, ...extra };
        if (preset) params.preset = preset;
        const { data } = await fetchApi('ig-daily', params);
        if (gen !== null && gen !== loadGeneration) return;

        const labels = data.map(d => formatDate(d.date));

        // Reach & Views chart (without Interactions — they have their own chart now)
        if (reachChartInstance) reachChartInstance.destroy();
        reachChartInstance = new Chart(document.getElementById('reachChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Views',
                        data: data.map(d => d.views || 0),
                        borderColor: '#6A1B9A',
                        backgroundColor: 'rgba(106, 27, 154, 0.08)',
                        fill: false,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Reach',
                        data: data.map(d => d.reach),
                        borderColor: '#AD1457',
                        backgroundColor: 'rgba(173, 20, 87, 0.08)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Visits',
                        data: data.map(d => d.profile_views),
                        borderColor: '#1565C0',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 2,
                        yAxisID: 'y1',
                    },
                    {
                        label: 'Link Clicks',
                        data: data.map(d => d.website_clicks),
                        borderColor: '#E65100',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        borderWidth: 2,
                        borderDash: [5, 3],
                        pointRadius: 2,
                        yAxisID: 'y1',
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
                    y: { type: 'linear', position: 'left', title: { display: true, text: 'Reach' } },
                    y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Visits' } },
                },
            },
        });

        // Content Interactions chart (dedicated, like Meta's native view)
        if (interactionsChartInstance) interactionsChartInstance.destroy();
        const interactionsData = data.map(d => d.content_interactions || 0);
        const interactionsTotal = interactionsData.reduce((s, v) => s + v, 0);

        // Update total badge
        const intBadge = document.getElementById('interactionsTotalBadge');
        if (intBadge) intBadge.textContent = fmtNum(interactionsTotal);

        interactionsChartInstance = new Chart(document.getElementById('interactionsChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Content Interactions',
                    data: interactionsData,
                    borderColor: '#1565C0',
                    backgroundColor: 'rgba(21, 101, 192, 0.10)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#1565C0',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => 'Interactions: ' + fmtNum(ctx.parsed.y),
                        },
                    },
                },
                scales: {
                    x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        title: { display: false },
                        ticks: { callback: v => fmtNum(v) },
                    },
                },
            },
        });

        // Update the total followers badge with the most recent follower count
        const totalFollowersBadge = document.getElementById('totalFollowersBadge');
        const followerNetBadge = document.getElementById('followerNetBadge');
        if (totalFollowersBadge && data.length > 0) {
            // Find the latest non-zero follower count, or use the last day's count
            let latestFollowers = [...data].reverse().find(d => d.follower_count > 0)?.follower_count || data[data.length - 1].follower_count;
            totalFollowersBadge.textContent = fmtNum(latestFollowers);

            // Net change badge
            const netChange = data.reduce((sum, d) => sum + (d.new_followers || 0), 0);
            if (followerNetBadge) {
                const sign = netChange >= 0 ? '+' : '';
                followerNetBadge.textContent = sign + fmtNum(netChange) + ' in period';
                followerNetBadge.classList.remove('hidden');
                followerNetBadge.style.backgroundColor = netChange >= 0 ? '#E8F5E9' : '#FFEBEE';
                followerNetBadge.style.color = netChange >= 0 ? '#2E7D32' : '#C62828';
                followerNetBadge.style.border = '1px solid ' + (netChange >= 0 ? '#A5D6A7' : '#EF9A9A');
            }
        }

        // Followers chart (New Followers Only)
        if (followersChartInstance) followersChartInstance.destroy();
        followersChartInstance = new Chart(document.getElementById('followersChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'New Followers',
                        data: data.map(d => d.new_followers || 0),
                        backgroundColor: '#2E7D32',
                        borderColor: '#1B5E20',
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

        renderDailyBreakdown(data);
    }

    function renderDailyBreakdown(data) {
        const tbody = document.getElementById('dailyBreakdownBody');
        if (!tbody) return;

        if (!Array.isArray(data) || !data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-5 py-5 text-center text-slate-400">Nuk ka t\u00eb dh\u00ebna ditore</td></tr>';
            return;
        }

        tbody.innerHTML = data.map((row, index) => {
            const bg = index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50';
            return `
                <tr class="${bg} border-b border-slate-100">
                    <td class="px-4 py-2.5 text-slate-900">${row.date || ''}</td>
                    <td class="px-4 py-2.5 text-right text-slate-900 tabular-nums">${fmtNum(row.views || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-900 tabular-nums">${fmtNum(row.reach || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-900 tabular-nums">${fmtNum(row.profile_views || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-900 tabular-nums">${fmtNum(row.content_interactions || 0)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-900 tabular-nums">${fmtNum(row.conversations || 0)}</td>
                </tr>
            `;
        }).join('');
    }

    // Messaging
    async function loadMessaging(from, to, preset = null, extra = {}, gen = null) {
        const params = { from, to, ...extra };
        if (preset) params.preset = preset;
        const { data } = await fetchApi('ig-messaging', params);
        if (gen !== null && gen !== loadGeneration) return;

        document.getElementById('msg-conversations').textContent = fmtNum(data.totals.conversations);

        // DM activity chart — single line showing daily contacts
        if (data.daily && data.daily.length > 0) {
            const labels = data.daily.map(d => formatDate(d.date));

            if (messengerChartInstance) messengerChartInstance.destroy();
            messengerChartInstance = new Chart(document.getElementById('messengerChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'DM Kontakte', data: data.daily.map(d => d.new_conversations || 0), borderColor: '#AD1457', backgroundColor: 'rgba(173,20,87,0.08)', tension: 0.3, fill: true, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#AD1457' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: { x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }
                },
            });
        }
    }

    // Top Posts
    async function loadTopPosts(type, from, to, preset = null, extra = {}, gen = null) {
        const grid = document.getElementById('topPostsGrid');
        const params = { limit: 12, ...extra };
        if (type) params.type = type;
        if (from) params.from = from;
        if (to) params.to = to;
        if (preset) params.preset = preset;

        const { data } = await fetchApi('ig-top-posts', params);
        if (gen !== null && gen !== loadGeneration) return;

        if (!data.length) {
            grid.innerHTML = '<div class="text-center py-8 text-slate-400 col-span-full">Nuk ka postime</div>';
            return;
        }

        grid.innerHTML = data.map(p => {
            const imageUrl = p.media_url || null;
            const typeLabel = p.post_type || 'post';
            const typeBadge = {
                'reel': { bg: '#F3E5F5', color: '#7B1FA2', icon: 'heroicons-outline:play' },
                'story': { bg: '#FFF3E0', color: '#E65100', icon: 'heroicons-outline:clock' },
                'video': { bg: '#E3F2FD', color: '#1565C0', icon: 'heroicons-outline:video-camera' },
                'carousel_album': { bg: '#E8F5E9', color: '#2E7D32', icon: 'heroicons-outline:squares-2x2' },
                'photo': { bg: '#FCE4EC', color: '#AD1457', icon: 'heroicons-outline:photo' },
            }[typeLabel] || { bg: '#ECEFF1', color: '#546E7A', icon: 'heroicons-outline:photo' };

            return `
            <a href="${p.permalink_url || '#'}" target="_blank" class="block bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition-shadow no-underline">
                ${imageUrl
                    ? `<img src="${imageUrl}" class="w-full h-[180px] object-cover bg-gradient-to-br from-slate-100 to-slate-200" alt="" loading="lazy" onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');" /><div class="hidden w-full h-[180px] bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center flex-col gap-2"><iconify-icon icon="heroicons-outline:photo" width="40" class="text-slate-300"></iconify-icon></div>`
                    : `<div class="w-full h-[180px] bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center"><iconify-icon icon="heroicons-outline:photo" width="40" class="text-slate-300"></iconify-icon></div>`
                }
                <div class="p-3">
                    <div class="flex gap-1.5 items-center mb-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium" style="background: ${typeBadge.bg}; color: ${typeBadge.color};">
                            <iconify-icon icon="${typeBadge.icon}" width="12"></iconify-icon>
                            ${typeLabel}
                        </span>
                        <span class="text-[10px] text-slate-400">${p.created_at || ''}</span>
                    </div>
                    <div class="text-[13px] text-slate-700 leading-snug line-clamp-2">${p.message || 'Pa tekst'}</div>
                    <div class="flex gap-2 mt-2 text-xs text-slate-500 flex-wrap">
                        <span title="Likes">\u2764\uFE0F ${fmtNum(p.likes)}</span>
                        <span title="Comments">\uD83D\uDCAC ${fmtNum(p.comments)}</span>
                        <span title="Shares">\uD83D\uDD01 ${fmtNum(p.shares)}</span>
                        <span title="Saves">\uD83D\uDCCC ${fmtNum(p.saves)}</span>
                    </div>
                    ${p.engagement ? `<div class="text-[11px] text-pink-700 font-medium mt-1.5">Content Interactions: ${fmtNum(p.engagement)}</div>` : ''}
                </div>
            </a>
        `}).join('');
    }

    function filterPosts(type) {
        currentPostFilter = type;
        // Update active button
        document.querySelectorAll('.post-filter').forEach(btn => {
            if ((!type && btn.dataset.type === 'all') || btn.dataset.type === type) {
                btn.className = 'inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md bg-primary-600 text-white post-filter active';
            } else {
                btn.className = 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors post-filter';
            }
        });
        loadTopPosts(type, dateFromEl.value, dateToEl.value);
    }

    document.addEventListener('DOMContentLoaded', () => loadAll());
</script>
@endsection
