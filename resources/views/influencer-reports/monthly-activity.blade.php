@extends('_layouts.app', [
    'title'     => __('influencer_product.monthly_activity'),
    'pageTitle' => 'Influencer Reports',
])

@section('content')
<div class="mt-4 pb-10">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2.5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 m-0 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:calendar" class="align-[-3px]"></iconify-icon>
            @lang('influencer_product.monthly_activity')
            <span class="text-sm font-normal text-slate-400 ml-1">— {{ $year }}</span>
        </h1>
        <div class="flex items-center gap-2 flex-wrap">
            <form method="GET" class="flex items-center gap-2">
                <select name="year" class="h-[30px] px-2.5 border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 h-[30px] rounded-md text-xs font-medium bg-blue-500 text-white hover:bg-blue-600 border border-transparent cursor-pointer transition-all duration-150">Shiko</button>
            </form>
            <a href="{{ route('marketing.influencer-reports.dashboard') }}" class="inline-flex items-center gap-1.5 px-3.5 h-[30px] rounded-md text-xs font-medium bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:border-blue-500 hover:text-blue-500 no-underline cursor-pointer transition-all duration-150">
                &larr; Dashboard
            </a>
        </div>
    </div>

    {{-- Year Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-blue-50">
                <iconify-icon icon="heroicons-outline:cube" class="text-blue-500"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.new_products')</div>
                <div class="text-[22px] font-bold text-blue-500 leading-tight">{{ $yearStats['total_new'] }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-green-50">
                <iconify-icon icon="heroicons-outline:arrow-uturn-left" class="text-green-700"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.returned')</div>
                <div class="text-[22px] font-bold text-green-700 leading-tight">{{ $yearStats['total_returned'] }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-purple-50">
                <iconify-icon icon="heroicons-outline:shopping-cart" class="text-purple-700"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.converted')</div>
                <div class="text-[22px] font-bold text-purple-700 leading-tight">{{ $yearStats['total_converted'] }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center text-lg shrink-0 bg-slate-100">
                <iconify-icon icon="heroicons-outline:banknotes" class="text-slate-500"></iconify-icon>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">@lang('influencer_product.total_value')</div>
                <div class="text-[22px] font-bold text-slate-900 dark:text-slate-100 leading-tight">{{ number_format($yearStats['total_value'], 0, ',', '.') }} L</div>
            </div>
        </div>
    </div>

    {{-- Monthly Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
            <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-blue-50">
                <iconify-icon icon="heroicons-outline:calendar" class="text-blue-500"></iconify-icon>
            </div>
            <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 m-0 uppercase tracking-wider">@lang('influencer_product.monthly_activity') — {{ $year }}</h2>
        </div>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.fields.month')</th>
                    <th class="px-4 py-2 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.new_products')</th>
                    <th class="px-4 py-2 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.returned')</th>
                    <th class="px-4 py-2 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.converted')</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">@lang('influencer_product.fields.value')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyData as $month)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 last:border-b-0 font-semibold">{{ $month['month'] }}</td>
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 last:border-b-0 text-center">
                            @if($month['new_products'] > 0)
                                <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700">{{ $month['new_products'] }}</span>
                            @else
                                <span class="text-slate-300">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 last:border-b-0 text-center">
                            @if($month['returned_products'] > 0)
                                <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700">{{ $month['returned_products'] }}</span>
                            @else
                                <span class="text-slate-300">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 last:border-b-0 text-center">
                            @if($month['converted_products'] > 0)
                                <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-purple-50 text-purple-700">{{ $month['converted_products'] }}</span>
                            @else
                                <span class="text-slate-300">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 border-b border-slate-50 dark:border-slate-700 last:border-b-0 text-right font-semibold">{{ number_format($month['total_value'], 0, ',', '.') }} L</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
