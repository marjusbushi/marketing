@extends('_layouts.app', [
    'title'     => 'Meta Auth — Token Management',
    'pageTitle' => 'Meta Auth',
])

@section('header-actions')
    <a href="{{ route('marketing.analytics.index') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 text-xs text-slate-500 hover:bg-slate-50 transition-colors">
        <iconify-icon icon="heroicons-outline:arrow-left" width="15"></iconify-icon> Analytics
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
            <iconify-icon icon="heroicons-outline:link" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Lidh Llogarinë Meta (OAuth)</h3>
        </div>
        <div class="p-5">
            <p class="text-sm text-slate-500 mb-4">
                Kliko butonin më poshtë për të lidhur llogarinë tënde Meta/Facebook me Marketing.
                Kjo do të gjenerojë automatikisht tokens për Facebook Page dhe Instagram Business Account.
            </p>
            @if($appId)
                <a href="#" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">
                    <iconify-icon icon="mdi:facebook" width="18"></iconify-icon>
                    Lidh me Meta
                </a>
            @else
                <div class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-700">
                    <iconify-icon icon="heroicons-outline:exclamation-triangle" width="18"></iconify-icon>
                    <strong>META_APP_ID</strong> nuk është konfiguruar në <code class="bg-amber-100 px-1 rounded">.env</code>
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
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Page ID</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">IG ID</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Skadon</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Përdorur</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Veprime</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $token)
                    <tr class="border-b border-slate-50 hover:bg-slate-50/60">
                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $token['name'] }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $token['type'] === 'page' ? 'bg-emerald-50 text-emerald-700' : 'bg-primary-50 text-primary-700' }}">
                                {{ $token['type'] }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs font-mono text-slate-500">{{ $token['page_id'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center text-xs font-mono text-slate-500">{{ $token['ig_account_id'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center text-xs">
                            @if($token['expires_at'])
                                <span class="{{ $token['is_expired'] ? 'text-red-600 font-semibold' : ($token['expires_soon'] ? 'text-amber-600' : 'text-emerald-600') }}">
                                    {{ $token['expires_at'] }}
                                </span>
                            @else
                                <span class="text-emerald-600">Nuk skadon</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs text-slate-400">{{ $token['last_used'] ?? 'Asnjëherë' }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <div class="flex gap-1.5 justify-center">
                                @if($token['type'] === 'long_lived_user')
                                <form method="POST" action="{{ route('marketing.meta-auth.save-token') }}">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-md border border-slate-200 text-slate-400 hover:text-primary-600 hover:border-primary-500 transition-colors" title="Rifresko">
                                        <iconify-icon icon="heroicons-outline:arrow-path" width="14"></iconify-icon>
                                    </button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('marketing.meta-auth.delete-token', $token['id']) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-md border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-400 transition-colors" title="Çaktivizo"
                                            onclick="return confirm('Sigurt që dëshironi ta çaktivizoni këtë token?')">
                                        <iconify-icon icon="heroicons-outline:trash" width="14"></iconify-icon>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-10 text-slate-400 text-sm">
            <iconify-icon icon="heroicons-outline:key" width="28" class="block mx-auto mb-2 text-slate-300"></iconify-icon>
            Nuk ka tokens aktive. Lidh llogarinë Meta për të filluar.
        </div>
        @endif
    </div>

    {{-- Manual Token Info --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:cog-6-tooth" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Token Manual (.env)</h3>
        </div>
        <div class="p-5">
            <p class="text-sm text-slate-500 mb-3">
                Përskaj OAuth, mund të konfigurosh tokens manualisht në <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">.env</code>:
            </p>
            <div class="bg-slate-900 rounded-lg p-4 font-mono text-xs text-slate-300 space-y-1">
                <div>META_SYSTEM_USER_TOKEN=<span class="{{ config('meta.token') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('meta.token') ? '***SET***' : 'NOT SET' }}</span></div>
                <div>META_PAGE_TOKEN=<span class="{{ config('meta.page_token') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('meta.page_token') ? '***SET***' : 'NOT SET' }}</span></div>
                <div>META_APP_ID=<span class="{{ config('meta.app_id') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('meta.app_id') ?: 'NOT SET' }}</span></div>
                <div>META_APP_SECRET=<span class="{{ config('meta.app_secret') ? 'text-emerald-400' : 'text-red-400' }}">{{ config('meta.app_secret') ? '***SET***' : 'NOT SET' }}</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
