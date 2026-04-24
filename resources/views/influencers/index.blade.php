@extends('_layouts.app', [
    'title'     => 'Influencers',
    'pageTitle' => 'Influencers',
])

@section('header-actions')
    <button type="button" id="btn-add-influencer" class="inline-flex items-center gap-1.5 h-[30px] px-3.5 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors">
        <iconify-icon icon="heroicons-outline:plus" width="16"></iconify-icon>
        Shto Influencer
    </button>
@endsection

@section('content')
<div class="space-y-4">

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/60">
            {{-- Search --}}
            <div class="relative flex-1 min-w-[200px] max-w-[300px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="dt-search" placeholder="Kërko..."
                       class="w-full h-[30px] pl-8 pr-3 rounded-md border border-slate-200 bg-white text-xs text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
            </div>

            {{-- Platform --}}
            <select id="filter-platform" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                <option value="">Të gjitha platformat</option>
                <option value="instagram">Instagram</option>
                <option value="tiktok">TikTok</option>
                <option value="youtube">YouTube</option>
                <option value="other">Tjetër</option>
            </select>

            {{-- Status --}}
            <select id="filter-status" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                <option value="">Aktiv / Joaktiv</option>
                <option value="1">Aktiv</option>
                <option value="0">Joaktiv</option>
            </select>

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
            <table id="influencers-table" class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-slate-100">
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Emri</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Platforma</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Handle</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Telefon</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Produkte</th>
                        <th class="text-center px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Krijuar</th>
                        <th class="text-right px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create / Edit Modal --}}
<div id="influencer-modal" class="hidden">
    {{-- Overlay --}}
    <div id="modal-overlay" class="fixed inset-0 z-[9998] bg-black/40 backdrop-blur-sm"></div>

    {{-- Modal --}}
    <div class="fixed inset-0 z-[9999] flex items-start justify-center p-[5vh_1rem] overflow-y-auto">
        <div class="bg-white rounded-xl border border-slate-200 shadow-2xl w-full max-w-[480px] animate-[slideIn_0.2s_ease-out]">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 id="modal-title" class="text-[15px] font-bold text-slate-900">Shto Influencer</h3>
                <button type="button" id="modal-close" class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <iconify-icon icon="heroicons-outline:x-mark" width="18"></iconify-icon>
                </button>
            </div>

            {{-- Form --}}
            <form id="influencer-form" method="POST">
                @csrf
                <input type="hidden" id="form-method" name="_method" value="POST">

                <div class="px-5 py-4 space-y-4">
                    <div>
                        <label for="inf-name" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Emri <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="inf-name" required
                               class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm text-slate-800 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                    </div>

                    <div>
                        <label for="inf-platform" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Platforma <span class="text-red-500">*</span></label>
                        <select name="platform" id="inf-platform" required
                                class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm text-slate-800 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="youtube">YouTube</option>
                            <option value="other">Tjetër</option>
                        </select>
                    </div>

                    <div>
                        <label for="inf-handle" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Handle</label>
                        <input type="text" name="handle" id="inf-handle" placeholder="@username"
                               class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm text-slate-800 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="inf-phone" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Telefon</label>
                            <input type="text" name="phone" id="inf-phone"
                                   class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm text-slate-800 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                        </div>
                        <div>
                            <label for="inf-email" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                            <input type="email" name="email" id="inf-email"
                                   class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm text-slate-800 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
                        </div>
                    </div>

                    <div>
                        <label for="inf-notes" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Shënime</label>
                        <textarea name="notes" id="inf-notes" rows="2"
                                  class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-800 resize-y focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none"></textarea>
                    </div>

                    <div class="hidden" id="active-toggle-row">
                        <label class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Aktiv</span>
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="inf-is-active" value="1" checked
                                   class="w-4 h-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        </label>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/60">
                    <button type="button" id="modal-cancel" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Anulo</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition-colors">Ruaj</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* DataTables minimal overrides */
    #influencers-table_wrapper .dataTables_processing { @apply absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-500 shadow-sm; }
    .dt-footer { @apply flex items-center justify-between gap-2 px-4 py-2.5 border-t border-slate-100 bg-slate-50/60 text-xs text-slate-500; }
    .dataTables_paginate { @apply flex items-center gap-1; }
    .paginate_button { @apply min-w-[30px] h-[30px] inline-flex items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 text-xs cursor-pointer transition-colors hover:border-primary-500 hover:text-primary-600; }
    .paginate_button.current { @apply bg-primary-600 border-primary-600 text-white font-semibold; }
    .paginate_button.disabled { @apply opacity-40 cursor-default; }
    #influencers-table thead .sorting:after, #influencers-table thead .sorting_asc:after,
    #influencers-table thead .sorting_desc:after, #influencers-table thead .sorting:before,
    #influencers-table thead .sorting_asc:before, #influencers-table thead .sorting_desc:before { display: none !important; }
    #influencers-table tbody td { @apply px-4 py-2.5 text-slate-700 border-b border-slate-50 align-middle; }
    #influencers-table tbody tr:hover td { @apply bg-slate-50/60; }
    .status-dot { @apply inline-block w-2 h-2 rounded-full; }
    .status-dot.active { @apply bg-emerald-500; }
    .status-dot.inactive { @apply bg-slate-300; }
    .products-badge { @apply inline-flex items-center justify-center min-w-[26px] h-[22px] px-2 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold; }
    @keyframes slideIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
</style>
@endsection

@push('partial-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = $('#influencers-table').DataTable({
        dom: 'rt<"dt-footer"ip>',
        processing: true,
        serverSide: true,
        ordering: false,
        ajax: {
            url: '{{ route('marketing.influencers.index') }}',
            data: function(d) {
                d.platform = $('#filter-platform').val();
                d.is_active = $('#filter-status').val();
            }
        },
        columns: [
            {
                data: 'name', name: 'name',
                render: data => `<span class="font-semibold text-slate-900">${data}</span>`
            },
            {
                data: null, name: 'platform',
                render: data => `<span class="inline-flex items-center gap-1.5"><iconify-icon icon="${data.platform_icon}" width="15" class="text-slate-400"></iconify-icon><span class="text-slate-600">${data.platform_label}</span></span>`
            },
            {
                data: 'handle', name: 'handle',
                render: data => data ? `<span class="text-slate-500">@${String(data).replace(/^@+/, '')}</span>` : '<span class="text-slate-300">—</span>'
            },
            {
                data: 'phone', name: 'phone',
                render: data => data || '<span class="text-slate-300">—</span>'
            },
            {
                data: 'active_products_count', className: 'text-center', searchable: false,
                render: data => `<span class="products-badge">${data || 0}</span>`
            },
            {
                data: 'is_active', className: 'text-center',
                render: data => `<span class="status-dot ${data ? 'active' : 'inactive'}"></span>`
            },
            {
                data: 'created_at_formatted', name: 'created_at', searchable: false,
                render: data => `<span class="text-xs text-slate-400">${data}</span>`
            },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-right' }
        ],
        pageLength: 25,
        language: {
            processing: '<span class="text-slate-500">Po ngarkohet...</span>',
            emptyTable: '<div class="py-8 text-center text-slate-400 text-sm">Nuk ka influencer</div>',
            zeroRecords: '<div class="py-8 text-center text-slate-400 text-sm">Nuk u gjetën rezultate</div>',
            info: 'Po shfaqen _START_ - _END_ nga _TOTAL_',
            infoEmpty: 'Nuk ka të dhëna',
            paginate: { previous: '←', next: '→' }
        }
    });

    let searchTimeout;
    $('#dt-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        const value = this.value;
        searchTimeout = setTimeout(() => table.search(value).draw(), 300);
    });

    $('#filter-platform, #filter-status').on('change', () => table.ajax.reload());
    $('#clear-filters').on('click', function() { $('#dt-search').val(''); $('#filter-platform, #filter-status').val(''); table.search('').ajax.reload(); });
    $('#refresh-table').on('click', () => table.ajax.reload());

    // Modal
    const modal = $('#influencer-modal');
    const form = $('#influencer-form');

    function openModal(mode, data) {
        form[0].reset();
        if (mode === 'edit' && data) {
            $('#modal-title').text('Ndrysho Influencer');
            $('#form-method').val('PUT');
            form.attr('action', `{{ url('marketing/influencers') }}/${data.id}`);
            $('#inf-name').val(data.name);
            $('#inf-platform').val(data.platform);
            $('#inf-handle').val(data.handle);
            $('#inf-phone').val(data.phone);
            $('#inf-email').val(data.email);
            $('#inf-notes').val(data.notes);
            $('#inf-is-active').prop('checked', data.is_active);
            $('#active-toggle-row').removeClass('hidden');
        } else {
            $('#modal-title').text('Shto Influencer');
            $('#form-method').val('POST');
            form.attr('action', '{{ route('marketing.influencers.store') }}');
            $('#active-toggle-row').addClass('hidden');
        }
        modal.removeClass('hidden');
    }

    function closeModal() { modal.addClass('hidden'); }

    $('#btn-add-influencer').on('click', () => openModal('create'));
    $('#modal-close, #modal-cancel, #modal-overlay').on('click', closeModal);

    form.on('submit', function(e) {
        e.preventDefault();
        const url = form.attr('action');
        const method = $('#form-method').val();
        $.ajax({
            url: url,
            method: method === 'PUT' ? 'POST' : 'POST',
            data: form.serialize(),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) { closeModal(); table.ajax.reload(null, false); toastr.success(data.message || 'U ruajt me sukses'); }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    let msg = '';
                    Object.values(errors).forEach(e => msg += e.join('<br>') + '<br>');
                    toastr.error(msg);
                } else { toastr.error('Ndodhi një gabim'); }
            }
        });
    });

    window.editInfluencer = function(id) {
        $.get(`{{ url('marketing/influencers') }}/${id}`, function(data) {
            openModal('edit', data.influencer || data);
        });
    };
});
</script>
@endpush
