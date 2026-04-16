@extends('_layouts.app', [
    'title'     => __('influencer_product.add'),
    'pageTitle' => __('influencer_product.add'),
])

@section('header-actions')
    <a href="{{ route('marketing.influencer-products.index') }}" class="inline-flex items-center gap-1 h-[30px] px-2.5 rounded-md border border-slate-200 text-xs text-slate-500 hover:bg-slate-50 transition-colors">
        <iconify-icon icon="heroicons-outline:arrow-left" width="15"></iconify-icon> Kthehu
    </a>
@endsection

@section('content')
<form action="{{ route('marketing.influencer-products.store') }}" method="POST" id="create-form">
    @csrf

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[5fr_2fr] gap-5">
        {{-- Main --}}
        <div class="space-y-5">
            {{-- Details Card --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-slate-100">
                    <div class="w-7 h-7 rounded-lg bg-violet-50 flex items-center justify-center">
                        <iconify-icon icon="heroicons-outline:user" width="14" class="text-violet-600"></iconify-icon>
                    </div>
                    <h2 class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Detajet e Dhënies</h2>
                </div>
                <div class="p-5 space-y-5">
                    {{-- Influencer --}}
                    <div>
                        <label for="influencer_id" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer_product.fields.influencer') <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1">
                                <select name="influencer_id" id="influencer_id" class="w-full h-9 rounded-lg border border-slate-200 text-sm" required>
                                    <option value="">Kërko influencerin...</option>
                                </select>
                            </div>
                            <button type="button" id="btn-new-influencer" class="inline-flex items-center gap-1 h-9 px-3 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:border-primary-500 hover:text-primary-600 transition-colors whitespace-nowrap">
                                <iconify-icon icon="heroicons-outline:plus" width="14"></iconify-icon> I Ri
                            </button>
                        </div>
                        @error('influencer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Branch --}}
                    <div>
                        <label for="source_branch_id" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer_product.fields.branch') <span class="text-red-500">*</span></label>
                        <select name="source_branch_id" id="source_branch_id" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" required>
                            <option value="">Zgjidh degën...</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('source_branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('source_branch_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Warehouse --}}
                    <div>
                        <label for="source_warehouse_id" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer_product.fields.warehouse') <span class="text-red-500">*</span></label>
                        <select name="source_warehouse_id" id="source_warehouse_id" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" required>
                            <option value="">Zgjidh magazinën...</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Zgjidh fillimisht degën për të parë magazinat</p>
                        @error('source_warehouse_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Agreement Type --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">@lang('influencer_product.fields.agreement_type') <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="agreement_type" value="loan" {{ old('agreement_type', 'loan') === 'loan' ? 'checked' : '' }} class="peer absolute opacity-0">
                                <div class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 border-slate-200 bg-white text-center transition-all peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:ring-2 peer-checked:ring-blue-500/20 group-hover:border-slate-300">
                                    <iconify-icon icon="heroicons-outline:arrow-uturn-left" width="20" class="text-blue-500"></iconify-icon>
                                    <span class="text-[13px] font-semibold text-slate-700">@lang('influencer_product.agreement.loan')</span>
                                </div>
                            </label>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="agreement_type" value="gift" {{ old('agreement_type') === 'gift' ? 'checked' : '' }} class="peer absolute opacity-0">
                                <div class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 border-slate-200 bg-white text-center transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:ring-2 peer-checked:ring-emerald-500/20 group-hover:border-slate-300">
                                    <iconify-icon icon="heroicons-outline:gift" width="20" class="text-emerald-500"></iconify-icon>
                                    <span class="text-[13px] font-semibold text-slate-700">@lang('influencer_product.agreement.gift')</span>
                                </div>
                            </label>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="agreement_type" value="tbd" {{ old('agreement_type') === 'tbd' ? 'checked' : '' }} class="peer absolute opacity-0">
                                <div class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 border-slate-200 bg-white text-center transition-all peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:ring-2 peer-checked:ring-amber-500/20 group-hover:border-slate-300">
                                    <iconify-icon icon="heroicons-outline:question-mark-circle" width="20" class="text-amber-500"></iconify-icon>
                                    <span class="text-[13px] font-semibold text-slate-700">@lang('influencer_product.agreement.tbd')</span>
                                </div>
                            </label>
                        </div>
                        @error('agreement_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Date + Notes --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="expected_return_date" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer_product.fields.expected_return_date')</label>
                            <input type="date" name="expected_return_date" id="expected_return_date" value="{{ old('expected_return_date') }}" min="{{ date('Y-m-d') }}"
                                   class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                            @error('expected_return_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div></div>
                    </div>

                    <div>
                        <label for="notes" class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer_product.fields.notes')</label>
                        <textarea name="notes" id="notes" rows="3" placeholder="Shënime rreth kësaj dhënieje..."
                                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 resize-y outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Items Card --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-slate-100">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center">
                        <iconify-icon icon="heroicons-outline:cube" width="14" class="text-blue-600"></iconify-icon>
                    </div>
                    <h2 class="text-xs font-semibold text-slate-600 uppercase tracking-wider">@lang('influencer_product.fields.items')</h2>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Shto artikull</label>
                        <select id="item-search" class="w-full h-9 rounded-lg border border-slate-200 text-sm">
                            <option value="">Kërko artikullin (emri ose SKU)...</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="items-table">
                            <thead>
                                <tr class="bg-slate-50/60 border-b border-slate-100">
                                    <th class="text-center px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[60px]">Foto</th>
                                    <th class="text-left px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Artikulli</th>
                                    <th class="text-left px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[80px]">SKU</th>
                                    <th class="text-center px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[100px]">Sasia</th>
                                    <th class="text-right px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[130px]">Vlera (L)</th>
                                    <th class="w-[50px]"></th>
                                </tr>
                            </thead>
                            <tbody id="items-body"></tbody>
                            <tfoot>
                                <tr class="bg-slate-50/40">
                                    <td colspan="4" class="px-3 py-2.5 text-right text-xs font-semibold text-slate-600">Totali:</td>
                                    <td class="px-3 py-2.5 text-right text-sm font-bold text-primary-600 tabular-nums" id="items-total">0 L</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div id="no-items-message" class="text-center py-8 text-slate-400 text-sm">
                        <iconify-icon icon="heroicons-outline:cube" width="28" class="block mx-auto mb-1.5 text-slate-300"></iconify-icon>
                        Shto artikuj duke përdorur fushën e kërkimit më sipër
                    </div>

                    @error('items') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-2">
                <button type="submit" class="w-full flex items-center justify-center gap-1.5 h-[34px] rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors">
                    <iconify-icon icon="heroicons-outline:check" width="14"></iconify-icon> Ruaj Dhënien
                </button>
                <a href="{{ route('marketing.influencer-products.index') }}" class="w-full flex items-center justify-center h-[30px] rounded-md border border-slate-200 text-xs font-medium text-slate-500 hover:bg-slate-50 transition-colors">
                    Anulo
                </a>
            </div>

            <div class="flex gap-3 p-4 rounded-xl bg-blue-50 border border-blue-100">
                <iconify-icon icon="heroicons-outline:information-circle" width="20" class="text-blue-500 shrink-0 mt-0.5"></iconify-icon>
                <div>
                    <p class="text-[13px] font-semibold text-blue-700 mb-1.5">Si funksionon?</p>
                    <ul class="text-xs text-blue-600 space-y-1 list-disc pl-4">
                        <li>Dhënia krijohet si Draft</li>
                        <li>Pas aktivizimit, stoku lëviz automatikisht</li>
                        <li>Kthimi regjistrohet me sasi dhe gjendje</li>
                        <li>Mund të konvertohet në expense nëse nuk kthehet</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- New Influencer Modal --}}
<div id="new-influencer-modal" class="hidden">
    <div id="new-inf-overlay" class="fixed inset-0 z-[9998] bg-black/40 backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-2xl w-full max-w-[480px] max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 class="text-[15px] font-bold text-slate-900">@lang('influencer.add')</h3>
                <button type="button" id="new-inf-close" class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <iconify-icon icon="heroicons-outline:x-mark" width="18"></iconify-icon>
                </button>
            </div>
            <form id="new-influencer-form">
                @csrf
                <div class="px-5 py-4 space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer.fields.name') <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="new-inf-name" required class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer.fields.platform') <span class="text-red-500">*</span></label>
                        <select name="platform" id="new-inf-platform" required class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="youtube">YouTube</option>
                            <option value="other">@lang('influencer.platform.other')</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer.fields.handle')</label>
                        <input type="text" name="handle" id="new-inf-handle" placeholder="@username" class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer.fields.phone')</label>
                            <input type="text" name="phone" id="new-inf-phone" class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">@lang('influencer.fields.email')</label>
                            <input type="email" name="email" id="new-inf-email" class="w-full h-9 px-3 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/60">
                    <button type="button" id="new-inf-cancel" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Anulo</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition-colors">Ruaj & Zgjidh</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Image Preview --}}
<div class="hidden fixed inset-0 z-[999999] bg-black/80 cursor-pointer items-center justify-center" id="itemPreviewOverlay" onclick="this.classList.add('hidden');this.classList.remove('flex')">
    <span class="absolute top-5 right-8 text-white text-4xl font-light cursor-pointer opacity-80 hover:opacity-100">&times;</span>
    <img src="" id="itemPreviewImg" alt="Preview" referrerpolicy="no-referrer" class="max-w-[85vw] max-h-[90vh] rounded-lg shadow-2xl object-contain">
</div>

<style>
    .select2-container { z-index: 10001 !important; }
    .select2-dropdown { z-index: 10002 !important; }
    .ic-item-thumb { @apply w-10 h-10 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0; }
    .ic-item-thumb img { @apply w-full h-full object-cover; }
    .ic-item-thumb.clickable { @apply cursor-pointer hover:opacity-80 transition-opacity; }
    .ic-no-photo { @apply text-lg text-slate-300; }
    .ic-item-input { @apply h-8 text-center text-sm border border-slate-200 rounded-lg outline-none bg-white text-slate-800 focus:border-primary-500; }
    .ic-remove-btn { @apply bg-transparent border-0 text-slate-400 cursor-pointer hover:text-red-500 transition-colors; }
</style>
@endsection

@push('partial-scripts')
<script>
$(document).ready(function() {
    let itemIndex = 0;
    let addedItems = {};

    $('#influencer_id').select2({
        placeholder: 'Kërko influencerin...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("marketing.influencers.search") }}',
            dataType: 'json', delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => data,
            cache: true
        }
    });

    $('#source_branch_id').on('change', function() {
        const branchId = $(this).val();
        const ws = $('#source_warehouse_id');
        ws.html('<option value="">Duke kërkuar...</option>');
        if (!branchId) { ws.html('<option value="">Zgjidh magazinën...</option>'); return; }
        $.get('{{ route("marketing.influencer-products.warehouses-for-branch") }}', { branch_id: branchId }, function(data) {
            let opts = '<option value="">Zgjidh magazinën...</option>';
            data.forEach(wh => opts += `<option value="${wh.id}">${wh.name}</option>`);
            ws.html(opts);
        });
    });

    $('#item-search').select2({
        placeholder: 'Kërko artikullin (emri ose SKU)...',
        allowClear: true, minimumInputLength: 2,
        ajax: {
            url: '{{ route("marketing.influencer-products.search-items") }}',
            dataType: 'json', delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => data,
            cache: true
        }
    });

    $('#item-search').on('select2:select', function(e) {
        const item = e.params.data;
        if (addedItems[item.id]) { toastr.warning('Ky artikull është shtuar tashmë'); $(this).val(null).trigger('change'); return; }
        addItem(item);
        $(this).val(null).trigger('change');
    });

    function addItem(item) {
        addedItems[item.id] = true;
        const idx = itemIndex++;
        const defaultImg = '{{ asset("assets/images/users/user-1.jpg") }}';
        const thumbUrl = (item.thumbnail && item.thumbnail !== 'null' && item.thumbnail !== '' && item.thumbnail !== defaultImg) ? item.thumbnail : '';
        const fullUrl = (item.full_image && item.full_image !== 'null' && item.full_image !== '' && item.full_image !== defaultImg) ? item.full_image : thumbUrl;
        const hasPhoto = !!thumbUrl;

        let thumbHtml = hasPhoto
            ? `<div class="ic-item-thumb clickable" onclick="previewItemImage('${fullUrl||thumbUrl}')" title="Kliko për parjen"><img src="${thumbUrl}" alt="${item.text}" loading="lazy" referrerpolicy="no-referrer" onerror="this.parentElement.innerHTML='<iconify-icon icon=\\'heroicons-outline:photo\\' class=\\'ic-no-photo\\'></iconify-icon>'"></div>`
            : `<div class="ic-item-thumb"><iconify-icon icon="heroicons-outline:photo" class="ic-no-photo"></iconify-icon></div>`;

        const row = `<tr data-item-id="${item.id}" class="border-b border-slate-50">
            <td class="text-center px-3 py-2">${thumbHtml}</td>
            <td class="px-3 py-2"><span class="font-semibold text-slate-900">${item.text}</span><input type="hidden" name="items[${idx}][item_id]" value="${item.id}"></td>
            <td class="px-3 py-2 font-mono text-[11px] text-slate-400">${item.sku||'—'}</td>
            <td class="text-center px-3 py-2"><input type="number" name="items[${idx}][quantity_given]" value="1" min="1" class="ic-item-input item-qty" style="width:70px" onchange="recalcTotal()"></td>
            <td class="text-right px-3 py-2"><input type="number" name="items[${idx}][product_value]" value="${item.rate||0}" min="0" step="0.01" class="ic-item-input item-value" style="width:110px;text-align:right" onchange="recalcTotal()"></td>
            <td class="text-right px-3 py-2"><button type="button" class="ic-remove-btn" onclick="removeItem(this,${item.id})"><iconify-icon icon="heroicons-outline:trash" width="16"></iconify-icon></button></td>
        </tr>`;
        $('#items-body').append(row);
        $('#no-items-message').addClass('hidden');
        recalcTotal();
    }

    window.removeItem = function(btn, itemId) {
        $(btn).closest('tr').remove();
        delete addedItems[itemId];
        if ($('#items-body tr').length === 0) $('#no-items-message').removeClass('hidden');
        recalcTotal();
    };

    window.recalcTotal = function() {
        let total = 0;
        $('#items-body tr').each(function() {
            total += (parseFloat($(this).find('.item-qty').val())||0) * (parseFloat($(this).find('.item-value').val())||0);
        });
        $('#items-total').text(total.toLocaleString('sq-AL') + ' L');
    };

    const newInfModal = $('#new-influencer-modal');
    $('#btn-new-influencer').on('click', () => newInfModal.removeClass('hidden'));
    $('#new-inf-close, #new-inf-cancel, #new-inf-overlay').on('click', () => newInfModal.addClass('hidden'));

    $('#new-influencer-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '{{ route("marketing.influencers.store") }}',
            method: 'POST', data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success && data.influencer) {
                    const inf = data.influencer;
                    $('#influencer_id').append(new Option(inf.label||inf.name, inf.id, true, true)).trigger('change');
                    newInfModal.addClass('hidden');
                    $('#new-influencer-form')[0].reset();
                    toastr.success('Influenceri u krijua me sukses');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) { let msg=''; Object.values(xhr.responseJSON.errors).forEach(e => msg += e.join('<br>')+'<br>'); toastr.error(msg); }
                else toastr.error('Ndodhi një gabim');
            }
        });
    });

    window.previewItemImage = function(src) {
        if (!src) return;
        document.getElementById('itemPreviewImg').src = src;
        const overlay = document.getElementById('itemPreviewOverlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    };

    $(document).on('keydown', e => { if (e.key==='Escape') { $('#itemPreviewOverlay').addClass('hidden').removeClass('flex'); } });
});
</script>
@endpush
