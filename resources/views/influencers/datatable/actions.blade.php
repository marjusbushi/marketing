<div class="flex items-center gap-1 justify-end">
    <a href="{{ route('marketing.influencers.show', $influencer) }}"
       class="inf-row-action"
       title="@lang('influencer.actions.view')">
        <iconify-icon icon="heroicons-outline:eye" width="16"></iconify-icon>
    </a>
    <button type="button"
            onclick="editInfluencer({{ $influencer->id }})"
            class="inf-row-action"
            title="@lang('influencer.actions.edit')">
        <iconify-icon icon="heroicons-outline:pencil-square" width="16"></iconify-icon>
    </button>
</div>
