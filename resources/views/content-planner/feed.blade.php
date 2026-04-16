@extends('_layouts.app', [
    'title' => 'Content Planner — Feed',
    'container_class' => 'container-fluid zoho-page'
])

@section('styles')
    {{-- TODO: port report-styles partial --}}
    <style>
        .feed-wrap { max-width: 560px; margin: 0 auto; padding: 0 16px 60px; }

        /* Platform filter tabs */
        .feed-platform-tabs { display: flex; gap: 6px; margin-bottom: 20px; }
        .feed-platform-tab {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; font-size: 12px; font-weight: 500;
            border: 1px solid #E5E7EB; border-radius: 20px;
            background: #fff; color: #6B7280; cursor: pointer;
            transition: all .15s; text-decoration: none;
        }
        .feed-platform-tab:hover { border-color: #9CA3AF; color: #374151; }
        .feed-platform-tab.active { background: #6366f1; color: #fff; border-color: #6366f1; }

        /* NOW divider */
        .feed-now-divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0; padding: 0;
        }
        .feed-now-divider::before, .feed-now-divider::after {
            content: ''; flex: 1; height: 2px; background: #EF4444;
        }
        .feed-now-label {
            font-size: 11px; font-weight: 700; color: #EF4444;
            text-transform: uppercase; letter-spacing: 1px;
            padding: 3px 10px; background: #FEF2F2; border-radius: 10px;
        }

        /* Feed card base */
        .feed-card {
            background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
            margin-bottom: 16px; overflow: hidden; transition: box-shadow .15s;
        }
        .feed-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }

        /* Planned / future cards */
        .feed-card.is-future {
            border-style: dashed; border-color: #D1D5DB; opacity: .85;
        }
        .feed-card.is-future .feed-card-media { position: relative; }
        .feed-card.is-future .feed-card-media::after {
            content: ''; position: absolute; inset: 0;
            background: rgba(255,255,255,.35);
        }

        /* Card header */
        .feed-card-header {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 14px; border-bottom: 1px solid #F3F4F6;
        }
        .feed-card-platform-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .feed-card-platform-icon iconify-icon { color: #fff; }
        .feed-card-header-info { flex: 1; min-width: 0; }
        .feed-card-platform-name { font-size: 13px; font-weight: 600; color: #111827; }
        .feed-card-time { font-size: 11px; color: #9CA3AF; }

        .feed-card-status-chip {
            font-size: 10px; font-weight: 600; padding: 2px 8px;
            border-radius: 10px; white-space: nowrap;
        }

        /* Card media */
        .feed-card-media { position: relative; background: #000; }
        .feed-card-media img, .feed-card-media video { width: 100%; display: block; max-height: 500px; object-fit: contain; }
        .feed-card-no-media { padding: 0; }

        /* Card content */
        .feed-card-content {
            padding: 12px 14px; font-size: 13px; line-height: 1.55;
            color: #374151; white-space: pre-wrap; word-break: break-word;
        }
        .feed-card-content.truncated { max-height: 100px; overflow: hidden; position: relative; }
        .feed-card-content.truncated::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 40px; background: linear-gradient(transparent, #fff);
        }

        /* Card footer — metrics */
        .feed-card-footer {
            display: flex; align-items: center; gap: 16px;
            padding: 8px 14px; border-top: 1px solid #F3F4F6;
            font-size: 11px; color: #6B7280;
        }
        .feed-metric { display: inline-flex; align-items: center; gap: 4px; }
        .feed-metric iconify-icon { font-size: 13px; color: #9CA3AF; }
        .feed-metric-value { font-weight: 600; color: #374151; }

        /* Card labels */
        .feed-card-labels { display: flex; gap: 4px; flex-wrap: wrap; padding: 0 14px 10px; }
        .feed-label-chip {
            font-size: 10px; font-weight: 500; padding: 2px 7px;
            border-radius: 4px; color: #fff;
        }

        /* Permalink button */
        .feed-permalink {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; color: #6366f1; text-decoration: none; margin-left: auto;
        }
        .feed-permalink:hover { text-decoration: underline; }

        /* Loading / empty */
        .feed-loading, .feed-empty {
            text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px;
        }
        .feed-loading iconify-icon { font-size: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Scheduled time badge on future cards */
        .feed-scheduled-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; font-weight: 500; color: #7C3AED;
            background: #EDE9FE; padding: 3px 8px; border-radius: 6px;
            margin: 0 14px 10px;
        }
    </style>
@endsection

@section('content')
    @include('marketing._partials.nav')
    @include('content-planner._partials.nav')

    {{-- Breadcrumbs --}}
    <nav style="font-size:12px; color:#9CA3AF; margin-bottom:16px;">
        <a href="{{ route('marketing.dashboard') }}" style="color:#9CA3AF; text-decoration:none;">Home</a>
        <span style="margin:0 4px;">›</span>
        <a href="{{ route('marketing.dashboard') }}" style="color:#9CA3AF; text-decoration:none;">Marketing</a>
        <span style="margin:0 4px;">›</span>
        <a href="{{ route('marketing.planner.calendar') }}" style="color:#9CA3AF; text-decoration:none;">Content Planner</a>
        <span style="margin:0 4px;">›</span>
        <span style="color:#374151; font-weight:500;">Feed</span>
    </nav>

    <div class="feed-wrap">
        {{-- Platform filter tabs --}}
        <div class="feed-platform-tabs">
            <button class="feed-platform-tab active" data-platform="all" onclick="filterPlatform('all', this)">All</button>
            @foreach($platforms as $p)
                <button class="feed-platform-tab" data-platform="{{ $p }}" onclick="filterPlatform('{{ $p }}', this)">
                    <iconify-icon icon="{{ $p === 'facebook' ? 'mdi:facebook' : ($p === 'instagram' ? 'mdi:instagram' : 'ic:baseline-tiktok') }}" width="14"></iconify-icon>
                    {{ ucfirst($p) }}
                </button>
            @endforeach
        </div>

        {{-- Feed container --}}
        <div id="feed-container">
            <div class="feed-loading">
                <iconify-icon icon="heroicons-outline:arrow-path" width="24"></iconify-icon>
                <div style="margin-top:8px;">Loading feed...</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    const API_URL = @json(route('marketing.planner.api.posts.feed'));
    let currentPlatform = 'all';
    let allItems = [];
    let nowISO = '';

    // ── Platform helpers ──
    const platformConfig = {
        facebook:  { color: '#1877F2', bg: '#EBF4FF', icon: 'mdi:facebook',          label: 'Facebook' },
        instagram: { color: '#E4405F', bg: '#FDF2F4', icon: 'mdi:instagram',          label: 'Instagram' },
        tiktok:    { color: '#010101', bg: '#F5F5F5', icon: 'ic:baseline-tiktok',     label: 'TikTok' },
        multi:     { color: '#6366f1', bg: '#EEF2FF', icon: 'heroicons-outline:globe-alt', label: 'Multi' },
    };

    function getPlatform(key) {
        return platformConfig[key] || platformConfig.multi;
    }

    function formatNum(n) {
        if (!n && n !== 0) return '—';
        if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n/1000).toFixed(1) + 'k';
        return n.toString();
    }

    function formatDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const diff = d - now;
        const days = Math.floor(Math.abs(diff) / 86400000);

        const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

        if (days === 0) return 'Today at ' + time;
        if (days === 1 && diff < 0) return 'Yesterday at ' + time;
        if (days === 1 && diff > 0) return 'Tomorrow at ' + time;

        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) + ' at ' + time;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ── Render ──
    function renderFeed() {
        const container = document.getElementById('feed-container');
        const filtered = currentPlatform === 'all'
            ? allItems
            : allItems.filter(i => i.platform === currentPlatform || (i.platform_icons && i.platform_icons.includes(currentPlatform)));

        if (!filtered.length) {
            container.innerHTML = '<div class="feed-empty"><iconify-icon icon="heroicons-outline:inbox" width="32"></iconify-icon><div style="margin-top:8px;">No posts found for this period.</div></div>';
            return;
        }

        let html = '';
        let nowInserted = false;
        const nowDate = new Date(nowISO);

        filtered.forEach(item => {
            const itemDate = new Date(item.sort_date);
            const isFuture = itemDate > nowDate;

            // Insert NOW divider before the first future item
            if (isFuture && !nowInserted) {
                nowInserted = true;
                html += `<div class="feed-now-divider"><span class="feed-now-label">Now</span></div>`;
            }

            html += renderCard(item, isFuture);
        });

        // If no future items, put NOW at the end
        if (!nowInserted) {
            html += `<div class="feed-now-divider"><span class="feed-now-label">Now</span></div>`;
        }

        container.innerHTML = html;
    }

    function renderCard(item, isFuture) {
        const p = getPlatform(item.platform);
        const isExternal = item.type === 'external';
        const isTikTok = item.platform === 'tiktok';

        let cardClass = 'feed-card';
        if (isFuture && !isExternal) cardClass += ' is-future';

        // Header
        let header = `
            <div class="feed-card-header">
                <div class="feed-card-platform-icon" style="background:${p.color};">
                    <iconify-icon icon="${p.icon}" width="16"></iconify-icon>
                </div>
                <div class="feed-card-header-info">
                    <div class="feed-card-platform-name">${p.label}</div>
                    <div class="feed-card-time">${formatDate(item.sort_date)}</div>
                </div>`;

        // Status chip for planned posts
        if (!isExternal && item.status_label) {
            header += `<span class="feed-card-status-chip" style="background:${item.status_color || '#9CA3AF'}20; color:${item.status_color || '#9CA3AF'};">${escapeHtml(item.status_label)}</span>`;
        }
        if (isExternal) {
            header += `<span class="feed-card-status-chip" style="background:${p.bg}; color:${p.color};">Published</span>`;
        }
        header += '</div>';

        // Scheduled badge for future planned posts
        let scheduledBadge = '';
        if (isFuture && !isExternal && item.scheduled_at) {
            scheduledBadge = `<div class="feed-scheduled-badge"><iconify-icon icon="heroicons-outline:clock" width="12"></iconify-icon> Scheduled for ${formatDate(item.scheduled_at)}</div>`;
        }

        // Platform icons row for multi-platform posts
        let platformIcons = '';
        if (item.platform_icons && item.platform_icons.length > 1) {
            platformIcons = '<div style="display:flex; gap:4px; padding:6px 14px 0;">';
            item.platform_icons.forEach(pi => {
                const pc = getPlatform(pi);
                platformIcons += `<span style="display:inline-flex; align-items:center; gap:3px; font-size:10px; color:${pc.color}; background:${pc.bg}; padding:2px 6px; border-radius:4px;"><iconify-icon icon="${pc.icon}" width="11"></iconify-icon>${pc.label}</span>`;
            });
            platformIcons += '</div>';
        }

        // Media
        let media = '';
        const mediaUrl = item.first_media_url || item.thumbnail;
        if (mediaUrl && item.is_video) {
            media = `<div class="feed-card-media"><video src="${escapeHtml(mediaUrl)}" muted autoplay loop playsinline onerror="this.parentElement.style.display='none'"></video></div>`;
        } else if (item.thumbnail) {
            media = `<div class="feed-card-media"><img src="${escapeHtml(item.thumbnail)}" alt="" loading="lazy" onerror="this.parentElement.style.display='none'"></div>`;
        }

        // Content
        let content = '';
        const text = item.content || '';
        if (text) {
            const truncate = text.length > 300;
            content = `<div class="feed-card-content${truncate ? ' truncated' : ''}">${escapeHtml(text)}</div>`;
        }

        // Labels (planned posts only)
        let labels = '';
        if (item.labels && item.labels.length) {
            labels = '<div class="feed-card-labels">';
            item.labels.forEach(l => {
                labels += `<span class="feed-label-chip" style="background:${escapeHtml(l.color || '#6B7280')}">${escapeHtml(l.name)}</span>`;
            });
            labels += '</div>';
        }

        // Footer — metrics
        let footer = '<div class="feed-card-footer">';
        if (isExternal && item.metrics) {
            if (isTikTok) {
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:eye"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.view_count)}</span></span>`;
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:heart"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.like_count)}</span></span>`;
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:chat-bubble-left"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.comment_count)}</span></span>`;
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:arrow-uturn-right"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.share_count)}</span></span>`;
            } else {
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:signal"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.reach)}</span> reach</span>`;
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:heart"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.likes)}</span></span>`;
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:chat-bubble-left"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.comments)}</span></span>`;
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:arrow-uturn-right"></iconify-icon><span class="feed-metric-value">${formatNum(item.metrics.shares)}</span></span>`;
            }
            if (item.metrics.engagement_rate) {
                footer += `<span class="feed-metric" style="margin-left:auto;"><span class="feed-metric-value" style="color:#059669;">${parseFloat(item.metrics.engagement_rate).toFixed(1)}%</span> eng.</span>`;
            }
        } else if (!isExternal) {
            // Planned post — show user + labels summary
            if (item.user_name) {
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:user"></iconify-icon> ${escapeHtml(item.user_name)}</span>`;
            }
            if (item.has_media) {
                footer += `<span class="feed-metric"><iconify-icon icon="heroicons-outline:photo"></iconify-icon> Media attached</span>`;
            }
        }

        // Permalink for external
        if (isExternal && item.permalink) {
            footer += `<a href="${escapeHtml(item.permalink)}" target="_blank" rel="noopener" class="feed-permalink"><iconify-icon icon="heroicons-outline:arrow-top-right-on-square" width="12"></iconify-icon> View</a>`;
        }

        footer += '</div>';

        return `<div class="${cardClass}">${header}${platformIcons}${media}${scheduledBadge}${content}${labels}${footer}</div>`;
    }

    // ── Filter ──
    window.filterPlatform = function(platform, btn) {
        currentPlatform = platform;
        document.querySelectorAll('.feed-platform-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        renderFeed();
    };

    // ── Load data ──
    function loadFeed() {
        const params = new URLSearchParams({
            from: new Date(Date.now() - 30*86400000).toISOString().slice(0,10),
            to: new Date(Date.now() + 30*86400000).toISOString().slice(0,10),
        });
        if (currentPlatform !== 'all') params.set('platforms', currentPlatform);

        fetch(API_URL + '?' + params.toString())
            .then(r => r.json())
            .then(data => {
                allItems = data.items || [];
                nowISO = data.now;
                renderFeed();
            })
            .catch(err => {
                console.error('Feed load error:', err);
                document.getElementById('feed-container').innerHTML = '<div class="feed-empty" style="color:#EF4444;">Failed to load feed. Please try again.</div>';
            });
    }

    loadFeed();
})();
</script>
@endpush
