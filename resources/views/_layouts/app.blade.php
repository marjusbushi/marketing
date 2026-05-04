<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Flare' }} — Zero Absolute</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')

    {{-- Studio chrome — override sidebar CSS variables so the shared
         sidebar component renders dark when the Studio page is active,
         without forking the markup. --}}
    @if(request()->routeIs('marketing.studio.*'))
        <style>
            body { background: #09090b !important; color: #e4e4e7 !important; }
            #sidebar {
                --tw-bg-opacity: 1;
                background: #18181b !important;
                border-color: #27272a !important;
            }
            #sidebar, #sidebar * { color-scheme: dark; }
            .bg-sidebar { background: #18181b !important; }
            .bg-sidebar-active { background: rgba(139, 92, 246, 0.2) !important; }
            .bg-sidebar-hover, .hover\:bg-sidebar-hover:hover { background: #27272a !important; }
            .text-sidebar-text { color: #a1a1aa !important; }
            .text-sidebar-text-active,
            .hover\:text-sidebar-text-active:hover { color: #f4f4f5 !important; }
            aside#sidebar a .text-slate-900 { color: #f4f4f5 !important; }
        </style>
    @endif
</head>
    @php
        // Studio is a creative tool (like Figma/Canva/CapCut) — dark chrome
        // everywhere on that page so the white UI can't pull the eye away
        // from the media being worked on.
        $studioChrome = request()->routeIs('marketing.studio.*');
    @endphp
<body class="h-full font-sans antialiased {{ $studioChrome ? 'bg-zinc-950 text-zinc-100' : 'bg-slate-50 text-slate-900' }}">

    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 overflow-y-auto flex flex-col transition-all duration-200 w-64 {{ $studioChrome ? 'bg-zinc-900 border-r border-zinc-800' : 'bg-sidebar border-r border-slate-200' }}" data-expanded="true">
        {{-- Brand → App Switcher (klik për të kaluar në apps të tjera) --}}
        @include('_partials.app-switcher')

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 space-y-0.5">
            <a href="{{ route('marketing.analytics.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ request()->routeIs('marketing.dashboard') || request()->routeIs('marketing.analytics.*') ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Dashboard">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                <span class="sidebar-label">Dashboard</span>
            </a>

            {{-- Order follows the production flow: Merch Calendar (strategy)
                 -> Shporta Ditore (daily execution) -> Content (publishing). --}}
            <a href="{{ route('marketing.merch-calendar.calendar') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ request()->routeIs('marketing.merch-calendar.*') ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Merch Calendar">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                <span class="sidebar-label">Merch Calendar</span>
            </a>

            <a href="{{ route('marketing.daily-basket.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ request()->routeIs('marketing.daily-basket.*') ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Shporta Ditore">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                <span class="sidebar-label">Shporta Ditore</span>
            </a>

            {{-- Visual Studio, Brand Kit, Meta Auth, TikTok Auth jane levizur
                 ne Settings hub (klik ne user profile box ne fund). --}}

            <a href="{{ route('marketing.planner.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ (request()->routeIs('marketing.planner.*') && !request()->routeIs('marketing.planner.media')) ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Content Planner">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                <span class="sidebar-label">Content Planner</span>
            </a>

            <a href="{{ route('marketing.planner.media') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ request()->routeIs('marketing.planner.media') ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Media Library">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                <span class="sidebar-label">Media</span>
            </a>

            {{-- ───────────── Influencer section ─────────────
                 Three related pages (Influencers / Products /
                 Reports) live under one conceptual module. Grouped
                 with a section header + indented "Reports" children
                 that auto-expand on active route so navigation is
                 never more than one click away. --}}
            @php
                $isInfluencerSection = request()->routeIs('marketing.influencers.*')
                    || request()->routeIs('marketing.influencer-products.*')
                    || request()->routeIs('marketing.influencer-reports.*');
                $isReportsActive = request()->routeIs('marketing.influencer-reports.*');
            @endphp

            <div class="pt-3 pb-1 px-3 sidebar-label">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Influencer</div>
            </div>

            <a href="{{ route('marketing.influencers.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ request()->routeIs('marketing.influencers.*') ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Influencers">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                <span class="sidebar-label">Lista</span>
            </a>

            <a href="{{ route('marketing.influencer-products.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ request()->routeIs('marketing.influencer-products.*') ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Produkte te dhena influencerave">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                <span class="sidebar-label">Produkte</span>
            </a>

            <a href="{{ route('marketing.influencer-reports.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {{ $isReportsActive ? 'bg-sidebar-active text-sidebar-text-active font-medium' : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-active' }}" title="Raporte Influencer">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                <span class="sidebar-label">Raporte</span>
            </a>

            {{-- Reports sub-items — auto-expand when any report page is active. --}}
            @if($isReportsActive)
                <div class="sidebar-label ml-6 pl-3 border-l border-slate-200 space-y-0.5 mt-0.5 mb-1">
                    <a href="{{ route('marketing.influencer-reports.dashboard') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-[12px] transition-all duration-150 {{ request()->routeIs('marketing.influencer-reports.dashboard') ? 'text-sidebar-text-active font-medium bg-sidebar-hover' : 'text-sidebar-text hover:text-sidebar-text-active hover:bg-sidebar-hover' }}">
                        <iconify-icon icon="heroicons-outline:chart-pie" width="14"></iconify-icon>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('marketing.influencer-reports.overdue') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-[12px] transition-all duration-150 {{ request()->routeIs('marketing.influencer-reports.overdue') ? 'text-sidebar-text-active font-medium bg-sidebar-hover' : 'text-sidebar-text hover:text-sidebar-text-active hover:bg-sidebar-hover' }}">
                        <iconify-icon icon="heroicons-outline:exclamation-circle" width="14"></iconify-icon>
                        <span>Te vonuar</span>
                    </a>
                    <a href="{{ route('marketing.influencer-reports.value-by-influencer') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-[12px] transition-all duration-150 {{ request()->routeIs('marketing.influencer-reports.value-by-influencer') ? 'text-sidebar-text-active font-medium bg-sidebar-hover' : 'text-sidebar-text hover:text-sidebar-text-active hover:bg-sidebar-hover' }}">
                        <iconify-icon icon="heroicons-outline:banknotes" width="14"></iconify-icon>
                        <span>Vlera sipas influencerit</span>
                    </a>
                    <a href="{{ route('marketing.influencer-reports.monthly') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-[12px] transition-all duration-150 {{ request()->routeIs('marketing.influencer-reports.monthly') ? 'text-sidebar-text-active font-medium bg-sidebar-hover' : 'text-sidebar-text hover:text-sidebar-text-active hover:bg-sidebar-hover' }}">
                        <iconify-icon icon="heroicons-outline:calendar-days" width="14"></iconify-icon>
                        <span>Aktiviteti mujor</span>
                    </a>
                </div>
            @endif

            {{-- ───────────── end Influencer section ───────────── --}}
        </nav>

        {{-- User footer me dropdown — Settings + Dil --}}
        <div class="border-t border-slate-100 p-3 relative" id="userMenuWrap">
            <button type="button"
                    id="userMenuTrigger"
                    onclick="document.getElementById('userMenuPanel').classList.toggle('hidden')"
                    class="w-full flex items-center gap-3 p-1.5 rounded-lg hover:bg-sidebar-hover transition-colors text-left"
                    title="Klik per Settings ose Dil">
                <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center text-xs font-semibold shrink-0">
                    {{ auth()->user()->initials }}
                </div>
                <div class="sidebar-label min-w-0 flex-1 overflow-hidden">
                    <div class="text-sm font-medium text-slate-900 truncate">{{ auth()->user()->full_name }}</div>
                    <div class="text-[11px] text-sidebar-text truncate">{{ auth()->user()->email }}</div>
                </div>
                <svg class="w-3.5 h-3.5 text-sidebar-text sidebar-label shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                </svg>
            </button>

            <div id="userMenuPanel" class="hidden absolute bottom-full left-3 right-3 mb-2 bg-white rounded-xl border border-slate-200 shadow-xl py-1.5 z-50">
                <a href="{{ route('marketing.settings.index') }}"
                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Settings
                </a>
                <div class="my-1 border-t border-slate-100"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                        Dil
                    </button>
                </form>
            </div>
        </div>

    </aside>

    {{-- Main area --}}
    <div id="main-area" class="pl-64 min-h-full transition-all duration-200">
        {{-- Header --}}
        <header class="sticky top-0 z-40 h-14 bg-white/80 backdrop-blur-md border-b border-slate-200/60 flex items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle"
                        type="button"
                        aria-label="Toggle sidebar"
                        title="Toggle sidebar (⌘/Ctrl+B)"
                        class="w-8 h-8 rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors duration-150 cursor-pointer shrink-0">
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <h1 class="text-[15px] font-semibold text-slate-900">{{ $pageTitle ?? $title ?? 'Marketing' }}</h1>

                {{-- View switcher dropdown (only on Content Planner pages) --}}
                @if(request()->routeIs('marketing.planner.*'))
                <div class="relative" id="viewSwitcherWrap">
                    @php
                        // Default landing at /planner/ (name: index) serves the grid view.
                        $currentView = request()->routeIs('marketing.planner.calendar') ? 'Calendar'
                            : (request()->routeIs('marketing.planner.list') ? 'List'
                            : (request()->routeIs('marketing.planner.media') ? 'Media'
                            : (request()->routeIs('marketing.planner.grid') || request()->routeIs('marketing.planner.index') ? 'Grid'
                            : 'Grid')));
                        $viewIcons = ['Calendar' => 'heroicons-outline:calendar-days', 'List' => 'heroicons-outline:list-bullet', 'Grid' => 'heroicons-outline:squares-2x2', 'Media' => 'heroicons-outline:photo'];
                    @endphp
                    <button onclick="document.getElementById('viewSwitcherPanel').classList.toggle('hidden')"
                            class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                        <iconify-icon icon="{{ $viewIcons[$currentView] }}" width="15"></iconify-icon>
                        {{ $currentView }}
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div id="viewSwitcherPanel" class="hidden absolute top-full left-0 mt-1 w-[180px] bg-white rounded-xl border border-slate-200 shadow-xl z-50 py-1.5">
                        <a href="{{ route('marketing.planner.calendar') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm {{ $currentView === 'Calendar' ? 'text-primary-600 font-semibold bg-primary-50/50' : 'text-slate-700 hover:bg-slate-50' }} transition-colors">
                            <iconify-icon icon="heroicons-outline:calendar-days" width="16"></iconify-icon> Calendar
                        </a>
                        <a href="{{ route('marketing.planner.grid') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm {{ $currentView === 'Grid' ? 'text-primary-600 font-semibold bg-primary-50/50' : 'text-slate-700 hover:bg-slate-50' }} transition-colors">
                            <iconify-icon icon="heroicons-outline:squares-2x2" width="16"></iconify-icon> Feed
                        </a>
                        <a href="{{ route('marketing.planner.list') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm {{ $currentView === 'List' ? 'text-primary-600 font-semibold bg-primary-50/50' : 'text-slate-700 hover:bg-slate-50' }} transition-colors">
                            <iconify-icon icon="heroicons-outline:list-bullet" width="16"></iconify-icon> List
                        </a>
                        <a href="{{ route('marketing.planner.media') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm {{ $currentView === 'Media' ? 'text-primary-600 font-semibold bg-primary-50/50' : 'text-slate-700 hover:bg-slate-50' }} transition-colors">
                            <iconify-icon icon="heroicons-outline:photo" width="16"></iconify-icon> Media
                        </a>
                    </div>
                </div>
                @endif
            </div>
            <div class="flex items-center gap-4">
                @yield('header-actions')
            </div>
        </header>

        {{-- Page content --}}
        <main class="p-6">
            @yield('content')
        </main>
    </div>

    {{-- Scripts --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>

    {{-- Surface JS errors as toastr so blank-UI bugs stop being invisible.
         Also sets Select2 defaults to Albanian — used by every Select2
         instance in the app so we don't repeat ourselves per page. --}}
    <script>
        window.addEventListener('error', function (e) {
            if (typeof toastr === 'undefined') return;
            const file = (e.filename || '').split('/').pop();
            toastr.error(`${e.message}${file ? ' (' + file + ':' + e.lineno + ')' : ''}`, 'JS error', { timeOut: 8000 });
        });
        window.addEventListener('unhandledrejection', function (e) {
            if (typeof toastr === 'undefined') return;
            const msg = (e.reason && e.reason.message) || String(e.reason || 'unknown');
            toastr.error(msg, 'Async error', { timeOut: 8000 });
        });

        if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
            $.fn.select2.defaults.set('language', {
                inputTooShort:  function (a) { return 'Shkruaj ' + (a.minimum - a.input.length) + ' karaktere më shumë'; },
                inputTooLong:   function (a) { return 'Fshij ' + (a.input.length - a.maximum) + ' karaktere'; },
                searching:      function () { return 'Duke kërkuar...'; },
                noResults:      function () { return 'Asnjë rezultat'; },
                loadingMore:    function () { return 'Duke ngarkuar më shumë...'; },
                errorLoading:   function () { return 'Nuk mund të ngarkohen rezultatet'; },
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@2.1.0/dist/iconify-icon.min.js"></script>

    <script>
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
        toastr.options = { positionClass: 'toast-top-right', timeOut: 3000, closeButton: true, progressBar: true };
        @if(session('success')) toastr.success('{{ session('success') }}'); @endif
        @if(session('error')) toastr.error('{{ session('error') }}'); @endif

        // View switcher + User menu — close on outside click
        document.addEventListener('click', function(e) {
            const wrap = document.getElementById('viewSwitcherWrap');
            const panel = document.getElementById('viewSwitcherPanel');
            if (wrap && panel && !wrap.contains(e.target)) panel.classList.add('hidden');

            const userWrap = document.getElementById('userMenuWrap');
            const userPanel = document.getElementById('userMenuPanel');
            if (userWrap && userPanel && !userWrap.contains(e.target)) userPanel.classList.add('hidden');
        });

        // Sidebar collapse/expand
        (function() {
            const sidebar = document.getElementById('sidebar');
            const mainArea = document.getElementById('main-area');
            const toggleBtn = document.getElementById('sidebar-toggle');
            const labels = sidebar.querySelectorAll('.sidebar-label');

            // Restore from localStorage
            const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            if (collapsed) collapse(false);

            toggleBtn.addEventListener('click', function() {
                const isExpanded = sidebar.dataset.expanded === 'true';
                if (isExpanded) collapse(true); else expand(true);
            });

            // Keyboard shortcut: Cmd/Ctrl+B toggles the sidebar.
            document.addEventListener('keydown', function(e) {
                if ((e.metaKey || e.ctrlKey) && (e.key === 'b' || e.key === 'B')) {
                    const tag = (e.target.tagName || '').toLowerCase();
                    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
                    e.preventDefault();
                    toggleBtn.click();
                }
            });

            function collapse(animate) {
                sidebar.dataset.expanded = 'false';
                sidebar.classList.remove('w-64');
                sidebar.classList.add('w-[68px]');
                mainArea.classList.remove('pl-64');
                mainArea.classList.add('pl-[68px]');
                labels.forEach(el => {
                    el.style.opacity = '0';
                    el.style.width = '0';
                    el.style.overflow = 'hidden';
                });
                localStorage.setItem('sidebar_collapsed', 'true');
                notifyWidthChange(animate);
            }

            function expand(animate) {
                sidebar.dataset.expanded = 'true';
                sidebar.classList.remove('w-[68px]');
                sidebar.classList.add('w-64');
                mainArea.classList.remove('pl-[68px]');
                mainArea.classList.add('pl-64');
                labels.forEach(el => {
                    el.style.opacity = '1';
                    el.style.width = '';
                    el.style.overflow = '';
                });
                localStorage.setItem('sidebar_collapsed', 'false');
                notifyWidthChange(animate);
            }

            // After the sidebar transition runs, fire window.resize so
            // DataTables (and any other width-cached widget) recomputes
            // column widths. DataTables natively listens to resize.
            // Also dispatch a custom sidebar:toggled event so a page
            // can hook in directly if the generic resize isn't enough.
            function notifyWidthChange(animate) {
                const fire = () => {
                    window.dispatchEvent(new Event('resize'));
                    window.dispatchEvent(new CustomEvent('sidebar:toggled', {
                        detail: { expanded: sidebar.dataset.expanded === 'true' }
                    }));
                };
                // CSS transition is 200ms (Tailwind duration-200); wait
                // just past that so the final layout is settled.
                if (animate) setTimeout(fire, 220); else fire();
            }
        })();
    </script>

    @yield('scripts')
    @stack('partial-scripts')
    @stack('scripts')
</body>
</html>
