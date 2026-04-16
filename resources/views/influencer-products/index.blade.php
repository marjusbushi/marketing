@extends('_layouts.app', [
    'title'     => 'Influencer Products',
    'pageTitle' => 'Influencer Products',
])

@section('header-actions')
    <a href="{{ route('marketing.influencer-products.create') }}" class="inline-flex items-center gap-1.5 h-[30px] px-3.5 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors">
        <iconify-icon icon="heroicons-outline:plus" width="16"></iconify-icon>
        Shto Produkt
    </a>
@endsection

@section('content')
<div class="space-y-4">

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/60">
            {{-- Search --}}
            <div class="relative flex-1 min-w-[180px] max-w-[260px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="dt-search" placeholder="Kërko..."
                       class="w-full h-[30px] pl-8 pr-3 rounded-md border border-slate-200 bg-white text-xs text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
            </div>

            <select id="filter-status" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                <option value="">Statusi</option>
                <option value="draft">Draft</option>
                <option value="active">Aktiv</option>
                <option value="partially_returned">Kthyer Pjesërisht</option>
                <option value="returned">Kthyer</option>
                <option value="converted">Konvertuar</option>
                <option value="cancelled">Anulluar</option>
            </select>

            <select id="filter-branch" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                <option value="">Dega</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>

            <select id="filter-agreement" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                <option value="">Marrëveshja</option>
                <option value="loan">Huazim</option>
                <option value="gift">Dhuratë</option>
                <option value="tbd">Pa vendosur</option>
            </select>

            <select id="filter-influencer" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none" style="width:180px;">
                <option value="">Influencer</option>
            </select>

            <label class="flex items-center gap-2 h-[30px] px-2.5 rounded-md border border-slate-200 bg-white text-xs text-slate-600 cursor-pointer select-none hover:border-slate-300">
                <input type="checkbox" id="filter-overdue" class="w-3.5 h-3.5 rounded border-slate-300 text-red-600 focus:ring-red-500">
                <span>Vonuar</span>
            </label>

            <div class="flex items-center gap-2">
                <input type="date" id="filter-date-from" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20">
                <input type="date" id="filter-date-to" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20">
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button type="button" id="clear-filters" class="h-[30px] w-[30px] inline-flex items-center justify-center rounded-md border border-slate-200 text-slate-400 hover:text-slate-600 hover:border-slate-300 transition-colors" title="Pastro">
                    <iconify-icon icon="heroicons-outline:x-mark" width="16"></iconify-icon>
                </button>
                <button type="button" id="refresh-table" class="h-[30px] w-[30px] inline-flex items-center justify-center rounded-md border border-slate-200 text-slate-400 hover:text-slate-600 hover:border-slate-300 transition-colors" title="Rifresko">
                    <iconify-icon icon="heroicons-outline:arrow-path" width="16"></iconify-icon>
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table id="influencer-products-table" class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-slate-100">
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Serial</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Influencer</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Dega</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Artikuj</th>
                        <th class="text-right px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Vlera</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Marrëveshja</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Kthim</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Krijuar</th>
                        <th class="text-right px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<style>
    #influencer-products-table_wrapper .dataTables_processing { @apply absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-500 shadow-sm; }
    .dt-footer { @apply flex items-center justify-between gap-2 px-4 py-2.5 border-t border-slate-100 bg-slate-50/60 text-xs text-slate-500; }
    .dataTables_paginate { @apply flex items-center gap-1; }
    .paginate_button { @apply min-w-[30px] h-[30px] inline-flex items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 text-xs cursor-pointer transition-colors hover:border-primary-500 hover:text-primary-600; }
    .paginate_button.current { @apply bg-primary-600 border-primary-600 text-white font-semibold; }
    .paginate_button.disabled { @apply opacity-40 cursor-default; }
    #influencer-products-table thead .sorting:after, #influencer-products-table thead .sorting_asc:after,
    #influencer-products-table thead .sorting_desc:after, #influencer-products-table thead .sorting:before,
    #influencer-products-table thead .sorting_asc:before, #influencer-products-table thead .sorting_desc:before { display: none !important; }
    #influencer-products-table tbody td { @apply px-4 py-2.5 text-slate-700 border-b border-slate-50 align-middle; }
    #influencer-products-table tbody tr:hover td { @apply bg-slate-50/60; }
    #influencer-products-table tbody tr.overdue-row td { background: rgba(239,68,68,.04); }
    #influencer-products-table tbody tr.overdue-row:hover td { background: rgba(239,68,68,.08); }
    .ip-badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap; }
    .ip-badge-draft { @apply bg-slate-100 text-slate-600; }
    .ip-badge-warning { @apply bg-amber-50 text-amber-700; }
    .ip-badge-info { @apply bg-blue-50 text-blue-700; }
    .ip-badge-purple { @apply bg-violet-50 text-violet-700; }
    .ip-badge-success { @apply bg-emerald-50 text-emerald-700; }
    .ip-badge-primary { @apply bg-primary-50 text-primary-700; }
    .ip-badge-danger { @apply bg-red-50 text-red-700; }
    .ip-count { @apply inline-flex items-center justify-center min-w-[26px] h-[22px] px-2 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold; }
</style>
@endsection

@push('partial-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#filter-influencer').select2({
        placeholder: 'Influencer',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("marketing.influencers.search") }}',
            dataType: 'json',
            delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => data,
            cache: true
        }
    });

    const badgeMap = {
        warning:'ip-badge-warning', info:'ip-badge-info', purple:'ip-badge-purple',
        success:'ip-badge-success', primary:'ip-badge-primary', danger:'ip-badge-danger', draft:'ip-badge-draft'
    };

    const table = $('#influencer-products-table').DataTable({
        dom: 'rt<"dt-footer"ip>',
        processing: true,
        serverSide: true,
        ordering: false,
        ajax: {
            url: '{{ route('marketing.influencer-products.index') }}',
            data: function(d) {
                d.status = $('#filter-status').val();
                d.branch_id = $('#filter-branch').val();
                d.agreement_type = $('#filter-agreement').val();
                d.influencer_id = $('#filter-influencer').val();
                d.overdue_only = $('#filter-overdue').is(':checked') ? 1 : 0;
                d.date_from = $('#filter-date-from').val();
                d.date_to = $('#filter-date-to').val();
            }
        },
        columns: [
            { data:'serial', name:'serial', render: d => `<span class="font-mono text-xs font-semibold text-primary-600">${d}</span>` },
            { data:null, name:'influencer_name', render: d => { let h = `<span class="font-semibold text-slate-900">${d.influencer_name}</span>`; if(d.influencer_handle && d.influencer_handle!=='-') h += `<br><span class="text-[11px] text-slate-400">@${d.influencer_handle}</span>`; return h; } },
            { data:'branch_name', name:'branch_name', render: d => `<span class="text-slate-600">${d}</span>` },
            { data:'items_count', className:'text-center', searchable:false, render: d => `<span class="ip-count">${d}</span>` },
            { data:'total_value_formatted', className:'text-right', searchable:false, render: d => `<span class="font-semibold tabular-nums">${d}</span>` },
            { data:null, className:'text-center', searchable:false, render: d => `<span class="ip-badge ${badgeMap[d.status_color]||'ip-badge-info'}">${d.status_label}</span>` },
            { data:null, className:'text-center', searchable:false, render: d => `<span class="ip-badge ${badgeMap[d.agreement_color]||'ip-badge-info'}">${d.agreement_label}</span>` },
            { data:'expected_return_formatted', searchable:false, render: (d,t,row) => { if(d==='-') return '<span class="text-slate-300">—</span>'; if(row.is_overdue) return `<span class="text-xs font-semibold text-red-600">${d}</span>`; return `<span class="text-xs text-slate-400">${d}</span>`; } },
            { data:'created_at_formatted', name:'created_at', searchable:false, render: d => `<span class="text-xs text-slate-400">${d}</span>` },
            { data:'actions', name:'actions', orderable:false, searchable:false, className:'text-right' }
        ],
        pageLength: 25,
        language: {
            processing: '<span class="text-slate-500">Po ngarkohet...</span>',
            emptyTable: '<div class="py-8 text-center text-slate-400 text-sm">Nuk ka produkte</div>',
            zeroRecords: '<div class="py-8 text-center text-slate-400 text-sm">Nuk u gjetën rezultate</div>',
            info: '_START_ - _END_ nga _TOTAL_',
            infoEmpty: 'Nuk ka të dhëna',
            paginate: { previous: '←', next: '→' }
        },
        createdRow: (row, data) => { if(data.is_overdue) $(row).addClass('overdue-row'); }
    });

    let searchTimeout;
    $('#dt-search').on('keyup', function() { clearTimeout(searchTimeout); const v = this.value; searchTimeout = setTimeout(() => table.search(v).draw(), 300); });
    $('#filter-status, #filter-branch, #filter-agreement').on('change', () => table.ajax.reload());
    $('#filter-influencer').on('change', () => table.ajax.reload());
    $('#filter-overdue').on('change', () => table.ajax.reload());
    $('#filter-date-from, #filter-date-to').on('change', () => table.ajax.reload());
    $('#clear-filters').on('click', function() { $('#dt-search').val(''); $('#filter-status, #filter-branch, #filter-agreement').val(''); $('#filter-influencer').val(null).trigger('change'); $('#filter-overdue').prop('checked',false); $('#filter-date-from, #filter-date-to').val(''); table.search('').ajax.reload(); });
    $('#refresh-table').on('click', () => table.ajax.reload());
});
</script>
@endpush
