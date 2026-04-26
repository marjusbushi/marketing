{{--
    App Switcher për Marketing — replacement i brand block.
    Klik te logo → popup me apps (Marketing si current).
    Vanilla JS, jQuery i disponueshëm. Jo Alpine.
--}}
@php
    $apps = [
        [
            'name'    => 'DIS',
            'tagline' => 'Dynamic Innovative Solution',
            'icon'    => 'mdi:view-dashboard',
            'url'     => config('app.dis_url', 'https://dis.zeroabsolute.dev') . '/management/dashboard',
            'bg'      => 'linear-gradient(135deg,#6366f1,#8b5cf6)',
            'current' => false,
            'shortcut'=> '',
        ],
        [
            'name'    => 'POS',
            'tagline' => 'Pikë shitjeje',
            'icon'    => 'heroicons-outline:computer-desktop',
            'url'     => config('app.pos_url', 'https://pos.zeroabsolute.dev') . '/pos',
            'bg'      => 'linear-gradient(135deg,#10b981,#059669)',
            'current' => false,
            'shortcut'=> '1',
        ],
        [
            'name'    => 'Marketing',
            'tagline' => 'Studio & Kampanja',
            'icon'    => 'mdi:bullhorn',
            'url'     => '#',
            'bg'      => 'linear-gradient(135deg,#ec4899,#f43f5e)',
            'current' => true,
            'shortcut'=> '',
        ],
        [
            'name'    => 'HRMS',
            'tagline' => 'Punonjësit & oraret',
            'icon'    => 'heroicons-outline:user-group',
            'url'     => config('app.hrms_url', 'https://hrms.zeroabsolute.dev'),
            'bg'      => 'linear-gradient(135deg,#f59e0b,#f97316)',
            'current' => false,
            'shortcut'=> '3',
        ],
        [
            'name'    => 'Mail',
            'tagline' => 'Webmail',
            'icon'    => 'heroicons-outline:envelope',
            'url'     => config('app.mail_url', 'https://mail.zeroabsolute.dev'),
            'bg'      => 'linear-gradient(135deg,#3b82f6,#06b6d4)',
            'current' => false,
            'shortcut'=> '4',
        ],
        [
            'name'    => 'Chat',
            'tagline' => 'Mattermost',
            'icon'    => 'mdi:chat',
            'url'     => config('app.chat_url', 'https://chat.zeroabsolute.dev'),
            'bg'      => 'linear-gradient(135deg,#8b5cf6,#6366f1)',
            'current' => false,
            'shortcut'=> '5',
        ],
        [
            'name'    => 'AntTech',
            'tagline' => 'Detyrat & planet',
            'icon'    => 'mdi:clipboard-check-outline',
            'url'     => config('app.anttech_url', 'https://anttech.zeroabsolute.dev'),
            'bg'      => 'linear-gradient(135deg,#1f2937,#4b5563)',
            'current' => false,
            'shortcut'=> '6',
        ],
        [
            'name'    => 'Web',
            'tagline' => 'Faqja publike',
            'icon'    => 'mdi:web',
            'url'     => config('app.web_url', 'https://zeroabsolute.com/web-administration'),
            'bg'      => 'linear-gradient(135deg,#0ea5e9,#06b6d4)',
            'current' => false,
            'shortcut'=> '7',
        ],
    ];
@endphp

{{-- Brand button (replaces old Brand block) --}}
<div class="relative" id="appSwitcherWrap">
    <button type="button"
            id="appSwitcherTrigger"
            class="w-full flex items-center gap-3 px-5 h-14 border-b hover:bg-slate-50 transition-colors text-left {{ $studioChrome ? 'border-zinc-800 hover:bg-zinc-800/40' : 'border-slate-200/60' }}"
            title="Apps (klik për të kaluar)">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-white"
             style="background: linear-gradient(135deg,#ec4899,#f43f5e);">
            <iconify-icon icon="mdi:bullhorn" style="font-size: 18px; color: white;"></iconify-icon>
        </div>
        <div class="sidebar-label overflow-hidden whitespace-nowrap flex-1">
            <div class="text-sm font-bold tracking-tight {{ $studioChrome ? 'text-zinc-100' : 'text-slate-900' }}">Marketing</div>
            <div class="text-[10px] {{ $studioChrome ? 'text-zinc-500' : 'text-sidebar-text' }}">by Zero Absolute</div>
        </div>
        <iconify-icon icon="heroicons-outline:chevron-down" class="sidebar-label shrink-0 transition-transform" id="appSwitcherChevron" style="font-size: 14px; color: #94a3b8;"></iconify-icon>
    </button>
</div>

{{-- Backdrop + dropdown — appended to body via JS to escape stacking context --}}
<template id="appSwitcherTemplate">
    <div id="appSwitcherBackdrop"
         style="position: fixed; inset: 0; z-index: 99998; background: rgba(15,23,42,0.15); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); opacity: 0; transition: opacity 0.2s;"></div>
    <div id="appSwitcherPanel"
         style="position: fixed; top: 70px; left: 16px; width: 340px; z-index: 99999; opacity: 0; transform: translateY(-8px) scale(0.96); transition: all 0.2s cubic-bezier(.4,0,.2,1);"
         class="rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kalo në aplikacion</div>
        </div>
        <div class="p-1.5 max-h-[480px] overflow-y-auto">
            @foreach($apps as $app)
                <a href="{{ $app['url'] }}"
                   @if(! $app['current']) target="_self" rel="noopener" @endif
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ $app['current'] ? 'bg-indigo-50' : 'hover:bg-slate-50' }}"
                   @if($app['current']) onclick="event.preventDefault();" @endif>
                    <div class="rounded-lg flex items-center justify-center text-white flex-shrink-0"
                         style="width: 36px; height: 36px; background: {{ $app['bg'] }}; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <iconify-icon icon="{{ $app['icon'] }}" style="font-size: 18px; color: white;"></iconify-icon>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-slate-900 truncate">{{ $app['name'] }}</div>
                        <div class="text-xs text-slate-500 truncate">{{ $app['tagline'] }}</div>
                    </div>
                    @if($app['current'])
                        <iconify-icon icon="heroicons-outline:check" style="font-size: 16px; color: #6366f1;"></iconify-icon>
                    @elseif($app['shortcut'])
                        <span class="hidden md:inline text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 group-hover:bg-white">⌘{{ $app['shortcut'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="px-4 py-2.5 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-[11px] text-slate-500">
            <span>Hap shpejt: <kbd class="font-mono px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[10px]">⌘ .</kbd> ose <kbd class="font-mono px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[10px]">⌘ 1-7</kbd></span>
            <span>{{ count($apps) }} apps</span>
        </div>
    </div>
</template>

@push('scripts')
<script>
(function() {
    const trigger  = document.getElementById('appSwitcherTrigger');
    const chevron  = document.getElementById('appSwitcherChevron');
    const template = document.getElementById('appSwitcherTemplate');
    if (!trigger || !template) return;

    let isOpen = false;
    let backdrop = null;
    let panel = null;

    const shortcutMap = {
        '1': @json(config('app.pos_url', 'https://pos.zeroabsolute.dev') . '/pos'),
        '2': null, // we are on Marketing
        '3': @json(config('app.hrms_url', 'https://hrms.zeroabsolute.dev')),
        '4': @json(config('app.mail_url', 'https://mail.zeroabsolute.dev')),
        '5': @json(config('app.chat_url', 'https://chat.zeroabsolute.dev')),
        '6': @json(config('app.anttech_url', 'https://anttech.zeroabsolute.dev')),
        '7': @json(config('app.web_url', 'https://zeroabsolute.com/web-administration')),
    };

    function open() {
        if (isOpen) return;
        isOpen = true;
        const fragment = template.content.cloneNode(true);
        document.body.appendChild(fragment);
        backdrop = document.getElementById('appSwitcherBackdrop');
        panel    = document.getElementById('appSwitcherPanel');
        // animate in
        requestAnimationFrame(() => {
            backdrop.style.opacity = '1';
            panel.style.opacity = '1';
            panel.style.transform = 'translateY(0) scale(1)';
        });
        backdrop.addEventListener('click', close);
        chevron && (chevron.style.transform = 'rotate(180deg)');
    }

    function close() {
        if (!isOpen) return;
        isOpen = false;
        if (backdrop) backdrop.style.opacity = '0';
        if (panel) {
            panel.style.opacity = '0';
            panel.style.transform = 'translateY(-8px) scale(0.96)';
        }
        chevron && (chevron.style.transform = '');
        setTimeout(() => {
            backdrop && backdrop.remove();
            panel && panel.remove();
            backdrop = null;
            panel = null;
        }, 200);
    }

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        isOpen ? close() : open();
    });

    document.addEventListener('keydown', (e) => {
        // ⌘. ose Ctrl+. → toggle
        if ((e.metaKey || e.ctrlKey) && e.key === '.') {
            e.preventDefault();
            isOpen ? close() : open();
            return;
        }
        // Esc → close
        if (e.key === 'Escape' && isOpen) {
            close();
            return;
        }
        // ⌘1-7 direct jump
        if ((e.metaKey || e.ctrlKey) && /^[1-7]$/.test(e.key)) {
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) return;
            const url = shortcutMap[e.key];
            if (url) {
                e.preventDefault();
                if (e.key === '1') {
                    window.location.href = url;
                } else {
                    window.open(url, '_blank', 'noopener');
                }
            }
        }
    });
})();
</script>
@endpush
