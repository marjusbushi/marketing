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
    /* Plain CSS. @apply inside a blade <style> block isn't processed
       by Tailwind's JIT, so we avoid it entirely. */
    #influencer-products-table { border-collapse: separate; border-spacing: 0; }
    #influencer-products-table thead th { position: sticky; top: 0; background: #f8fafc; z-index: 1; }
    #influencer-products-table_wrapper .dataTables_processing {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0;
        background: white; font-size: 13px; color: #64748b;
        box-shadow: 0 2px 4px rgba(0,0,0,.04); z-index: 10;
    }
    .dt-footer {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; padding: 10px 16px; border-top: 1px solid #f1f5f9;
        background: #f8fafc; font-size: 12px; color: #64748b;
    }
    .dataTables_paginate { display: flex; align-items: center; gap: 4px; }
    .paginate_button {
        min-width: 30px; height: 30px; display: inline-flex;
        align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid #e2e8f0;
        background: white; color: #64748b; font-size: 12px;
        cursor: pointer; transition: all .15s; padding: 0 8px;
    }
    .paginate_button:hover { border-color: #8b5cf6; color: #7c3aed; }
    .paginate_button.current {
        background: #7c3aed; border-color: #7c3aed;
        color: white; font-weight: 600;
    }
    .paginate_button.disabled { opacity: .4; cursor: default; pointer-events: none; }
    #influencer-products-table thead .sorting:after, #influencer-products-table thead .sorting_asc:after,
    #influencer-products-table thead .sorting_desc:after, #influencer-products-table thead .sorting:before,
    #influencer-products-table thead .sorting_asc:before, #influencer-products-table thead .sorting_desc:before { display: none !important; }
    #influencer-products-table tbody td {
        padding: 10px 16px; color: #334155; font-size: 13px;
        border-bottom: 1px solid #f1f5f9; vertical-align: middle;
    }
    #influencer-products-table tbody tr:hover td { background: #f8fafc; }
    #influencer-products-table tbody tr.overdue-row td { background: rgba(239,68,68,.04); }
    #influencer-products-table tbody tr.overdue-row:hover td { background: rgba(239,68,68,.08); }

    .ip-serial { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; font-weight: 600; color: #7c3aed; }
    .ip-value  { font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; color: #0f172a; }

    .ip-badge {
        display: inline-flex; align-items: center;
        padding: 3px 10px; border-radius: 9999px;
        font-size: 11px; font-weight: 600; white-space: nowrap;
    }
    .ip-badge-draft   { background: #f1f5f9; color: #475569; }
    .ip-badge-warning { background: #fef3c7; color: #b45309; }
    .ip-badge-info    { background: #dbeafe; color: #1d4ed8; }
    .ip-badge-purple  { background: #ede9fe; color: #6d28d9; }
    .ip-badge-success { background: #d1fae5; color: #047857; }
    .ip-badge-primary { background: #f5f3ff; color: #6d28d9; }
    .ip-badge-danger  { background: #fee2e2; color: #b91c1c; }
    .ip-badge-secondary { background: #f1f5f9; color: #64748b; }

    .ip-count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 26px; height: 22px; padding: 0 8px;
        border-radius: 9999px; background: #f5f3ff; color: #6d28d9;
        font-size: 12px; font-weight: 600;
    }

    .ip-row-action {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 6px;
        color: #94a3b8; transition: all .15s;
    }
    .ip-row-action:hover { background: #f1f5f9; color: #7c3aed; }
    .ip-row-action.success { color: #10b981; }
    .ip-row-action.success:hover { background: #ecfdf5; color: #059669; }
    .ip-row-action.danger { color: #ef4444; }
    .ip-row-action.danger:hover { background: #fee2e2; color: #dc2626; }

    .ip-date-overdue { color: #dc2626; font-weight: 600; font-size: 12px; white-space: nowrap; }
    .ip-date         { color: #64748b; font-size: 12px; white-space: nowrap; }
    .ip-date-faded   { color: #cbd5e1; font-size: 12px; white-space: nowrap; }
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
        success:'ip-badge-success', primary:'ip-badge-primary', danger:'ip-badge-danger',
        draft:'ip-badge-draft', secondary:'ip-badge-secondary'
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
            { data:'serial', name:'serial', render: d => `<span class="ip-serial">${d}</span>` },
            { data:null, name:'influencer_name', render: d => {
                let h = `<div style="font-weight:600;color:#0f172a;font-size:13px">${d.influencer_name}</div>`;
                if (d.influencer_handle && d.influencer_handle !== '-') {
                    h += `<div style="font-size:11px;color:#94a3b8;margin-top:2px">@${String(d.influencer_handle).replace(/^@+/, '')}</div>`;
                }
                return h;
            } },
            { data:'branch_name', name:'branch_name', render: d => `<span style="color:#475569;font-size:13px">${d}</span>` },
            { data:'items_count', className:'text-center', searchable:false, render: d => `<span class="ip-count">${d}</span>` },
            { data:'total_value_formatted', className:'text-right', searchable:false, render: d => `<span class="ip-value">${d}</span>` },
            { data:null, className:'text-center', searchable:false, render: d => `<span class="ip-badge ${badgeMap[d.status_color]||'ip-badge-info'}">${d.status_label}</span>` },
            { data:null, className:'text-center', searchable:false, render: d => `<span class="ip-badge ${badgeMap[d.agreement_color]||'ip-badge-info'}">${d.agreement_label}</span>` },
            { data:'expected_return_formatted', searchable:false, render: (d,t,row) => {
                if (d === '-') return '<span class="ip-date-faded">—</span>';
                return row.is_overdue
                    ? `<span class="ip-date-overdue">${d}</span>`
                    : `<span class="ip-date">${d}</span>`;
            } },
            { data:'created_at_formatted', name:'created_at', searchable:false, render: d => `<span class="ip-date">${d}</span>` },
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
