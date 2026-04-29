@extends('production._layout', ['title' => 'Prodhimi'])

@push('head')
<style>
    .pr-page { max-width: 640px; margin: 0 auto; padding: 12px; min-height: 100vh; }
    .pr-hdr { display: flex; align-items: center; gap: 10px; padding: 8px 4px 16px; }
    .pr-hdr-icon { font-size: 22px; }
    .pr-hdr-title { font-size: 18px; font-weight: 700; line-height: 1.1; }
    .pr-hdr-meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .pr-section-hdr { display: flex; align-items: center; gap: 6px; margin: 18px 4px 8px; }
    .pr-section-lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
    .pr-section-count { font-size: 10px; color: #94a3b8; }
    .pr-card { display: flex; align-items: center; gap: 10px; padding: 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 8px; cursor: pointer; transition: box-shadow 0.12s; min-height: 56px; }
    .pr-card:active { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .pr-card.taken { opacity: 0.55; cursor: default; }
    .pr-thumb { width: 44px; height: 44px; border-radius: 6px; flex-shrink: 0; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 16px; }
    .pr-info { flex: 1; min-width: 0; }
    .pr-title { font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pr-meta { font-size: 10px; color: #94a3b8; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pr-tag { font-size: 9px; padding: 2px 6px; border-radius: 8px; font-weight: 600; flex-shrink: 0; text-transform: uppercase; letter-spacing: 0.04em; }
    .pr-tag.urgent { background: #fee2e2; color: #b91c1c; }
    .pr-tag.high   { background: #fef3c7; color: #92400e; }
    .pr-tag.normal { background: #f1f5f9; color: #475569; }
    .pr-tag.low    { background: #f8fafc; color: #94a3b8; }
    .pr-tag.taken  { background: #f1f5f9; color: #6b7280; text-transform: none; letter-spacing: 0; }
    .pr-empty { padding: 32px 12px; text-align: center; color: #94a3b8; font-size: 12px; }
    .pr-tt { font-size: 10px; color: #94a3b8; font-style: italic; padding: 0 4px 8px; margin-top: 12px; }
</style>
@endpush

@section('content')
<div class="pr-page">
    <div class="pr-hdr">
        <span class="pr-hdr-icon">📷</span>
        <div>
            <div class="pr-hdr-title">Prodhimi</div>
            <div class="pr-hdr-meta">{{ auth()->user()->name }} · <span id="prClock"></span></div>
        </div>
    </div>

    <div id="prSections">
        @include('production._sections', compact('mine', 'free', 'taken'))
    </div>

    <div class="pr-tt">Auto-refresh çdo 30 sek.</div>
</div>

<script>
(function () {
    'use strict';
    const sectionsEl = document.getElementById('prSections');
    const clockEl    = document.getElementById('prClock');
    const SHOW_BASE  = '/marketing/production/';

    function tickClock() {
        const d = new Date();
        clockEl.textContent = d.toLocaleTimeString('sq-AL', { hour: '2-digit', minute: '2-digit' });
    }
    tickClock();
    setInterval(tickClock, 30_000);

    async function refresh() {
        if (document.visibilityState !== 'visible') return;
        try {
            const res = await fetch(window.location.pathname + '?json=1', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderSections(data);
        } catch (_) { /* network blip */ }
    }
    setInterval(refresh, 30_000);

    function clearChildren(el) {
        while (el.firstChild) el.removeChild(el.firstChild);
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = text;
        return node;
    }

    const SECTION_LABELS = { mine: 'Të miat', free: 'Të lira', taken: 'Të zëna nga të tjerët' };

    function renderSections(data) {
        clearChildren(sectionsEl);
        ['mine', 'free', 'taken'].forEach(kind => {
            const posts = data[kind] || [];
            if ((kind === 'mine' || kind === 'taken') && posts.length === 0) return;

            const hdr = el('div', 'pr-section-hdr');
            hdr.appendChild(el('span', 'pr-section-lbl', SECTION_LABELS[kind]));
            hdr.appendChild(el('span', 'pr-section-count', `(${posts.length})`));
            sectionsEl.appendChild(hdr);

            if (posts.length === 0) {
                sectionsEl.appendChild(el('div', 'pr-empty', '🎉 Asnjë shoot për sot. Pushim i merituar.'));
            } else {
                posts.forEach(p => sectionsEl.appendChild(buildCard(p, kind === 'taken')));
            }
        });
    }

    function buildCard(p, taken) {
        const card = el('div', 'pr-card' + (taken ? ' taken' : ''));
        if (!taken) {
            card.addEventListener('click', () => { window.location = SHOW_BASE + p.id; });
        }

        const thumb = el('div', 'pr-thumb');
        if (p.thumbnail_url) {
            thumb.style.backgroundImage = `url('${p.thumbnail_url.replace(/'/g, "\\'")}')`;
        } else {
            thumb.style.background = `hsl(${(p.id * 37) % 360},55%,55%)`;
            thumb.textContent = (p.title || '?').charAt(0).toUpperCase();
        }
        card.appendChild(thumb);

        const info = el('div', 'pr-info');
        info.appendChild(el('div', 'pr-title', p.title || 'Pa titull'));
        const metaParts = [p.post_type_label, p.lokacioni].filter(Boolean);
        info.appendChild(el('div', 'pr-meta', metaParts.join(' · ')));
        card.appendChild(info);

        if (taken && p.claimed_by) {
            card.appendChild(el('span', 'pr-tag taken', `${p.claimed_by} e mori`));
        } else {
            const pri = (p.priority || 'normal').toLowerCase();
            card.appendChild(el('span', 'pr-tag ' + pri, (p.priority || 'normal').toUpperCase()));
        }

        return card;
    }
})();
</script>
@endsection
