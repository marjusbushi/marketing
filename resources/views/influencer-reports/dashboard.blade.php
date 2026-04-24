@extends('_layouts.app', [
    'title'     => __('influencer.dashboard'),
    'pageTitle' => 'Influencer Reports',
])

@section('content')
    @php
        $maxLeaderboard = $topInfluencers->max('active_products_count') ?: 1;
    @endphp

    <div class="pb-10">
        @include('influencer-reports._tabs')

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center gap-2 mb-2.5">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-blue-50">
                        <iconify-icon icon="heroicons-outline:user-group" class="text-blue-600"></iconify-icon>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide m-0">@lang('influencer.plural')</p>
                </div>
                <p class="text-[26px] font-bold text-slate-900 dark:text-slate-100 leading-none m-0">{{ $stats['total_influencers'] }}</p>
                <div class="flex items-center gap-1.5 mt-2 text-xs text-slate-400">
                    <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-800">{{ $stats['active_influencers'] }} @lang('influencer.active')</span>
                    @if($stats['inactive_influencers'] > 0)
                        <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-800">{{ $stats['inactive_influencers'] }} @lang('influencer.inactive')</span>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center gap-2 mb-2.5">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-green-50">
                        <iconify-icon icon="heroicons-outline:cube" class="text-green-700"></iconify-icon>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide m-0">@lang('influencer_product.active_products')</p>
                </div>
                <p class="text-[26px] font-bold text-slate-900 dark:text-slate-100 leading-none m-0">{{ $stats['active_products'] }}</p>
                <div class="flex items-center gap-1.5 mt-2 text-xs text-slate-400">
                    <span>nga {{ $stats['total_products'] }} total</span>
                    <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500">{{ $stats['draft_products'] }} draft</span>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center gap-2 mb-2.5">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 {{ $stats['overdue_products'] > 0 ? 'bg-red-50' : 'bg-green-50' }}">
                        <iconify-icon icon="heroicons-outline:exclamation-circle" class="{{ $stats['overdue_products'] > 0 ? 'text-red-600' : 'text-green-700' }}"></iconify-icon>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide m-0">@lang('influencer_product.overdue_products')</p>
                </div>
                <p class="text-[26px] font-bold leading-none m-0 {{ $stats['overdue_products'] > 0 ? 'text-red-800' : 'text-green-700' }}">
                    {{ $stats['overdue_products'] }}
                </p>
                <div class="flex items-center gap-1.5 mt-2 text-xs">
                    @if($stats['overdue_products'] > 0)
                        <a href="#overdue-section" class="text-red-800 font-medium no-underline hover:underline">@lang('influencer_product.view_details') &rarr;</a>
                    @else
                        <span class="text-green-700">@lang('influencer_product.all_on_time')</span>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center gap-2 mb-2.5">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-purple-50">
                        <iconify-icon icon="heroicons-outline:currency-dollar" class="text-purple-700"></iconify-icon>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide m-0">@lang('influencer_product.value_out')</p>
                </div>
                <p class="text-[26px] font-bold text-slate-900 dark:text-slate-100 leading-none m-0">
                    {{ number_format($stats['total_value_out'], 0, ',', '.') }}
                    <span class="text-sm font-semibold text-slate-400">L</span>
                </p>
                <div class="flex items-center gap-1.5 mt-2 text-xs text-slate-400">@lang('influencer_product.active_products_value')</div>
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Card 1: Recent Products --}}
            <div class="bg-white rounded-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-blue-50">
                            <iconify-icon icon="heroicons-outline:cube" class="text-blue-600"></iconify-icon>
                        </div>
                        <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide m-0">@lang('influencer_product.recent_products')</h2>
                    </div>
                    <a href="{{ route('marketing.influencer-products.index') }}" class="text-xs font-medium text-blue-600 no-underline hover:underline whitespace-nowrap">
                        @lang('general.view_all') &rarr;
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/60 dark:bg-slate-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.serial')</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer.singular')</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.branch')</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.status')</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.date')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentProducts as $product)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                    <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50">
                                        <a href="{{ route('marketing.influencer-products.show', $product) }}" class="text-blue-600 font-semibold no-underline hover:underline text-xs font-mono">
                                            {{ $product->serial }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 font-semibold text-slate-600 dark:text-slate-300">{{ $product->influencer?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-slate-400">{{ $product->branch?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap
                                            @switch($product->status->color())
                                                @case('success') bg-green-50 text-green-800 @break
                                                @case('danger') bg-red-50 text-red-800 @break
                                                @case('warning') bg-orange-50 text-orange-700 @break
                                                @case('info') bg-blue-50 text-blue-700 @break
                                                @case('draft') bg-slate-100 text-slate-500 @break
                                                @case('purple') bg-purple-50 text-purple-700 @break
                                                @default bg-slate-100 text-slate-500
                                            @endswitch
                                        ">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60"></span>
                                            {{ $product->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-right text-xs text-slate-400 font-mono">
                                        {{ $product->created_at?->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="text-center py-7 px-4 text-slate-400 text-[13px]">
                                            <iconify-icon icon="heroicons-outline:inbox" class="text-[28px] block mb-1.5 text-slate-300"></iconify-icon>
                                            <p>@lang('influencer_product.no_recent_products')</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Card 2: Top Influencers --}}
            <div class="bg-white rounded-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-orange-50">
                            <iconify-icon icon="heroicons-outline:star" class="text-orange-700"></iconify-icon>
                        </div>
                        <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide m-0">Top Influencer-et</h2>
                    </div>
                </div>
                <div>
                    @forelse($topInfluencers as $index => $inf)
                        @php
                            $rankClass = match($index) {
                                0 => 'bg-orange-50 text-orange-700',
                                1 => 'bg-slate-100 text-slate-500',
                                2 => 'bg-orange-50 text-orange-800',
                                default => 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500',
                            };
                            $words = array_filter(explode(' ', $inf->name));
                            $initials = strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', array_slice($words, 0, 2))));
                            $barWidth = round(($inf->active_products_count / $maxLeaderboard) * 100);
                        @endphp
                        <div class="flex items-center gap-2.5 px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 last:border-b-0 hover:bg-slate-50 dark:hover:bg-slate-900/30">
                            <div class="w-[22px] h-[22px] rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 {{ $rankClass }}">{{ $index + 1 }}</div>
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">{{ $initials }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $inf->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $inf->active_products_count }} @lang('influencer_product.active_products')</div>
                            </div>
                            <div class="w-[60px] shrink-0">
                                <div class="h-[5px] rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-blue-600" style="width:{{ $barWidth }}%;"></div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-7 px-4 text-slate-400 text-[13px]">
                            <iconify-icon icon="heroicons-outline:user-group" class="text-[28px] block mb-1.5 text-slate-300"></iconify-icon>
                            <p>@lang('influencer.no_active_influencers')</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Card 3: Overdue Products --}}
            @if($overdueProducts->count() > 0)
                <div class="bg-white rounded-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700 overflow-hidden" id="overdue-section">
                    <div class="flex items-center justify-between gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-red-50">
                                <iconify-icon icon="heroicons-outline:exclamation-circle" class="text-red-600"></iconify-icon>
                            </div>
                            <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide m-0">@lang('influencer_product.overdue_products')</h2>
                            <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-800 ml-1">{{ $overdueProducts->count() }}</span>
                        </div>
                        <a href="{{ route('marketing.influencer-reports.overdue') }}" class="text-xs font-medium text-red-800 no-underline hover:underline whitespace-nowrap">
                            @lang('general.view_all') &rarr;
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50/60 dark:bg-slate-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.serial')</th>
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer.singular')</th>
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.fields.deadline')</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">@lang('influencer_product.days_overdue')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($overdueProducts as $product)
                                    @php
                                        $days = $product->expected_return_date->diffInDays(now());
                                    @endphp
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50">
                                            <a href="{{ route('marketing.influencer-products.show', $product) }}" class="text-blue-600 font-semibold no-underline hover:underline text-xs font-mono">{{ $product->serial }}</a>
                                        </td>
                                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 font-semibold text-slate-600 dark:text-slate-300">{{ $product->influencer?->name ?? '—' }}</td>
                                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-xs text-red-600 font-mono">
                                            {{ $product->expected_return_date?->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-700/50 text-right">
                                            <span class="inline-flex items-center gap-1 bg-red-50 text-red-900 text-[11px] font-semibold px-2 py-0.5 rounded-full">+{{ $days }}d</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Card 4: Quick Reports --}}
            <div class="bg-white rounded-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between gap-2 px-4 pt-3.5 pb-3 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm shrink-0 bg-blue-50">
                            <iconify-icon icon="heroicons-outline:document-report" class="text-blue-700"></iconify-icon>
                        </div>
                        <h2 class="text-[13px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide m-0">Raporte te Shpejta</h2>
                    </div>
                </div>
                <div class="p-2">
                    <a class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-md border border-slate-200 dark:border-slate-700 no-underline text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 hover:border-blue-500 hover:text-blue-600 transition-all duration-150 mb-1.5" href="{{ route('marketing.influencer-reports.overdue') }}">
                        <span class="flex items-center gap-2 text-[13px] font-medium">
                            <iconify-icon icon="heroicons-outline:clock"></iconify-icon>
                            @lang('influencer_product.overdue_products')
                        </span>
                        <span class="text-[11px] text-slate-400">{{ $stats['overdue_products'] }}</span>
                    </a>
                    <a class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-md border border-slate-200 dark:border-slate-700 no-underline text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 hover:border-blue-500 hover:text-blue-600 transition-all duration-150 mb-1.5" href="{{ route('marketing.influencer-reports.value-by-influencer') }}">
                        <span class="flex items-center gap-2 text-[13px] font-medium">
                            <iconify-icon icon="heroicons-outline:chart-square-bar"></iconify-icon>
                            @lang('influencer_product.value_by_influencer')
                        </span>
                        <iconify-icon icon="heroicons-outline:arrow-right" class="text-[11px] text-slate-400"></iconify-icon>
                    </a>
                    <a class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-md border border-slate-200 dark:border-slate-700 no-underline text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 hover:border-blue-500 hover:text-blue-600 transition-all duration-150" href="{{ route('marketing.influencer-reports.monthly') }}">
                        <span class="flex items-center gap-2 text-[13px] font-medium">
                            <iconify-icon icon="heroicons-outline:calendar"></iconify-icon>
                            @lang('influencer_product.monthly_activity')
                        </span>
                        <iconify-icon icon="heroicons-outline:arrow-right" class="text-[11px] text-slate-400"></iconify-icon>
                    </a>
                </div>
            </div>

        </div>{{-- /grid --}}

    </div>
@endsection
