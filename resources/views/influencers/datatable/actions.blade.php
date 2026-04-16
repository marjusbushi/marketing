<div class="flex items-center gap-1 justify-end">
    {{-- View --}}
    <a href="{{ route('marketing.influencers.show', $influencer) }}"
       class="dt-tippy-btn"
       data-tippy-content="@lang('influencer.actions.view')">
        <iconify-icon class="text-2xl inline-block text-slate-600" icon="heroicons:eye"></iconify-icon>
    </a>

    {{-- Edit --}}
    <button type="button"
            onclick="editInfluencer({{ $influencer->id }})"
            class="dt-tippy-btn"
            data-tippy-content="@lang('influencer.actions.edit')">
        <iconify-icon class="text-2xl inline-block text-slate-600" icon="heroicons:pencil-square"></iconify-icon>
    </button>
</div>
