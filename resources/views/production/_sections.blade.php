@php
    $kinds = ['mine' => 'Të miat', 'free' => 'Të lira', 'taken' => 'Të zëna nga të tjerët'];
    $bag = compact('mine', 'free', 'taken');
@endphp

@foreach ($kinds as $kind => $label)
    @php $posts = $bag[$kind]; @endphp

    @if (($kind === 'mine' || $kind === 'taken') && $posts->isEmpty())
        @continue
    @endif

    <div class="pr-section-hdr">
        <span class="pr-section-lbl">{{ $label }}</span>
        <span class="pr-section-count">({{ $posts->count() }})</span>
    </div>

    @if ($posts->isEmpty())
        <div class="pr-empty">🎉 Asnjë shoot për sot. Pushim i merituar.</div>
    @else
        @foreach ($posts as $p)
            @php
                $first = $p->media->first();
                $thumb = $first?->thumbnail_url ?? $first?->url;
                $tagClass = strtolower($p->priority ?? 'normal');
                $clickable = $kind !== 'taken';
                $initial = \Illuminate\Support\Str::substr($p->title ?? '?', 0, 1);
                $hue = ($p->id * 37) % 360;
            @endphp
            <div class="pr-card {{ $kind === 'taken' ? 'taken' : '' }}"
                 @if ($clickable) onclick="window.location='{{ route('marketing.production.show', $p) }}'" @endif>
                @if ($thumb)
                    <div class="pr-thumb" style="background-image:url('{{ e($thumb) }}')"></div>
                @else
                    <div class="pr-thumb" style="background:hsl({{ $hue }},55%,55%)">{{ strtoupper($initial) }}</div>
                @endif
                <div class="pr-info">
                    <div class="pr-title">{{ $p->title ?: 'Pa titull' }}</div>
                    <div class="pr-meta">{{ $p->post_type?->label() }}{{ $p->lokacioni ? ' · '.$p->lokacioni : '' }}</div>
                </div>
                @if ($kind === 'taken' && $p->claimer)
                    <span class="pr-tag taken">{{ $p->claimer->name }} e mori</span>
                @else
                    <span class="pr-tag {{ $tagClass }}">{{ strtoupper($p->priority ?? 'normal') }}</span>
                @endif
            </div>
        @endforeach
    @endif
@endforeach
