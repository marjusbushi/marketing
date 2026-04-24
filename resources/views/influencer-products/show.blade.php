@extends('_layouts.app', [
    'title' => $influencerProduct->serial,
    'pageTitle' => __('influencer_product.view'),
])

@section('content')
@php
    $sColor = $influencerProduct->status->color();
    $aColor = $influencerProduct->agreement_type->color();
    $badgeMap = [
        'warning' => 'bg-orange-50 text-orange-700',
        'info'    => 'bg-blue-50 text-blue-700',
        'purple'  => 'bg-purple-50 text-purple-700',
        'success' => 'bg-emerald-50 text-emerald-700',
        'primary' => 'bg-blue-50 text-blue-600',
        'danger'  => 'bg-red-50 text-red-700',
        'draft'   => 'bg-slate-100 text-slate-600',
    ];
@endphp

<div class="pb-10">
    @if(session('success'))
        <div class="px-4 py-2.5 rounded-md text-[13px] font-medium mb-4 bg-emerald-50 text-emerald-700 border border-emerald-300">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="px-4 py-2.5 rounded-md text-[13px] font-medium mb-4 bg-red-50 text-red-700 border border-red-300">{{ session('error') }}</div>
    @endif

    {{-- Stats Row --}}
    <div class="grid grid-cols-4 gap-4 mb-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] p-4">
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.influencer')</div>
            @if($influencerProduct->influencer)
                <a href="{{ route('marketing.influencers.show', $influencerProduct->influencer) }}" class="text-blue-500 font-bold text-lg no-underline hover:underline block mt-1">{{ $influencerProduct->influencer->name }}</a>
                @if($influencerProduct->influencer->handle)
                    <div class="text-[11px] text-gray-400 mt-0.5">@{{ ltrim($influencerProduct->influencer->handle, '@') }}</div>
                @endif
            @else
                <div class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">—</div>
            @endif
        </div>
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] p-4">
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.branch')</div>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 leading-tight">{{ $influencerProduct->branch?->name ?? '—' }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">{{ $influencerProduct->warehouse?->name ?? '' }}</div>
        </div>
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] p-4">
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.total_value')</div>
            <div class="text-lg font-bold text-blue-500 mt-1 leading-tight">{{ number_format($influencerProduct->total_value, 0, ',', '.') }} <span class="text-[13px] font-normal text-gray-400">L</span></div>
        </div>
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] p-4">
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.expected_return_date')</div>
            <div class="text-lg font-bold mt-1 leading-tight {{ $influencerProduct->is_overdue ? 'text-red-700' : 'text-gray-900 dark:text-gray-100' }}">
                {{ $influencerProduct->expected_return_date?->format('d/m/Y') ?? 'Pa afat' }}
            </div>
            @if($influencerProduct->actual_return_date)
                <div class="text-[11px] text-emerald-600 mt-0.5">Kthyer: {{ $influencerProduct->actual_return_date->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-[2fr_1fr] gap-4 max-lg:grid-cols-1">
        <div class="flex flex-col gap-4">
            {{-- Items Table --}}
            <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-blue-50 text-blue-500 text-sm">
                        <iconify-icon icon="heroicons-outline:cube"></iconify-icon>
                    </div>
                    <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">@lang('influencer_product.fields.items')</h2>
                    <div class="ml-auto flex gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $badgeMap[$sColor] ?? 'bg-blue-50 text-blue-700' }}">{{ $influencerProduct->status->label() }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $badgeMap[$aColor] ?? 'bg-blue-50 text-blue-700' }}">{{ $influencerProduct->agreement_type->label() }}</span>
                        @if($influencerProduct->is_overdue)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-red-50 text-red-700">Vonuar</span>
                        @endif
                    </div>
                </div>
                @if($influencerProduct->items->count() > 0)
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/60 dark:bg-[#252525]">
                            <tr>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Artikulli</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">SKU</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Dhene</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Kthyer</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Gjendje</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Vlera</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Mbajtur</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($influencerProduct->items as $line)
                                @php
                                    $condMap = ['good'=>'bg-emerald-50 text-emerald-700','damaged'=>'bg-orange-50 text-orange-700','missing'=>'bg-red-50 text-red-700'];
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-[#252525]">
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 border-b border-gray-50 dark:border-[#252525] font-semibold">{{ $line->item?->name ?? 'Artikull i fshire' }}</td>
                                    <td class="px-4 py-2.5 text-[11px] text-gray-400 font-mono border-b border-gray-50 dark:border-[#252525]">{{ $line->item?->sku ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-50 dark:border-[#252525]">{{ $line->quantity_given }}</td>
                                    <td class="px-4 py-2.5 text-center border-b border-gray-50 dark:border-[#252525]">
                                        @if($line->quantity_returned > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-50 text-emerald-700">{{ $line->quantity_returned }}</span>
                                        @else
                                            <span class="text-gray-300">0</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center border-b border-gray-50 dark:border-[#252525]">
                                        @if($line->return_condition)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $condMap[$line->return_condition->value] ?? 'bg-blue-50 text-blue-700' }}">{{ $line->return_condition->value }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-50 dark:border-[#252525]">{{ number_format($line->product_value, 0, ',', '.') }} L</td>
                                    <td class="px-4 py-2.5 text-center border-b border-gray-50 dark:border-[#252525]">
                                        @if($line->is_kept)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-50 text-blue-600">Po</span>
                                        @else
                                            <span class="text-gray-300">Jo</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 dark:bg-[#252525]">
                                <td colspan="2" class="px-4 py-2.5 text-right font-bold text-gray-600 dark:text-gray-300 border-t-2 border-slate-200 dark:border-[#424242]">Totali:</td>
                                <td class="px-4 py-2.5 text-center font-bold text-gray-600 dark:text-gray-300 border-t-2 border-slate-200 dark:border-[#424242]">{{ $influencerProduct->items->sum('quantity_given') }}</td>
                                <td class="px-4 py-2.5 text-center font-bold text-emerald-600 border-t-2 border-slate-200 dark:border-[#424242]">{{ $influencerProduct->items->sum('quantity_returned') }}</td>
                                <td class="px-4 py-2.5 border-t-2 border-slate-200 dark:border-[#424242]"></td>
                                <td class="px-4 py-2.5 text-right font-bold text-blue-500 border-t-2 border-slate-200 dark:border-[#424242]">{{ number_format($influencerProduct->items->sum('product_value'), 0, ',', '.') }} L</td>
                                <td class="px-4 py-2.5 border-t-2 border-slate-200 dark:border-[#424242]"></td>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <div class="text-center py-10 text-gray-400 text-[13px]">
                        <iconify-icon icon="heroicons-outline:cube" class="text-[32px] block mx-auto mb-2 text-gray-300"></iconify-icon>
                        <p>Nuk ka artikuj</p>
                    </div>
                @endif
            </div>

            {{-- Transfer Order --}}
            @if($influencerProduct->transferOrder)
                <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                        <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-blue-50 text-blue-500 text-sm">
                            <iconify-icon icon="heroicons-outline:switch-horizontal"></iconify-icon>
                        </div>
                        <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">Transfer Order</h2>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-md flex items-center justify-center shrink-0 bg-blue-50 text-blue-500 text-base">
                                <iconify-icon icon="heroicons-outline:switch-horizontal"></iconify-icon>
                            </div>
                            <div>
                                <a href="{{ config('services.dis_app.url', '') }}/marketing/transfer-orders/{{ $influencerProduct->transferOrder->id ?? '' }}" class="text-blue-500 font-semibold text-[13px] no-underline hover:underline">{{ $influencerProduct->transferOrder->serial ?? 'TO' }}</a>
                                <div class="text-[11px] text-gray-400 mt-0.5">Transfer Order per dhenien e produkteve</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Return Transfer Order --}}
            @if($influencerProduct->returnTransferOrder)
                <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                        <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-emerald-50 text-emerald-600 text-sm">
                            <iconify-icon icon="heroicons-outline:reply"></iconify-icon>
                        </div>
                        <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">Transfer Order Kthimi</h2>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-md flex items-center justify-center shrink-0 bg-emerald-50 text-emerald-600 text-base">
                                <iconify-icon icon="heroicons-outline:reply"></iconify-icon>
                            </div>
                            <div>
                                <a href="{{ config('services.dis_app.url', '') }}/marketing/transfer-orders/{{ $influencerProduct->returnTransferOrder->id ?? '' }}" class="text-blue-500 font-semibold text-[13px] no-underline hover:underline">{{ $influencerProduct->returnTransferOrder->serial ?? 'TO' }}</a>
                                <div class="text-[11px] text-gray-400 mt-0.5">Transfer Order per kthimin e produkteve</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Invoice --}}
            @if($influencerProduct->invoice)
                <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                        <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-purple-50 text-purple-700 text-sm">
                            <iconify-icon icon="heroicons-outline:document-text"></iconify-icon>
                        </div>
                        <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">Fatura (Konvertim)</h2>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-md flex items-center justify-center shrink-0 bg-purple-50 text-purple-700 text-base">
                                <iconify-icon icon="heroicons-outline:document-text"></iconify-icon>
                            </div>
                            <div>
                                <a href="{{ config('services.dis_app.url', '') }}/marketing/invoices/{{ $influencerProduct->invoice->id ?? '' }}" class="text-blue-500 font-semibold text-[13px] no-underline hover:underline" target="_blank">{{ $influencerProduct->invoice->serial ?? 'Fatura' }}</a>
                                <div class="text-[11px] text-gray-400 mt-0.5">Fatura e krijuar pas konvertimit</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="flex flex-col gap-4">
            {{-- Actions --}}
            <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-blue-50 text-blue-500 text-sm">
                        <iconify-icon icon="heroicons-outline:cog"></iconify-icon>
                    </div>
                    <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">Veprime</h2>
                </div>
                <div class="p-4">
                    @if($influencerProduct->isDraft())
                        <form action="{{ route('marketing.influencer-products.activate', $influencerProduct) }}" method="POST" id="activate-form">
                            @csrf
                            <button type="submit" class="flex items-center justify-center gap-2 w-full h-[30px] rounded-md text-xs font-semibold cursor-pointer border-none mb-2 transition-all bg-emerald-700 text-white hover:bg-emerald-800">
                                <iconify-icon icon="heroicons-outline:check" class="text-base"></iconify-icon>
                                @lang('influencer_product.actions.activate')
                            </button>
                        </form>
                        <form action="{{ route('marketing.influencer-products.cancel', $influencerProduct) }}" method="POST" onsubmit="return confirm('Jeni i sigurt qe doni ta anuloni?')">
                            @csrf
                            <button type="submit" class="flex items-center justify-center gap-2 w-full h-[30px] rounded-md text-xs font-semibold cursor-pointer border-none mb-2 transition-all bg-red-700 text-white hover:bg-red-800">
                                <iconify-icon icon="heroicons-outline:x" class="text-base"></iconify-icon>
                                @lang('influencer_product.actions.cancel')
                            </button>
                        </form>
                    @endif

                    @if($influencerProduct->isActive() || $influencerProduct->isPartiallyReturned())
                        <button type="button" id="btn-register-return" class="flex items-center justify-center gap-2 w-full h-[30px] rounded-md text-xs font-semibold cursor-pointer border-none mb-2 transition-all bg-emerald-700 text-white hover:bg-emerald-800">
                            <iconify-icon icon="heroicons-outline:reply" class="text-base"></iconify-icon>
                            @lang('influencer_product.actions.return')
                        </button>
                        <button type="button" id="btn-convert" class="flex items-center justify-center gap-2 w-full h-[30px] rounded-md text-xs font-semibold cursor-pointer border-none mb-2 transition-all bg-blue-500 text-white hover:bg-blue-600">
                            <iconify-icon icon="heroicons-outline:shopping-cart" class="text-base"></iconify-icon>
                            @lang('influencer_product.actions.convert')
                        </button>
                        <button type="button" id="btn-extend" class="flex items-center justify-center gap-2 w-full h-[30px] rounded-md text-xs font-semibold cursor-pointer mb-2 transition-all bg-white dark:bg-[#252525] text-gray-600 dark:text-gray-400 border border-slate-200 dark:border-[#424242] hover:border-blue-500 hover:text-blue-500">
                            <iconify-icon icon="heroicons-outline:calendar" class="text-base"></iconify-icon>
                            @lang('influencer_product.actions.extend')
                        </button>
                    @endif

                    @if($influencerProduct->canBeCancelled() && !$influencerProduct->isDraft())
                        <form action="{{ route('marketing.influencer-products.cancel', $influencerProduct) }}" method="POST" onsubmit="return confirm('Jeni i sigurt qe doni ta anuloni? Stoku do te kthehet.')">
                            @csrf
                            <button type="submit" class="flex items-center justify-center gap-2 w-full h-[30px] rounded-md text-xs font-semibold cursor-pointer border-none mb-2 transition-all bg-red-700 text-white hover:bg-red-800">
                                <iconify-icon icon="heroicons-outline:x" class="text-base"></iconify-icon>
                                Anulo Dhenien
                            </button>
                        </form>
                    @endif

                    @if($influencerProduct->status->isFinal())
                        <div class="py-3 text-center text-[13px] text-gray-400">Kjo dhenie ka perfunduar</div>
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-slate-100 text-slate-600 text-sm">
                        <iconify-icon icon="heroicons-outline:information-circle"></iconify-icon>
                    </div>
                    <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">Detaje</h2>
                </div>
                <div class="p-4">
                    <div class="py-2.5">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.created_at')</div>
                        <div class="mt-0.5 text-[13px] text-gray-600 dark:text-gray-300">{{ $influencerProduct->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="py-2.5 border-t border-gray-50 dark:border-[#2A2A2A]">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.created_by')</div>
                        <div class="mt-0.5 text-[13px] text-gray-400">{{ $influencerProduct->createdBy?->full_name ?? 'N/A' }}</div>
                    </div>
                    <div class="py-2.5 border-t border-gray-50 dark:border-[#2A2A2A]">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">@lang('influencer_product.fields.expected_return_date')</div>
                        <div class="mt-0.5 text-[13px] {{ $influencerProduct->is_overdue ? 'text-red-700 font-semibold' : 'text-gray-600 dark:text-gray-300' }}">
                            {{ $influencerProduct->expected_return_date?->format('d/m/Y') ?? 'Pa afat' }}
                        </div>
                    </div>
                    @if($influencerProduct->actual_return_date)
                        <div class="py-2.5 border-t border-gray-50 dark:border-[#2A2A2A]">
                            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Kthyer me</div>
                            <div class="mt-0.5 text-[13px] text-emerald-600">{{ $influencerProduct->actual_return_date->format('d/m/Y') }}</div>
                        </div>
                    @endif
                    <div class="py-2.5 border-t border-gray-50 dark:border-[#2A2A2A]">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Kthimi (%)</div>
                        <div class="w-full h-1.5 bg-gray-200 dark:bg-[#333] rounded-full overflow-hidden mt-1.5">
                            <div class="h-full rounded-full bg-emerald-600" style="width:{{ min(100, $influencerProduct->returned_percentage) }}%;"></div>
                        </div>
                        <div class="text-[11px] text-gray-400 mt-1">{{ $influencerProduct->returned_percentage }}%</div>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            @if($influencerProduct->notes)
            <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-orange-50 text-orange-600 text-sm">
                        <iconify-icon icon="heroicons-outline:document-text"></iconify-icon>
                    </div>
                    <h2 class="text-[13px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider m-0">@lang('influencer_product.fields.notes')</h2>
                </div>
                <div class="p-4">
                    <p class="text-[13px] text-gray-500 whitespace-pre-wrap m-0">{{ $influencerProduct->notes }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Return Modal --}}
<div id="return-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" id="return-overlay"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] shadow-xl w-full max-w-[640px] max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 m-0">@lang('influencer_product.actions.return')</h3>
                <button type="button" class="return-close bg-transparent border-none text-gray-400 cursor-pointer">
                    <iconify-icon icon="heroicons-outline:x" class="text-lg"></iconify-icon>
                </button>
            </div>
            <form action="{{ route('marketing.influencer-products.return', $influencerProduct) }}" method="POST">
                @csrf
                <div class="px-5 py-4">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/60 dark:bg-[#252525]">
                            <tr>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Artikulli</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Mbetur</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A] w-24">Kthej</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A] w-28">Gjendje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($influencerProduct->items as $idx => $line)
                                @if($line->remaining_quantity > 0)
                                    <tr>
                                        <td class="px-4 py-2.5 text-[13px] text-gray-600 dark:text-gray-300 border-b border-gray-50 dark:border-[#252525]">
                                            {{ $line->item?->name ?? '—' }}
                                            <input type="hidden" name="return_items[{{ $idx }}][influencer_product_item_id]" value="{{ $line->id }}">
                                        </td>
                                        <td class="px-4 py-2.5 text-center text-gray-400 border-b border-gray-50 dark:border-[#252525]">{{ $line->remaining_quantity }}</td>
                                        <td class="px-4 py-2.5 text-center border-b border-gray-50 dark:border-[#252525]">
                                            <input type="number" name="return_items[{{ $idx }}][quantity_returned]" value="0" min="0" max="{{ $line->remaining_quantity }}" class="h-8 px-2 text-[13px] border border-slate-200 dark:border-[#424242] rounded-md outline-none bg-white dark:bg-[#252525] text-gray-900 dark:text-gray-100 focus:border-blue-500 w-[70px] text-center">
                                        </td>
                                        <td class="px-4 py-2.5 text-center border-b border-gray-50 dark:border-[#252525]">
                                            <select name="return_items[{{ $idx }}][return_condition]" class="h-8 px-2 text-[13px] border border-slate-200 dark:border-[#424242] rounded-md bg-white dark:bg-[#252525] text-gray-900 dark:text-gray-100 w-full">
                                                <option value="good">Mire</option>
                                                <option value="damaged">Demtuar</option>
                                                <option value="missing">Mungon</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-end gap-2.5 px-5 py-3 border-t border-gray-100 dark:border-[#2A2A2A] bg-slate-50 dark:bg-[#252525]">
                    <button type="button" class="return-close h-[30px] px-4 rounded-md text-xs font-medium bg-white dark:bg-[#252525] text-gray-500 border border-slate-200 dark:border-[#424242] cursor-pointer">Anulo</button>
                    <button type="submit" class="h-[30px] px-4 rounded-md text-xs font-medium border-none cursor-pointer text-white bg-emerald-700">Regjistro Kthimin</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Convert Modal --}}
<div id="convert-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" id="convert-overlay"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] shadow-xl w-full max-w-[640px] max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 m-0">@lang('influencer_product.actions.convert')</h3>
                <button type="button" class="convert-close bg-transparent border-none text-gray-400 cursor-pointer">
                    <iconify-icon icon="heroicons-outline:x" class="text-lg"></iconify-icon>
                </button>
            </div>
            <form action="{{ route('marketing.influencer-products.convert', $influencerProduct) }}" method="POST">
                @csrf
                <div class="px-5 py-4">
                    <p class="text-[13px] text-gray-400 m-0 mb-3.5">Zgjidh artikujt qe influenceri do te mbaje (konvertohen ne shitje/expense):</p>
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/60 dark:bg-[#252525]">
                            <tr>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Artikulli</th>
                                <th class="px-4 py-2 text-center text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A]">Mbetur</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-[#2A2A2A] w-32">Vlera (L)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($influencerProduct->items as $idx => $line)
                                @if($line->remaining_quantity > 0)
                                    <tr>
                                        <td class="px-4 py-2.5 text-[13px] text-gray-600 dark:text-gray-300 border-b border-gray-50 dark:border-[#252525]">
                                            {{ $line->item?->name ?? '—' }}
                                            <input type="hidden" name="kept_items[{{ $idx }}][influencer_product_item_id]" value="{{ $line->id }}">
                                        </td>
                                        <td class="px-4 py-2.5 text-center text-gray-400 border-b border-gray-50 dark:border-[#252525]">{{ $line->remaining_quantity }}</td>
                                        <td class="px-4 py-2.5 text-right border-b border-gray-50 dark:border-[#252525]">
                                            <input type="number" name="kept_items[{{ $idx }}][product_value]" value="{{ $line->product_value }}" min="0" step="0.01" class="h-8 px-2 text-[13px] border border-slate-200 dark:border-[#424242] rounded-md outline-none bg-white dark:bg-[#252525] text-gray-900 dark:text-gray-100 focus:border-blue-500 w-[110px] text-right">
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-end gap-2.5 px-5 py-3 border-t border-gray-100 dark:border-[#2A2A2A] bg-slate-50 dark:bg-[#252525]">
                    <button type="button" class="convert-close h-[30px] px-4 rounded-md text-xs font-medium bg-white dark:bg-[#252525] text-gray-500 border border-slate-200 dark:border-[#424242] cursor-pointer">Anulo</button>
                    <button type="submit" class="h-[30px] px-4 rounded-md text-xs font-medium border-none cursor-pointer text-white bg-blue-500">Konverto</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Extend Deadline Modal --}}
<div id="extend-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" id="extend-overlay"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1E1E1E] rounded-xl border border-slate-200 dark:border-[#2A2A2A] shadow-xl w-full max-w-[420px] max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 dark:border-[#2A2A2A]">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 m-0">@lang('influencer_product.actions.extend')</h3>
                <button type="button" class="extend-close bg-transparent border-none text-gray-400 cursor-pointer">
                    <iconify-icon icon="heroicons-outline:x" class="text-lg"></iconify-icon>
                </button>
            </div>
            <form action="{{ route('marketing.influencer-products.extend', $influencerProduct) }}" method="POST">
                @csrf
                <div class="px-5 py-4">
                    <div>
                        <label class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide block mb-1.5">Data e re e kthimit <span class="text-red-600">*</span></label>
                        <input type="date" name="expected_return_date" min="{{ date('Y-m-d') }}" value="{{ $influencerProduct->expected_return_date?->addDays(14)->format('Y-m-d') ?? date('Y-m-d', strtotime('+14 days')) }}" class="w-full h-9 px-2 text-[13px] border border-slate-200 dark:border-[#424242] rounded-md outline-none bg-white dark:bg-[#252525] text-gray-900 dark:text-gray-100 focus:border-blue-500" required>
                    </div>
                    @if($influencerProduct->expected_return_date)
                        <p class="text-[11px] text-gray-400 mt-2">Afati aktual: {{ $influencerProduct->expected_return_date->format('d/m/Y') }}</p>
                    @endif
                </div>
                <div class="flex items-center justify-end gap-2.5 px-5 py-3 border-t border-gray-100 dark:border-[#2A2A2A] bg-slate-50 dark:bg-[#252525]">
                    <button type="button" class="extend-close h-[30px] px-4 rounded-md text-xs font-medium bg-white dark:bg-[#252525] text-gray-500 border border-slate-200 dark:border-[#424242] cursor-pointer">Anulo</button>
                    <button type="submit" class="h-[30px] px-4 rounded-md text-xs font-medium border-none cursor-pointer text-white bg-blue-500">Zgjat</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('partial-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const activateForm = document.getElementById('activate-form');
    if (activateForm) {
        activateForm.addEventListener('submit', function(e) {
            if (!confirm('Jeni i sigurt qe doni ta aktivizoni? Stoku do te levizet.')) {
                e.preventDefault();
            }
        });
    }

    const returnModal = $('#return-modal');
    $('#btn-register-return').on('click', () => returnModal.removeClass('hidden'));
    $('.return-close, #return-overlay').on('click', () => returnModal.addClass('hidden'));

    const convertModal = $('#convert-modal');
    $('#btn-convert').on('click', () => convertModal.removeClass('hidden'));
    $('.convert-close, #convert-overlay').on('click', () => convertModal.addClass('hidden'));

    const extendModal = $('#extend-modal');
    $('#btn-extend').on('click', () => extendModal.removeClass('hidden'));
    $('.extend-close, #extend-overlay').on('click', () => extendModal.addClass('hidden'));
});
</script>
@endpush
