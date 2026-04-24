@extends('_layouts.app', [
    'title'     => __('influencer_product.value_by_influencer'),
    'pageTitle' => 'Influencer Reports',
])

@section('content')
@php
    $totalGiven = $influencers->sum('total_given_value');
    $totalReturned = $influencers->sum('total_returned_value');
    $totalKept = $influencers->sum('total_kept_value');
    $totalProducts = $influencers->sum('total_products');
@endphp

<div class="mt-4 pb-10">
    @include('influencer-reports._tabs')

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2.5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 m-0 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:chart-bar" class="align-[-3px]"></iconify-icon>
            @lang('influencer_product.value_by_influencer')
        </h1>
        <div class="flex items-center gap-2 flex-wrap">
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="h-[30px] px-2.5 border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10">
                <span class="text-slate-400">—</span>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="h-[30px] px-2.5 border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 h-[30px] rounded-md text-xs font-medium bg-blue-500 text-white hover:bg-blue-600 border-none cursor-pointer transition-all duration-150">Filtro</button>
            </form>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-blue-50">
                <iconify-icon icon="heroicons-outline:users" class="text-blue-500"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Influencer-a</div>
                <div class="text-[22px] font-bold text-blue-500 leading-tight">{{ $influencers->count() }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-blue-50">
                <iconify-icon icon="heroicons-outline:cube" class="text-blue-700"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.fields.given_value')</div>
                <div class="text-[22px] font-bold text-blue-700 leading-tight">{{ number_format($totalGiven, 0, ',', '.') }} L</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-green-50">
                <iconify-icon icon="heroicons-outline:arrow-uturn-left" class="text-green-700"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.fields.returned_value')</div>
                <div class="text-[22px] font-bold text-green-700 leading-tight">{{ number_format($totalReturned, 0, ',', '.') }} L</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-orange-50">
                <iconify-icon icon="heroicons-outline:banknotes" class="text-orange-700"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.fields.kept_value')</div>
                <div class="text-[22px] font-bold text-orange-700 leading-tight">{{ number_format($totalKept, 0, ',', '.') }} L</div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
            <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-blue-50">
                <iconify-icon icon="heroicons-outline:chart-bar" class="text-blue-500"></iconify-icon>
            </div>
            <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 m-0 uppercase tracking-wider">@lang('influencer_product.value_by_influencer') ({{ $influencers->count() }})</h2>
        </div>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr>
                    <th class="w-10 px-4 py-2 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">#</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer.singular')</th>
                    <th class="px-4 py-2 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.fields.products_count')</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.fields.given_value')</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.fields.returned_value')</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.fields.kept_value')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($influencers as $index => $influencer)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 align-middle">
                            @if($index === 0)
                                <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full text-[11px] font-bold bg-orange-50 text-orange-800">1</span>
                            @elseif($index === 1)
                                <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full text-[11px] font-bold bg-slate-100 text-slate-500">2</span>
                            @elseif($index === 2)
                                <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full text-[11px] font-bold bg-orange-50 text-orange-900">3</span>
                            @else
                                <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full text-[11px] font-bold bg-slate-100 text-slate-400">{{ $index + 1 }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 align-middle">
                            <a href="{{ route('marketing.influencers.show', $influencer) }}" class="text-blue-500 font-semibold no-underline hover:underline">{{ $influencer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 align-middle text-center">
                            <span class="inline-flex items-center justify-center min-w-[26px] h-[22px] px-2 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">{{ $influencer->total_products }}</span>
                        </td>
                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700 align-middle text-right font-semibold text-slate-700 dark:text-slate-300">{{ number_format($influencer->total_given_value, 0, ',', '.') }} L</td>
                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700 align-middle text-right font-medium text-green-700">{{ number_format($influencer->total_returned_value, 0, ',', '.') }} L</td>
                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700 align-middle text-right font-medium text-orange-700">{{ number_format($influencer->total_kept_value, 0, ',', '.') }} L</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="text-center py-10 px-4 text-slate-400 text-sm">
                                <iconify-icon icon="heroicons-outline:chart-bar" class="text-[32px] block mx-auto mb-2 text-slate-300"></iconify-icon>
                                <p>@lang('influencer_product.no_data_period')</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($influencers->count() > 0)
                <tfoot>
                    <tr>
                        <td colspan="2" class="px-4 py-2.5 bg-slate-50 dark:bg-slate-700/60 font-bold text-slate-700 dark:text-slate-200 border-t-2 border-slate-200 dark:border-slate-600">Totali</td>
                        <td class="px-4 py-2.5 bg-slate-50 dark:bg-slate-700/60 font-bold text-slate-700 dark:text-slate-200 border-t-2 border-slate-200 dark:border-slate-600 text-center">{{ $totalProducts }}</td>
                        <td class="px-4 py-2.5 bg-slate-50 dark:bg-slate-700/60 font-bold text-slate-700 dark:text-slate-200 border-t-2 border-slate-200 dark:border-slate-600 text-right">{{ number_format($totalGiven, 0, ',', '.') }} L</td>
                        <td class="px-4 py-2.5 bg-slate-50 dark:bg-slate-700/60 font-bold text-green-700 border-t-2 border-slate-200 dark:border-slate-600 text-right">{{ number_format($totalReturned, 0, ',', '.') }} L</td>
                        <td class="px-4 py-2.5 bg-slate-50 dark:bg-slate-700/60 font-bold text-orange-700 border-t-2 border-slate-200 dark:border-slate-600 text-right">{{ number_format($totalKept, 0, ',', '.') }} L</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
