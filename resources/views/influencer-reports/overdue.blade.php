@extends('_layouts.app', [
    'title'     => __('influencer_product.overdue_products'),
    'pageTitle' => 'Influencer Reports',
])

@section('content')
@php
    $totalOverdue = $products->total();
    $criticalCount = $products->filter(fn($p) => $p->expected_return_date->diffInDays(now()) > 30)->count();
    $warningCount = $products->filter(fn($p) => $p->expected_return_date->diffInDays(now()) >= 7 && $p->expected_return_date->diffInDays(now()) <= 30)->count();
    $recentCount = $products->filter(fn($p) => $p->expected_return_date->diffInDays(now()) < 7)->count();
@endphp

<div class="mt-4 pb-10">
    @include('influencer-reports._tabs')

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-2.5 mb-4">
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 m-0 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:exclamation-circle" class="align-[-3px]"></iconify-icon>
            @lang('influencer_product.overdue_products')
        </h1>
        <div class="flex items-center gap-2 flex-wrap">
            <form method="GET" class="flex items-center gap-2">
                <select name="influencer_id" class="h-[30px] px-2.5 border border-slate-200 dark:border-slate-600 rounded-md bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-200 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10">
                    <option value="">@lang('influencer.title')</option>
                    @foreach($influencers as $inf)
                        <option value="{{ $inf->id }}" {{ request('influencer_id') == $inf->id ? 'selected' : '' }}>{{ $inf->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex items-center gap-1.5 h-[30px] px-3.5 rounded-md text-xs font-medium bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 cursor-pointer transition-all duration-150">Filtro</button>
                @if(request()->hasAny(['influencer_id']))
                    <a href="{{ route('marketing.influencer-reports.overdue') }}" class="inline-flex items-center gap-1.5 h-[30px] px-3.5 rounded-md text-xs font-medium border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-800 no-underline hover:border-blue-500 hover:text-blue-600 transition-all duration-150">Pastro</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-red-50">
                <iconify-icon icon="heroicons-outline:exclamation-triangle" class="text-red-800"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Totali Vonuara</div>
                <div class="text-[22px] font-bold text-red-800 leading-tight">{{ $totalOverdue }}</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-red-50">
                <iconify-icon icon="heroicons-outline:fire" class="text-red-800"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Kritike (+30 dite)</div>
                <div class="text-[22px] font-bold text-red-800 leading-tight">{{ $criticalCount }}</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-orange-50">
                <iconify-icon icon="heroicons-outline:clock" class="text-orange-700"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Mesatare (7-30 dite)</div>
                <div class="text-[22px] font-bold text-orange-700 leading-tight">{{ $warningCount }}</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-orange-50">
                <iconify-icon icon="heroicons-outline:bell" class="text-amber-600"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Te reja (&lt;7 dite)</div>
                <div class="text-[22px] font-bold text-amber-600 leading-tight">{{ $recentCount }}</div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
            <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-red-50">
                <iconify-icon icon="heroicons-outline:exclamation-circle" class="text-red-600"></iconify-icon>
            </div>
            <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide m-0">@lang('influencer_product.overdue_products') ({{ $products->total() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/60 dark:bg-slate-900/40">
                    <tr>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.serial')</th>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer.singular')</th>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.branch')</th>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.deadline')</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.days_overdue')</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.actions.view')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $daysOverdue = $product->expected_return_date->diffInDays(now());
                            $severityBorder = $daysOverdue > 30 ? 'border-l-[3px] border-l-red-800' : ($daysOverdue >= 7 ? 'border-l-[3px] border-l-orange-700' : 'border-l-[3px] border-l-amber-500');
                            $badgeClasses = $daysOverdue > 30 ? 'bg-red-50 text-red-800' : ($daysOverdue >= 7 ? 'bg-orange-50 text-orange-700' : 'bg-orange-50 text-amber-600');
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                            <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 {{ $severityBorder }}">
                                <a href="{{ route('marketing.influencer-products.show', $product) }}" class="text-blue-600 font-semibold no-underline hover:underline text-xs font-mono">{{ $product->serial }}</a>
                            </td>
                            <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 font-semibold text-slate-600 dark:text-slate-300">{{ $product->influencer?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-slate-400">{{ $product->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-xs font-mono text-red-800">
                                {{ $product->expected_return_date?->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-right">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badgeClasses }}">
                                    +{{ $daysOverdue }}d
                                </span>
                            </td>
                            <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-right">
                                <a href="{{ route('marketing.influencer-products.show', $product) }}" class="text-xs font-medium text-blue-600 no-underline hover:underline">
                                    @lang('influencer_product.actions.view') &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="text-center py-10 px-4 text-slate-400 text-[13px]">
                                    <iconify-icon icon="heroicons-outline:check-circle" class="text-[32px] block mx-auto mb-2 text-green-700"></iconify-icon>
                                    <p>@lang('influencer_product.all_on_time')</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
