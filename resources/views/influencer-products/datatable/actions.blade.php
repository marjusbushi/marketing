<div class="flex items-center gap-1 justify-end">
    <a href="{{ route('marketing.influencer-products.show', $influencerProduct) }}"
       class="ip-row-action" title="Shiko">
        <iconify-icon icon="heroicons-outline:eye" width="16"></iconify-icon>
    </a>

    @if($influencerProduct->isDraft())
        <form action="{{ route('marketing.influencer-products.activate', $influencerProduct) }}"
              method="POST" onsubmit="return confirm('Aktivizo këtë dhënie? Stoku do të lëvizet.')" class="inline-flex">
            @csrf
            <button type="submit" class="ip-row-action success" title="Aktivizo">
                <iconify-icon icon="heroicons-outline:check-circle" width="16"></iconify-icon>
            </button>
        </form>
        <form action="{{ route('marketing.influencer-products.cancel', $influencerProduct) }}"
              method="POST" onsubmit="return confirm('Anulo këtë dhënie?')" class="inline-flex">
            @csrf
            <button type="submit" class="ip-row-action danger" title="Anulo">
                <iconify-icon icon="heroicons-outline:x-circle" width="16"></iconify-icon>
            </button>
        </form>
    @elseif($influencerProduct->isActive())
        <form action="{{ route('marketing.influencer-products.cancel', $influencerProduct) }}"
              method="POST" onsubmit="return confirm('Anulo këtë dhënie? Stoku do të kthehet.')" class="inline-flex">
            @csrf
            <button type="submit" class="ip-row-action danger" title="Anulo">
                <iconify-icon icon="heroicons-outline:x-circle" width="16"></iconify-icon>
            </button>
        </form>
    @endif
</div>
