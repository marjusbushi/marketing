<div class="flex items-center gap-2 justify-end">
    <a class="dt-tippy-btn"
       href="{{ route('marketing.influencer-products.show', $influencerProduct) }}"
       data-tippy-content="Shiko">
        <iconify-icon class="text-2xl inline-block text-slate-600" icon="heroicons:eye"></iconify-icon>
    </a>

    @if($influencerProduct->isDraft())
        <a href="javascript:void(0);"
           class="dt-tippy-btn action-button"
           data-tippy-content="Aktivizo"
           data-action="{{ route('marketing.influencer-products.activate', $influencerProduct) }}"
           data-method="POST"
           data-title="Aktivizo Dhënien"
           data-message="Jeni i sigurt që doni ta aktivizoni këtë dhënie? Stoku do të lëvizet."
           data-is-danger="true">
            <iconify-icon class="text-2xl inline-block text-green-600" icon="heroicons:check-circle"></iconify-icon>
        </a>
        <a href="javascript:void(0);"
           class="dt-tippy-btn action-button"
           data-tippy-content="Anulo"
           data-action="{{ route('marketing.influencer-products.cancel', $influencerProduct) }}"
           data-method="POST"
           data-title="Anulo Dhënien"
           data-message="Jeni i sigurt që doni ta anuloni këtë dhënie?"
           data-is-danger="true">
            <iconify-icon class="text-2xl inline-block text-red-600" icon="heroicons:x-circle"></iconify-icon>
        </a>
    @endif

    @if($influencerProduct->isActive())
        <a href="javascript:void(0);"
           class="dt-tippy-btn action-button"
           data-tippy-content="Anulo"
           data-action="{{ route('marketing.influencer-products.cancel', $influencerProduct) }}"
           data-method="POST"
           data-title="Anulo Dhënien"
           data-message="Jeni i sigurt që doni ta anuloni këtë dhënie? Stoku do të kthehet."
           data-is-danger="true">
            <iconify-icon class="text-2xl inline-block text-red-600" icon="heroicons:x-circle"></iconify-icon>
        </a>
    @endif
</div>
