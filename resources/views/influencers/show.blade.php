@extends('_layouts.app', [
    'title'           => $influencer->name,
    'pageTitle'       => __('influencer.view'),
    'container_class' => 'container-fluid zoho-page',
])

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/zoho-theme.css') }}">
    <style>
        .is-page { margin-top: 16px; padding-bottom: 40px; }

        /* Header */
        .is-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .is-header-left { display: flex; align-items: center; gap: 10px; }
        .is-header h1 {
            font-size: 20px; font-weight: 700; color: #212121; margin: 0;
            display: flex; align-items: center; gap: 8px;
        }
        .dark .is-header h1 { color: #F5F5F5; }
        .is-status {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;
        }
        .is-status-active { background: #E8F5E9; color: #2E7D32; }
        .is-status-active::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #2E7D32; }
        .is-status-inactive { background: #ECEFF1; color: #546E7A; }
        .is-back-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 500;
            border: 1px solid #E0E0E0; color: #424242; background: #fff;
            text-decoration: none; transition: all .15s ease;
        }
        .is-back-btn:hover { border-color: #1E88E5; color: #1E88E5; text-decoration: none; }
        .dark .is-back-btn { background: #1E1E1E; border-color: #424242; color: #B0B0B0; }

        /* Stats */
        .is-stats {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
            margin-bottom: 16px;
        }
        .is-stat {
            background: #fff; border: 1px solid #E0E0E0; border-radius: 8px;
            padding: 16px;
        }
        .dark .is-stat { background: #1E1E1E; border-color: #2A2A2A; }
        .is-stat-label {
            font-size: 11px; font-weight: 600; color: #9E9E9E;
            text-transform: uppercase; letter-spacing: .3px;
        }
        .is-stat-value { font-size: 26px; font-weight: 700; color: #212121; line-height: 1.2; margin-top: 6px; }
        .dark .is-stat-value { color: #F5F5F5; }

        /* Grid layout */
        .is-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 16px;
        }

        /* Card */
        .is-card {
            background: #fff; border: 1px solid #E0E0E0; border-radius: 8px;
            overflow: hidden;
        }
        .dark .is-card { background: #1E1E1E; border-color: #2A2A2A; }
        .is-card-header {
            display: flex; align-items: center; gap: 8px;
            padding: 14px 16px 12px; border-bottom: 1px solid #F0F0F0;
        }
        .dark .is-card-header { border-color: #2A2A2A; }
        .is-card-header h2 {
            font-size: 13px; font-weight: 600; color: #424242; margin: 0;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .dark .is-card-header h2 { color: #B0B0B0; }
        .is-card-icon {
            width: 28px; height: 28px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .is-card-body { padding: 16px; }

        /* Table */
        .is-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .is-table th {
            padding: 8px 16px; text-align: left;
            font-size: 11px; font-weight: 600; color: #9E9E9E;
            text-transform: uppercase; letter-spacing: .3px;
            border-bottom: 1px solid #F0F0F0; background: #FAFAFA;
        }
        .dark .is-table th { background: #252525; color: #616161; border-color: #2A2A2A; }
        .is-table td {
            padding: 10px 16px; color: #424242;
            border-bottom: 1px solid #F5F5F5; vertical-align: middle;
        }
        .dark .is-table td { color: #D0D0D0; border-color: #252525; }
        .is-table tr:last-child td { border-bottom: none; }
        .is-table tr:hover td { background: #FAFAFA; }
        .dark .is-table tr:hover td { background: #252525; }

        /* Serial link */
        .is-serial {
            color: #1E88E5; font-weight: 600; text-decoration: none;
            font-size: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .is-serial:hover { text-decoration: underline; }

        /* Badge */
        .is-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;
        }
        .is-badge::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: currentColor; opacity: .6;
        }

        /* Profile detail rows */
        .is-detail { padding: 10px 0; }
        .is-detail + .is-detail { border-top: 1px solid #F5F5F5; }
        .dark .is-detail + .is-detail { border-color: #2A2A2A; }
        .is-detail-label {
            font-size: 11px; font-weight: 600; color: #9E9E9E;
            text-transform: uppercase; letter-spacing: .3px;
        }
        .is-detail-value { margin-top: 3px; font-size: 13px; color: #424242; }
        .dark .is-detail-value { color: #D0D0D0; }

        /* Profile avatar */
        .is-profile-row {
            display: flex; align-items: center; gap: 12px;
            padding-bottom: 12px; border-bottom: 1px solid #F0F0F0;
            margin-bottom: 4px;
        }
        .dark .is-profile-row { border-color: #2A2A2A; }
        .is-profile-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: #E3F2FD; display: flex; align-items: center;
            justify-content: center; color: #1E88E5; font-size: 20px;
        }
        .is-profile-name { font-size: 15px; font-weight: 700; color: #212121; }
        .dark .is-profile-name { color: #F5F5F5; }
        .is-profile-handle { font-size: 12px; color: #9E9E9E; }

        /* Empty state */
        .is-empty {
            text-align: center; padding: 40px 16px; color: #9E9E9E; font-size: 13px;
        }
        .is-empty iconify-icon { font-size: 32px; display: block; margin: 0 auto 8px; color: #BDBDBD; }

        @media (max-width: 1100px) {
            .is-stats { grid-template-columns: repeat(2, 1fr); }
            .is-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .is-stats { grid-template-columns: 1fr; }
            .is-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
@endsection

@section('breadcrumb')
    <li class="inline-block relative top-[3px] text-base text-primary-500 font-Inter">
        <a href="{{ route('marketing.dashboard') }}">
            <iconify-icon icon="heroicons-outline:home"></iconify-icon>
            <iconify-icon icon="heroicons-outline:chevron-right" class="relative text-slate-500 text-sm rtl:rotate-180"></iconify-icon>
        </a>
    </li>
    <li class="inline-block relative text-sm text-primary-500 font-Inter">
        <a href="{{ route('marketing.influencers.index') }}">
            @lang('influencer.title')
            <iconify-icon icon="heroicons-outline:chevron-right" class="relative top-[3px] text-slate-500 rtl:rotate-180"></iconify-icon>
        </a>
    </li>
    <li class="inline-block relative text-sm text-slate-500 font-Inter dark:text-white">{{ $influencer->name }}</li>
@endsection

@section('content')
@php
    $products = $influencer->influencerProducts;
    $totalProducts = $products->count();
    $activeProducts = $products->whereIn('status', [\App\Enums\InfluencerProductStatusEnum::ACTIVE, \App\Enums\InfluencerProductStatusEnum::PARTIALLY_RETURNED])->count();
    $returnedProducts = $products->where('status', \App\Enums\InfluencerProductStatusEnum::RETURNED)->count();
    $convertedProducts = $products->where('status', \App\Enums\InfluencerProductStatusEnum::CONVERTED)->count();
@endphp

<div class="is-page">

    {{-- Header --}}
    <div class="is-header">
        <div class="is-header-left">
            <h1>
                <iconify-icon icon="heroicons-outline:user" style="vertical-align:-3px;"></iconify-icon>
                {{ $influencer->name }}
            </h1>
            @if($influencer->is_active)
                <span class="is-status is-status-active">@lang('influencer.status.active')</span>
            @else
                <span class="is-status is-status-inactive">@lang('influencer.status.inactive')</span>
            @endif
        </div>
        <a href="{{ route('marketing.influencers.index') }}" class="is-back-btn">
            <iconify-icon icon="heroicons-outline:arrow-left" style="font-size:14px;"></iconify-icon>
            Kthehu
        </a>
    </div>

    {{-- Stats --}}
    <div class="is-stats">
        <div class="is-stat">
            <div class="is-stat-label">Totali Produkteve</div>
            <div class="is-stat-value">{{ $totalProducts }}</div>
        </div>
        <div class="is-stat">
            <div class="is-stat-label">Aktive</div>
            <div class="is-stat-value" style="color:#1E88E5;">{{ $activeProducts }}</div>
        </div>
        <div class="is-stat">
            <div class="is-stat-label">Kthyer</div>
            <div class="is-stat-value" style="color:#2E7D32;">{{ $returnedProducts }}</div>
        </div>
        <div class="is-stat">
            <div class="is-stat-label">Konvertuar</div>
            <div class="is-stat-value" style="color:#7B1FA2;">{{ $convertedProducts }}</div>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="is-grid">
        {{-- Products Table --}}
        <div>
            <div class="is-card">
                <div class="is-card-header">
                    <div class="is-card-icon" style="background:#E3F2FD;">
                        <iconify-icon icon="heroicons-outline:cube" style="color:#1E88E5;"></iconify-icon>
                    </div>
                    <h2>Produktet e Dhena</h2>
                </div>
                @if($products->count() > 0)
                    <table class="is-table">
                        <thead>
                            <tr>
                                <th>@lang('influencer_product.fields.serial')</th>
                                <th>@lang('influencer_product.fields.branch')</th>
                                <th class="text-center">@lang('influencer_product.fields.items_count')</th>
                                <th class="text-center">@lang('influencer_product.fields.agreement')</th>
                                <th class="text-center">Status</th>
                                <th class="text-right">@lang('influencer_product.fields.date')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>
                                        <a href="{{ route('marketing.influencer-products.show', $product) }}" class="is-serial">{{ $product->serial }}</a>
                                    </td>
                                    <td style="color:#9E9E9E;">{{ $product->branch?->name ?? '—' }}</td>
                                    <td class="text-center">{{ $product->items->count() }}</td>
                                    <td class="text-center">
                                        <span class="is-badge" style="background:#{{ $product->agreement_type->color() === 'info' ? 'E3F2FD' : ($product->agreement_type->color() === 'success' ? 'E8F5E9' : 'FFF3E0') }};color:#{{ $product->agreement_type->color() === 'info' ? '1565C0' : ($product->agreement_type->color() === 'success' ? '2E7D32' : 'E65100') }};">
                                            {{ $product->agreement_type->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="is-badge" style="background:#{{ $product->status->color() === 'warning' ? 'FFF3E0' : ($product->status->color() === 'success' ? 'E8F5E9' : ($product->status->color() === 'danger' ? 'FFEBEE' : ($product->status->color() === 'purple' ? 'F3E5F5' : ($product->status->color() === 'info' ? 'E3F2FD' : 'ECEFF1')))) }};color:#{{ $product->status->color() === 'warning' ? 'E65100' : ($product->status->color() === 'success' ? '2E7D32' : ($product->status->color() === 'danger' ? 'C62828' : ($product->status->color() === 'purple' ? '7B1FA2' : ($product->status->color() === 'info' ? '1565C0' : '546E7A')))) }};">
                                            {{ $product->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-right" style="font-size:12px;color:#9E9E9E;">{{ $product->created_at?->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="is-empty">
                        <iconify-icon icon="heroicons-outline:cube"></iconify-icon>
                        <p>Nuk ka produkte ende</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div style="display:flex;flex-direction:column;gap:16px;">
            {{-- Profile Card --}}
            <div class="is-card">
                <div class="is-card-header">
                    <div class="is-card-icon" style="background:#E3F2FD;">
                        <iconify-icon icon="heroicons-outline:user" style="color:#1E88E5;"></iconify-icon>
                    </div>
                    <h2>Profili</h2>
                </div>
                <div class="is-card-body">
                    <div class="is-profile-row">
                        <div class="is-profile-avatar">
                            <iconify-icon icon="{{ $influencer->platform->icon() }}"></iconify-icon>
                        </div>
                        <div>
                            <div class="is-profile-name">{{ $influencer->name }}</div>
                            @if($influencer->handle)
                                <div class="is-profile-handle">@{{ ltrim($influencer->handle, '@') }}</div>
                            @endif
                        </div>
                    </div>

                    @if($influencer->phone)
                    <div class="is-detail">
                        <div class="is-detail-label">@lang('influencer.fields.phone')</div>
                        <div class="is-detail-value">{{ $influencer->phone }}</div>
                    </div>
                    @endif

                    @if($influencer->email)
                    <div class="is-detail">
                        <div class="is-detail-label">@lang('influencer.fields.email')</div>
                        <div class="is-detail-value">{{ $influencer->email }}</div>
                    </div>
                    @endif

                    <div class="is-detail">
                        <div class="is-detail-label">@lang('influencer.fields.platform')</div>
                        <div class="is-detail-value" style="display:flex;align-items:center;gap:6px;">
                            <iconify-icon icon="{{ $influencer->platform->icon() }}" style="font-size:16px;color:#9E9E9E;"></iconify-icon>
                            {{ $influencer->platform->label() }}
                        </div>
                    </div>

                    <div class="is-detail">
                        <div class="is-detail-label">@lang('influencer.fields.created_at')</div>
                        <div class="is-detail-value" style="font-size:12px;color:#9E9E9E;">
                            {{ $influencer->created_at?->format('d/m/Y H:i') }}
                            @if($influencer->createdBy)
                                &middot; {{ $influencer->createdBy->full_name }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            @if($influencer->notes)
            <div class="is-card">
                <div class="is-card-header">
                    <div class="is-card-icon" style="background:#FFF3E0;">
                        <iconify-icon icon="heroicons-outline:document-text" style="color:#E65100;"></iconify-icon>
                    </div>
                    <h2>@lang('influencer.fields.notes')</h2>
                </div>
                <div class="is-card-body">
                    <p style="font-size:13px;color:#616161;white-space:pre-wrap;margin:0;">{{ $influencer->notes }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
