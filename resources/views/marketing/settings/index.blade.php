@extends('_layouts.app', ['pageTitle' => 'Settings'])

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-8">
        <h2 class="text-2xl font-semibold text-slate-900">Settings</h2>
        <p class="mt-1 text-sm text-slate-500">Konfigurime per Visual Studio, Brand Kit dhe lidhjet me Meta + TikTok.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        @can('content_planner.view')
        <a href="{{ route('marketing.studio.index') }}"
           class="group flex items-start gap-4 p-5 rounded-xl border border-slate-200 bg-white hover:border-primary-300 hover:shadow-sm transition-all">
            <div class="w-11 h-11 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" /></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-slate-900 group-hover:text-primary-700">Visual Studio</div>
                <div class="mt-1 text-[12px] text-slate-500 leading-relaxed">Mjet krijues per video, foto dhe kompozime, me AI dhe template.</div>
            </div>
        </a>
        @endcan

        @can('content_planner.manage')
        <a href="{{ route('marketing.settings.brand-kit.index') }}"
           class="group flex items-start gap-4 p-5 rounded-xl border border-slate-200 bg-white hover:border-primary-300 hover:shadow-sm transition-all">
            <div class="w-11 h-11 rounded-lg bg-pink-50 text-pink-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072m-6.402 11.705L19.5 4.5m0 0h-5.25M19.5 4.5v5.25" /></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-slate-900 group-hover:text-primary-700">Brand Kit</div>
                <div class="mt-1 text-[12px] text-slate-500 leading-relaxed">Logo, ngjyra, fontet dhe asetet e brendit per Studio dhe templates.</div>
            </div>
        </a>
        @endcan

        @can('analytics.manage')
        <a href="{{ route('marketing.meta-auth.index') }}"
           class="group flex items-start gap-4 p-5 rounded-xl border border-slate-200 bg-white hover:border-primary-300 hover:shadow-sm transition-all">
            <div class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-slate-900 group-hover:text-primary-700">Meta Auth</div>
                <div class="mt-1 text-[12px] text-slate-500 leading-relaxed">Lidh Facebook + Instagram me OAuth, menaxho token-et per sync e ads.</div>
            </div>
        </a>

        <a href="{{ route('marketing.tiktok-auth.index') }}"
           class="group flex items-start gap-4 p-5 rounded-xl border border-slate-200 bg-white hover:border-primary-300 hover:shadow-sm transition-all">
            <div class="w-11 h-11 rounded-lg bg-zinc-100 text-zinc-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z" /></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-slate-900 group-hover:text-primary-700">TikTok Auth</div>
                <div class="mt-1 text-[12px] text-slate-500 leading-relaxed">Lidh llogarine TikTok per analytics dhe import te video-ve.</div>
            </div>
        </a>
        @endcan

    </div>
</div>
@endsection
