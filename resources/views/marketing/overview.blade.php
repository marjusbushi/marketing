@extends('_layouts.app', [
    'title' => 'Marketing — Overview',
    'pageTitle' => 'Marketing Overview',
])

@section('content')

@include('marketing._partials.nav')

<div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50">Marketing Overview</h2>
</div>

{{-- KPI Strip --}}
<div class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-2.5 mb-4" id="kpi-strip">
    <div class="col-span-full text-center py-3 text-gray-400 text-[13px]">Loading KPIs...</div>
</div>

{{-- Channel Reach Row --}}
<div class="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-2.5 mb-6 hidden" id="channel-row"></div>

{{-- Three Panels --}}
<div class="grid grid-cols-3 gap-3.5 mb-6 max-lg:grid-cols-1">
    {{-- Tasks Summary --}}
    <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#333] p-4 min-h-[280px]">
        <div class="text-xs font-bold uppercase tracking-wider text-pink-500 mb-3 flex items-center gap-1.5">
            <iconify-icon icon="heroicons-outline:clipboard-document-list" class="text-base"></iconify-icon>
            Tasks
        </div>
        <div id="panel-tasks"><div class="text-center py-6 text-gray-400 text-[13px]">Loading...</div></div>
        <a href="{{ route('marketing.dashboard') }}" class="text-xs font-semibold text-indigo-500 hover:underline mt-2 inline-block">View Task Board →</a>
    </div>

    {{-- Content Pipeline — Upcoming Posts --}}
    <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#333] p-4 min-h-[280px]">
        <div class="text-xs font-bold uppercase tracking-wider text-indigo-500 mb-3 flex items-center gap-1.5">
            <iconify-icon icon="heroicons-outline:calendar-days" class="text-base"></iconify-icon>
            Upcoming Content
        </div>
        <div id="panel-content"><div class="text-center py-6 text-gray-400 text-[13px]">Loading...</div></div>
        <a href="{{ route('marketing.planner.calendar') }}" class="text-xs font-semibold text-indigo-500 hover:underline mt-2 inline-block">View Content Planner →</a>
    </div>

    {{-- Top Performing --}}
    <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#333] p-4 min-h-[280px]">
        <div class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-3 flex items-center gap-1.5">
            <iconify-icon icon="heroicons-outline:arrow-trending-up" class="text-base"></iconify-icon>
            Top Performing
        </div>
        <div id="panel-top"><div class="text-center py-6 text-gray-400 text-[13px]">Loading...</div></div>
        <a href="{{ route('marketing.analytics.index') }}" class="text-xs font-semibold text-indigo-500 hover:underline mt-2 inline-block">View Full Analytics →</a>
    </div>
</div>

{{-- Quick Actions --}}
<div class="flex gap-2.5 flex-wrap">
    <a href="{{ route('marketing.dashboard') }}" class="inline-flex items-center gap-1.5 px-4 h-[30px] rounded-md text-xs font-semibold border border-slate-200 dark:border-[#333] bg-white dark:bg-[#1E1E1E] text-gray-700 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-500 transition-all no-underline">
        <iconify-icon icon="heroicons-outline:plus" class="text-base"></iconify-icon> Create Task
    </a>
    <a href="{{ route('marketing.planner.calendar') }}" class="inline-flex items-center gap-1.5 px-4 h-[30px] rounded-md text-xs font-semibold border border-slate-200 dark:border-[#333] bg-white dark:bg-[#1E1E1E] text-gray-700 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-500 transition-all no-underline">
        <iconify-icon icon="heroicons-outline:calendar" class="text-base"></iconify-icon> Schedule Post
    </a>
    <a href="{{ route('marketing.planner.grid') }}" class="inline-flex items-center gap-1.5 px-4 h-[30px] rounded-md text-xs font-semibold border border-slate-200 dark:border-[#333] bg-white dark:bg-[#1E1E1E] text-gray-700 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-500 transition-all no-underline">
        <iconify-icon icon="heroicons-outline:signal" class="text-base"></iconify-icon> Feed Preview
    </a>
    <a href="{{ route('marketing.analytics.index') }}" class="inline-flex items-center gap-1.5 px-4 h-[30px] rounded-md text-xs font-semibold border border-slate-200 dark:border-[#333] bg-white dark:bg-[#1E1E1E] text-gray-700 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-500 transition-all no-underline">
        <iconify-icon icon="heroicons-outline:chart-bar" class="text-base"></iconify-icon> Full Analytics
    </a>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    const from28 = '{{ now()->subDays(28)->format("Y-m-d") }}';
    const from14 = '{{ now()->subDays(14)->format("Y-m-d") }}';
    const today = '{{ now()->format("Y-m-d") }}';
    const yesterday = '{{ now()->subDay()->format("Y-m-d") }}';

    const platformColors = {
        facebook: '#1877F2', instagram: '#E4405F', tiktok: '#010101',
        ads: '#F59E0B', multi: '#6366f1'
    };
    const platformIcons = {
        facebook: 'mdi:facebook', instagram: 'mdi:instagram', tiktok: 'ic:baseline-tiktok'
    };

    function esc(str) { return $('<span>').text(str || '').html(); }
    function fmtNum(n) {
        if (n === null || n === undefined) return '—';
        if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n/1000).toFixed(1) + 'k';
        return n.toLocaleString('en-US');
    }
    function fmtMoney(n) {
        if (!n) return '$0';
        return '$' + parseFloat(n).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }
    function changeBadge(change) {
        if (change === null || change === undefined || change === 'no_data') return '';
        const val = parseFloat(change);
        if (isNaN(val)) return '';
        const cls = val > 0 ? 'bg-emerald-100 text-emerald-600' : (val < 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400');
        const arrow = val > 0 ? '↑' : (val < 0 ? '↓' : '→');
        return `<span class="text-[11px] font-semibold px-1.5 py-0.5 rounded-md ${cls}">${arrow} ${Math.abs(val).toFixed(1)}%</span>`;
    }

    // ── KPIs ──
    const kpiUrl = '{{ route("marketing.analytics.api.total-kpis") }}';
    $.getJSON(kpiUrl, { from: from28, to: yesterday }, function(res) {
        // Define which KPIs to show in the strip
        const kpiDefs = [
            { key: 'ads_spend', label: 'Ad Spend', fmt: fmtMoney },
            { key: 'total_reach', label: 'Total Reach', fmt: fmtNum },
            { key: 'roas', label: 'ROAS', fmt: v => v ? v.toFixed(2) + 'x' : '—' },
            { key: 'total_engagement', label: 'Engagement', fmt: fmtNum },
            { key: 'combined_link_clicks', label: 'Link Clicks', fmt: fmtNum },
            { key: 'total_impressions', label: 'Impressions', fmt: fmtNum },
        ];

        let html = '';
        kpiDefs.forEach(def => {
            const kpi = res[def.key];
            if (!kpi) return;
            html += `<div class="bg-white dark:bg-[#1E1E1E] border border-slate-200 dark:border-[#333] rounded-xl px-4 py-3.5 hover:shadow-sm transition-shadow">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">${esc(def.label)}</div>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-[22px] font-bold text-gray-900 dark:text-gray-50">${def.fmt(kpi.value)}</span>
                    ${changeBadge(kpi.change)}
                </div>
            </div>`;
        });
        $('#kpi-strip').html(html || '<div class="col-span-full text-center py-3 text-gray-400 text-[13px]">No KPI data available.</div>');

        // Channel Reach Row
        const channels = [
            { key: 'fb_reach', name: 'Facebook Reach', color: '#1877F2', icon: 'mdi:facebook' },
            { key: 'ig_reach', name: 'Instagram Reach', color: '#E4405F', icon: 'mdi:instagram' },
        ];
        if (res.tiktok_reach) {
            channels.push({ key: 'tiktok_reach', name: 'TikTok Reach', color: '#010101', icon: 'ic:baseline-tiktok' });
        }

        let chHtml = '';
        channels.forEach(ch => {
            const kpi = res[ch.key];
            if (!kpi) return;
            const changeClass = kpi.change > 0 ? 'text-emerald-600' : (kpi.change < 0 ? 'text-red-600' : 'text-gray-400');
            const changeText = (kpi.change !== null && kpi.change !== 'no_data')
                ? `<span class="text-[10px] font-semibold ${changeClass}">${parseFloat(kpi.change) > 0 ? '↑' : '↓'} ${Math.abs(parseFloat(kpi.change)).toFixed(1)}%</span>`
                : '';
            chHtml += `<div class="flex items-center gap-2.5 bg-white dark:bg-[#1E1E1E] border border-slate-200 dark:border-[#333] rounded-xl px-3.5 py-3 hover:shadow-sm transition-shadow">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:${ch.color};"><iconify-icon icon="${ch.icon}" class="text-white text-lg"></iconify-icon></div>
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase">${esc(ch.name)}</div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-base font-bold text-gray-900 dark:text-gray-50">${fmtNum(kpi.value)}</span>
                        ${changeText}
                    </div>
                </div>
            </div>`;
        });
        if (chHtml) {
            $('#channel-row').html(chHtml).removeClass('hidden');
        }
    }).fail(function() {
        $('#kpi-strip').html('<div class="col-span-full text-center py-3 text-gray-400 text-[13px]">Could not load KPIs.</div>');
    });

    // ── Tasks Summary ──
    const taskUrl = '{{ '#' }}';
    $.getJSON(taskUrl, { from: from28, to: today }, function(res) {
        const done = (res.done || []).length;
        const inProgress = (res.in_progress || []).length;
        const next = (res.next || []).length;
        const suggestions = (res.suggestions || []).length;

        let html = '';
        html += `<div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-[#2A2A2A]"><span class="text-[13px] text-gray-700 dark:text-gray-400">In Progress</span><span class="text-sm font-semibold text-gray-900 dark:text-gray-50">${inProgress}</span></div>`;
        html += `<div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-[#2A2A2A]"><span class="text-[13px] text-gray-700 dark:text-gray-400">Completed (28d)</span><span class="text-sm font-semibold text-emerald-600">${done}</span></div>`;
        html += `<div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-[#2A2A2A] last:border-b-0"><span class="text-[13px] text-gray-700 dark:text-gray-400">Up Next</span><span class="text-sm font-semibold text-gray-900 dark:text-gray-50">${next}</span></div>`;
        if (suggestions > 0) {
            html += `<div class="flex items-center justify-between py-2"><span class="text-[13px] text-gray-700 dark:text-gray-400">Suggestions</span><span class="text-sm font-semibold text-amber-500">${suggestions}</span></div>`;
        }
        $('#panel-tasks').html(html);
    }).fail(function() {
        $('#panel-tasks').html('<div class="text-center py-6 text-gray-400 text-[13px]">Could not load tasks.</div>');
    });

    // ── Upcoming Content (next 5 scheduled posts) ──
    const feedUrl = '{{ route("marketing.planner.api.posts.feed") }}';
    $.getJSON(feedUrl, {
        from: today,
        to: '{{ now()->addDays(30)->format("Y-m-d") }}',
    }, function(res) {
        const items = (res.items || []).filter(i => i.type === 'planned');
        if (!items.length) {
            $('#panel-content').html('<div class="text-center py-6 text-gray-400 text-[13px]">No upcoming content scheduled.</div>');
            return;
        }

        let html = '';
        items.slice(0, 5).forEach(function(p) {
            const title = (p.content || 'Untitled post').substring(0, 50);
            const platform = p.platform || 'multi';
            const pColor = platformColors[platform] || '#6366f1';
            const time = p.scheduled_at ? new Date(p.scheduled_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : '—';
            const statusLabel = p.status_label || p.status || '';
            const thumb = p.thumbnail
                ? `<img src="${esc(p.thumbnail)}" class="w-9 h-9 rounded-md object-cover bg-gray-100 shrink-0" alt="" onerror="this.style.display='none'">`
                : `<div class="w-9 h-9 rounded-md bg-gray-100 shrink-0 flex items-center justify-center"><iconify-icon icon="heroicons-outline:document-text" class="text-gray-300 text-base"></iconify-icon></div>`;

            html += `<div class="flex items-center gap-2.5 py-2 border-b border-gray-100 dark:border-[#2A2A2A] last:border-b-0">
                ${thumb}
                <div class="flex-1 min-w-0">
                    <div class="text-[13px] font-semibold text-gray-900 dark:text-gray-50 truncate">${esc(title)}</div>
                    <div class="text-[11px] text-gray-400 mt-px flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full inline-block" style="background:${pColor};"></span>
                        ${esc(platform.charAt(0).toUpperCase() + platform.slice(1))} · ${esc(time)} · <span style="color:${p.status_color || '#9CA3AF'};font-weight:600;">${esc(statusLabel)}</span>
                    </div>
                </div>
            </div>`;
        });
        $('#panel-content').html(html);
    }).fail(function() {
        $('#panel-content').html('<div class="text-center py-6 text-gray-400 text-[13px]">Could not load content pipeline.</div>');
    });

    // ── Top Performing (All Platforms) ──
    let topPosts = [];
    let loadedSources = 0;
    const totalSources = 3;

    function renderTopPosts() {
        loadedSources++;
        if (loadedSources < totalSources) return;

        // Sort by engagement descending
        topPosts.sort((a, b) => b.engagement - a.engagement);
        const top5 = topPosts.slice(0, 5);

        if (!top5.length) {
            $('#panel-top').html('<div class="text-center py-6 text-gray-400 text-[13px]">No top posts data.</div>');
            return;
        }

        let html = '';
        top5.forEach(function(p) {
            const pColor = platformColors[p.platform] || '#6B7280';
            const pIcon = platformIcons[p.platform] || 'heroicons-outline:globe-alt';
            const thumb = p.media_url
                ? `<img src="${esc(p.media_url)}" class="w-10 h-10 rounded-md object-cover bg-gray-100 shrink-0" alt="" onerror="this.style.display='none'">`
                : `<div class="w-10 h-10 rounded-md bg-gray-100 shrink-0 flex items-center justify-center"><iconify-icon icon="${pIcon}" style="color:${pColor};font-size:18px;"></iconify-icon></div>`;

            html += `<div class="flex items-center gap-2.5 py-2 border-b border-gray-100 dark:border-[#2A2A2A] last:border-b-0">
                ${thumb}
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-gray-700 dark:text-gray-300 truncate">${esc(p.title)}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5 flex gap-2.5">
                        <span>${fmtNum(p.engagement)} eng.</span>
                        <span>${fmtNum(p.reach)} reach</span>
                    </div>
                </div>
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded text-white shrink-0" style="background:${pColor};">
                    <iconify-icon icon="${pIcon}" style="font-size:11px;"></iconify-icon>
                </span>
            </div>`;
        });
        $('#panel-top').html(html);
    }

    // Fetch IG top posts
    const igUrl = '{{ route("marketing.analytics.api.ig-top-posts") }}';
    $.getJSON(igUrl, { from: from14, to: yesterday, limit: 5 }, function(posts) {
        (Array.isArray(posts) ? posts : []).forEach(p => {
            topPosts.push({
                platform: 'instagram',
                title: (p.message || p.caption || 'Post').substring(0, 60),
                media_url: p.media_url || null,
                engagement: (p.likes||0) + (p.comments||0) + (p.shares||0) + (p.saves||0),
                reach: p.reach || 0,
            });
        });
        renderTopPosts();
    }).fail(function() { renderTopPosts(); });

    // Fetch FB top posts
    const fbUrl = '{{ route("marketing.analytics.api.fb-top-posts") }}';
    $.getJSON(fbUrl, { from: from14, to: yesterday, limit: 5 }, function(posts) {
        (Array.isArray(posts) ? posts : []).forEach(p => {
            topPosts.push({
                platform: 'facebook',
                title: (p.message || 'Post').substring(0, 60),
                media_url: p.media_url || null,
                engagement: (p.likes||0) + (p.comments||0) + (p.shares||0) + (p.saves||0),
                reach: p.reach || 0,
            });
        });
        renderTopPosts();
    }).fail(function() { renderTopPosts(); });

    // Fetch TikTok top videos
    @if(config('tiktok.features.tiktok_module', false))
    const ttUrl = '{{ route("marketing.analytics.api.tiktok-top-videos") }}';
    $.getJSON(ttUrl, { from: from14, to: yesterday, limit: 5 }, function(videos) {
        (Array.isArray(videos) ? videos : []).forEach(v => {
            topPosts.push({
                platform: 'tiktok',
                title: (v.title || 'Video').substring(0, 60),
                media_url: v.cover_image_url || null,
                engagement: (v.like_count||0) + (v.comment_count||0) + (v.share_count||0),
                reach: v.view_count || 0,
            });
        });
        renderTopPosts();
    }).fail(function() { renderTopPosts(); });
    @else
    renderTopPosts(); // Skip TikTok, count as loaded
    @endif
});
</script>
@endsection
