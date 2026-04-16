{{-- Shared sub-navigation for all Marketing module pages --}}
<nav style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:16px;">
    <a href="{{ route('marketing.dashboard') }}"
       class="zoho-btn {{ request()->routeIs('marketing.dashboard') ? 'zoho-btn-primary' : 'zoho-btn-secondary' }} zoho-btn-sm">
        <iconify-icon icon="heroicons-outline:squares-2x2" width="14"></iconify-icon> Overview
    </a>

    <a href="{{ route('marketing.planner.calendar') }}"
       class="zoho-btn {{ request()->routeIs('marketing.planner.*') ? 'zoho-btn-primary' : 'zoho-btn-secondary' }} zoho-btn-sm">
        <iconify-icon icon="heroicons-outline:calendar-days" width="14"></iconify-icon> Content Planner
    </a>

    <a href="{{ route('marketing.analytics.index') }}"
       class="zoho-btn {{ request()->routeIs('marketing.analytics.*') ? 'zoho-btn-primary' : 'zoho-btn-secondary' }} zoho-btn-sm">
        <iconify-icon icon="heroicons-outline:chart-bar-square" width="14"></iconify-icon> Analytics
    </a>

    <a href="{{ route('marketing.influencers.index') }}"
       class="zoho-btn {{ request()->routeIs('marketing.influencers.*') ? 'zoho-btn-primary' : 'zoho-btn-secondary' }} zoho-btn-sm">
        <iconify-icon icon="heroicons-outline:user-group" width="14"></iconify-icon> Influencers
    </a>
</nav>
