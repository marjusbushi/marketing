{{-- Shared report-sub-page tabs. Included at the top of each
     influencer-reports/* view so switching between the four
     reports is a single click, not a sidebar round-trip. --}}
<nav class="flex items-center gap-1 flex-wrap mb-4 pb-2 border-b border-slate-200 dark:border-slate-700">
    @php
        $tabs = [
            ['route' => 'marketing.influencer-reports.dashboard',         'icon' => 'heroicons-outline:chart-pie',            'label' => 'Dashboard'],
            ['route' => 'marketing.influencer-reports.overdue',           'icon' => 'heroicons-outline:exclamation-circle',   'label' => 'Te vonuar'],
            ['route' => 'marketing.influencer-reports.value-by-influencer','icon' => 'heroicons-outline:banknotes',            'label' => 'Vlera sipas influencerit'],
            ['route' => 'marketing.influencer-reports.monthly',           'icon' => 'heroicons-outline:calendar-days',        'label' => 'Aktiviteti mujor'],
        ];
    @endphp

    @foreach($tabs as $tab)
        @php $active = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}"
           class="inline-flex items-center gap-1.5 px-3 h-[30px] rounded-md text-xs font-medium no-underline transition-all duration-150 {{ $active
                ? 'bg-blue-600 text-white border border-blue-600'
                : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:border-blue-500 hover:text-blue-600' }}">
            <iconify-icon icon="{{ $tab['icon'] }}" width="14"></iconify-icon>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
