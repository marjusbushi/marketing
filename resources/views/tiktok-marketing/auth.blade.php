@extends('_layouts.app', [
    'title'     => 'TikTok Auth — Token Management',
    'pageTitle' => 'TikTok Auth',
])

@section('header-actions')
    <a href="{{ route('marketing.analytics.tiktok') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 text-xs text-slate-500 hover:bg-slate-50 transition-colors">
        <iconify-icon icon="heroicons-outline:arrow-left" width="15"></iconify-icon> TikTok Analytics
    </a>
@endsection

@section('content')
<div class="space-y-5 max-w-4xl">

    @if(session('success'))
        <div class="flex items-center gap-2.5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-medium text-emerald-700">
            <iconify-icon icon="heroicons-outline:check-circle" width="18"></iconify-icon>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-2.5 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm font-medium text-red-700">
            <iconify-icon icon="heroicons-outline:exclamation-circle" width="18"></iconify-icon>
            {{ session('error') }}
        </div>
    @endif

    {{-- OAuth Connect --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="logos:tiktok-icon" width="18"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Lidh Llogarinë TikTok (OAuth)</h3>
        </div>
        <div class="p-5">
            <p class="text-sm text-slate-500 mb-4">
                Lidh llogarinë tënde TikTok për të sinkronizuar data organike (videot, followers, engagement) dhe ads (campaigns, spend, conversions).
            </p>
            @if($clientKey)
                <a href="{{ route('marketing.tiktok-auth.redirect') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-colors">
                    <iconify-icon icon="logos:tiktok-icon" width="16"></iconify-icon>
                    Lidh me TikTok
                </a>
            @else
                <div class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-700">
                    <iconify-icon icon="heroicons-outline:exclamation-triangle" width="18"></iconify-icon>
                    <strong>TIKTOK_CLIENT_KEY</strong> nuk është konfiguruar në <code class="bg-amber-100 px-1 rounded">.env</code>
                </div>
            @endif
        </div>
    </div>

    {{-- Active Tokens --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:key" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Tokens Aktive</h3>
        </div>
        @if($tokens->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-slate-100">
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Emri</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Lloji</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Open ID</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Advertiser ID</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Access Token</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Refresh Token</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Përdorur</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Veprime</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $token)
                    <tr class="border-b border-slate-50 hover:bg-slate-50/60">
                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $token['name'] }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $token['token_type'] === 'ads' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $token['token_type'] }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs font-mono text-slate-500">{{ Str::limit($token['open_id'], 12) }}</td>
                        <td class="px-4 py-2.5 text-center text-xs font-mono text-slate-500">{{ $token['advertiser_id'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center text-xs">
                            @if($token['access_expires_at'])
                                <span class="{{ $token['is_access_expired'] ? 'text-red-600 font-semibold' : 'text-emerald-600' }}">
                                    {{ $token['access_expires_at'] }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs">
                            @if($token['refresh_expires_at'])
                                <span class="{{ $token['is_refresh_expired'] ? 'text-red-600 font-semibold' : 'text-emerald-600' }}">
                                    {{ $token['refresh_expires_at'] }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs text-slate-400">{{ $token['last_used'] ?? 'Asnjëherë' }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <form method="POST" action="{{ route('marketing.tiktok-auth.delete-token', $token['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-md border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-400 transition-colors" title="Çaktivizo"
                                        onclick="return confirm('Sigurt që dëshironi ta çaktivizoni këtë token?')">
                                    <iconify-icon icon="heroicons-outline:trash" width="14"></iconify-icon>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-10 text-slate-400 text-sm">
            <iconify-icon icon="heroicons-outline:key" width="28" class="block mx-auto mb-2 text-slate-300"></iconify-icon>
            Nuk ka tokens aktive. Lidh llogarinë TikTok për të filluar.
        </div>
        @endif
    </div>

    {{-- Manual Config Info --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:cog-6-tooth" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Konfigurim (.env)</h3>
        </div>
        <div class="p-5">
            <div class="bg-slate-900 rounded-lg p-4 font-mono text-xs text-slate-300 space-y-1">
                <div>TIKTOK_CLIENT_KEY=<span class="{{ config('tiktok.client_key') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('tiktok.client_key') ? '***SET***' : 'NOT SET' }}</span></div>
                <div>TIKTOK_CLIENT_SECRET=<span class="{{ config('tiktok.client_secret') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('tiktok.client_secret') ? '***SET***' : 'NOT SET' }}</span></div>
                <div>TIKTOK_APP_ID=<span class="{{ config('tiktok.app_id') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('tiktok.app_id') ?: 'NOT SET' }}</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
