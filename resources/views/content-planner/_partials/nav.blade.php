{{-- Content Planner sub-navigation tabs --}}
<nav style="display:flex; align-items:center; gap:2px; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:0;">
    <a href="{{ route('marketing.planner.calendar') }}"
       style="display:inline-flex; align-items:center; gap:5px; padding:8px 14px; font-size:13px; font-weight:500; border-bottom:2px solid {{ request()->routeIs('marketing.planner.calendar') ? '#6366f1' : 'transparent' }}; color:{{ request()->routeIs('marketing.planner.calendar') ? '#6366f1' : '#6B7280' }}; text-decoration:none; transition:all .15s;"
       onmouseover="if(!this.style.borderBottomColor.includes('99,102'))this.style.color='#374151'"
       onmouseout="if(!this.style.borderBottomColor.includes('99,102'))this.style.color='#6B7280'">
        <iconify-icon icon="heroicons-outline:calendar-days" width="14"></iconify-icon> Calendar
    </a>
    <a href="{{ route('marketing.planner.list') }}"
       style="display:inline-flex; align-items:center; gap:5px; padding:8px 14px; font-size:13px; font-weight:500; border-bottom:2px solid {{ request()->routeIs('marketing.planner.list') ? '#6366f1' : 'transparent' }}; color:{{ request()->routeIs('marketing.planner.list') ? '#6366f1' : '#6B7280' }}; text-decoration:none; transition:all .15s;">
        <iconify-icon icon="heroicons-outline:list-bullet" width="14"></iconify-icon> List
    </a>
    <a href="{{ route('marketing.planner.grid') }}"
       style="display:inline-flex; align-items:center; gap:5px; padding:8px 14px; font-size:13px; font-weight:500; border-bottom:2px solid {{ request()->routeIs('marketing.planner.grid') ? '#6366f1' : 'transparent' }}; color:{{ request()->routeIs('marketing.planner.grid') ? '#6366f1' : '#6B7280' }}; text-decoration:none; transition:all .15s;">
        <iconify-icon icon="heroicons-outline:squares-2x2" width="14"></iconify-icon> Feed Preview
    </a>
    <a href="{{ route('marketing.planner.media') }}"
       style="display:inline-flex; align-items:center; gap:5px; padding:8px 14px; font-size:13px; font-weight:500; border-bottom:2px solid {{ request()->routeIs('marketing.planner.media') ? '#6366f1' : 'transparent' }}; color:{{ request()->routeIs('marketing.planner.media') ? '#6366f1' : '#6B7280' }}; text-decoration:none; transition:all .15s;">
        <iconify-icon icon="heroicons-outline:photo" width="14"></iconify-icon> Media
    </a>
</nav>
