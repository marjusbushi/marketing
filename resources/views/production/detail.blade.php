@extends('production._layout', ['title' => $post->title ?: 'Set i prodhimit'])

@push('head')
<style>
    .pd-page { max-width: 640px; margin: 0 auto; padding-bottom: 80px; min-height: 100vh; }
    .pd-nav { display: flex; align-items: center; gap: 10px; padding: 12px; border-bottom: 1px solid #e5e7eb; background: #fff; position: sticky; top: 0; z-index: 5; }
    .pd-back { font-size: 18px; color: #64748b; }
    .pd-nav-title { font-weight: 600; font-size: 13px; flex: 1; }
    .pd-stage-pill { background: #fef3c7; color: #92400e; font-size: 9px; padding: 3px 7px; border-radius: 10px; font-weight: 600; }

    .pd-banner { padding: 10px 12px; font-size: 12px; display: flex; align-items: center; gap: 8px; }
    .pd-banner.free  { background: #fef3c7; color: #78350f; }
    .pd-banner.mine  { background: #dcfce7; color: #166534; }
    .pd-banner.taken { background: #fee2e2; color: #b91c1c; }
    .pd-banner button { margin-left: auto; padding: 6px 12px; border-radius: 6px; border: none; font-size: 11px; font-weight: 600; cursor: pointer; }
    .pd-banner.free button  { background: #1f2937; color: #fff; }
    .pd-banner.mine button  { background: #fff; color: #166534; border: 1px solid #bbf7d0; }

    .pd-body { padding: 12px; }
    .pd-lbl { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin: 14px 0 6px; }
    .pd-lbl:first-child { margin-top: 0; }

    .pd-type { display: inline-block; color: #fff; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
    .pd-type[data-type="reel"]     { background: #db2777; }
    .pd-type[data-type="photo"]    { background: #2563eb; }
    .pd-type[data-type="story"]    { background: #7c3aed; }
    .pd-type[data-type="carousel"] { background: #0891b2; }
    .pd-type[data-type="video"]    { background: #64748b; }

    .pd-ref { display: block; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #f8fafc; }
    .pd-ref-img { width: 100%; aspect-ratio: 4/5; background: linear-gradient(135deg,#fde68a,#f472b6); position: relative; }
    .pd-ref-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pd-ref-img .play { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.92); font-size: 56px; text-shadow: 0 2px 12px rgba(0,0,0,0.4); pointer-events: none; }
    .pd-ref-img .badge { position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: #fff; padding: 4px 8px; border-radius: 8px; font-size: 10px; }
    .pd-ref-meta { padding: 8px 10px; display: flex; gap: 8px; align-items: center; background: #fff; border-top: 1px solid #e5e7eb; }
    .pd-ref-host { font-size: 11px; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .pd-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px 4px 4px; border-radius: 14px; background: #f1f5f9; font-size: 11px; margin: 0 4px 4px 0; }
    .pd-chip-thumb { width: 18px; height: 18px; border-radius: 4px; background: #e2e8f0; flex-shrink: 0; object-fit: cover; }

    .pd-loc { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; }
    .pd-loc-icon { font-size: 20px; }
    .pd-loc-name { font-size: 13px; font-weight: 600; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pd-loc-arrow { color: #94a3b8; font-size: 14px; }

    .pd-model { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px 6px 6px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; font-size: 12px; color: #166534; margin: 0 6px 6px 0; }
    .pd-model-av { width: 22px; height: 22px; border-radius: 50%; background: linear-gradient(135deg,#34d399,#059669); color: #fff; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; }

    .pd-notes { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 8px 10px; border-radius: 0 6px 6px 0; font-size: 12px; color: #78350f; line-height: 1.4; white-space: pre-wrap; }

    .pd-upload { display: block; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 24px 12px; text-align: center; color: #94a3b8; font-size: 12px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
    .pd-upload:hover { border-color: #6366f1; background: #f8fafc; }
    .pd-upload-icon { font-size: 28px; }
    .pd-upload input[type=file] { display: none; }
    .pd-upload.disabled { opacity: 0.5; cursor: not-allowed; }
    .pd-upload.disabled:hover { border-color: #cbd5e1; background: transparent; }
    .pd-progress { width: 100%; height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden; margin-top: 8px; }
    .pd-progress-bar { height: 100%; background: #1f2937; transition: width 0.2s; width: 0; }

    .pd-uploaded { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 8px; }
    .pd-uploaded-tile { aspect-ratio: 1/1; border-radius: 8px; background: #f1f5f9; position: relative; overflow: hidden; }
    .pd-uploaded-tile img, .pd-uploaded-tile video { width: 100%; height: 100%; object-fit: cover; }
    .pd-uploaded-del { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(0,0,0,0.7); color: #fff; border: none; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; }

    .pd-foot { position: fixed; left: 0; right: 0; bottom: 0; display: flex; gap: 8px; padding: 10px 12px; background: #fff; border-top: 1px solid #e5e7eb; max-width: 640px; margin: 0 auto; }
    .pd-btn-primary { flex: 1; background: #1f2937; color: #fff; border: none; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .pd-btn-primary[disabled] { opacity: 0.5; cursor: not-allowed; }
    .pd-btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; }
</style>
@endpush

@section('content')
<div class="pd-page" id="pdPage" data-post-id="{{ $post->id }}">
    <div class="pd-nav">
        <a href="{{ route('marketing.production.queue') }}" class="pd-back">←</a>
        <span class="pd-nav-title">Set {{ $position }} / {{ $totalToday }} për sot</span>
        <span class="pd-stage-pill">{{ $post->stage->label() }}</span>
    </div>

    {{-- Claim banner --}}
    @if ($claimState === 'free')
        <div class="pd-banner free">
            <span>📷 Ky post është pa marrë.</span>
            <button id="prClaimBtn">Po e marr unë</button>
        </div>
    @elseif ($claimState === 'mine')
        <div class="pd-banner mine">
            <span>✓ Ti e ke marrë në {{ $post->claimed_at?->format('H:i') }}.</span>
            <button id="prReleaseBtn">✗ Largoja</button>
        </div>
    @else
        <div class="pd-banner taken">
            <span>🔒 {{ $post->claimer?->name ?? 'Tjetri' }} e mori {{ $post->claimed_at?->diffForHumans() }}.</span>
        </div>
    @endif

    <div class="pd-body">
        {{-- Type --}}
        <div class="pd-lbl">📌 Tipi i postit</div>
        <span class="pd-type" data-type="{{ $post->post_type?->value }}">{{ $post->post_type?->label() }}</span>

        {{-- Reference --}}
        @if ($post->reference_url)
            <div class="pd-lbl">🎯 Referenca — tap për ta hapur</div>
            <a href="{{ $post->reference_url }}" target="_blank" rel="noopener" class="pd-ref">
                <div class="pd-ref-img">
                    @if ($referencePreview['image'])
                        <img src="{{ $referencePreview['image'] }}" alt="" referrerpolicy="no-referrer">
                    @endif
                    @if ($referencePreview['is_video'])
                        <span class="play">▶</span>
                    @endif
                    <span class="badge">↗ {{ $post->reference_host ?? parse_url($post->reference_url, PHP_URL_HOST) }}</span>
                </div>
                <div class="pd-ref-meta">
                    <span class="pd-ref-host">{{ $post->reference_url }}</span>
                </div>
            </a>
        @endif

        {{-- Products --}}
        @if ($post->itemGroups->isNotEmpty())
            <div class="pd-lbl">🛍️ Produktet që duhen veshur</div>
            <div>
                @foreach ($post->itemGroups as $g)
                    <span class="pd-chip">
                        @if ($g->image_url)
                            <img class="pd-chip-thumb" src="{{ route('marketing.cdn-image') }}?url={{ urlencode($g->image_url) }}" alt="">
                        @else
                            <span class="pd-chip-thumb"></span>
                        @endif
                        {{ $g->name }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Location --}}
        @if ($post->lokacioni)
            <div class="pd-lbl">📍 Lokacioni — tap për Maps</div>
            <a class="pd-loc" target="_blank" rel="noopener"
               href="https://www.google.com/maps/search/?api=1&query={{ urlencode($post->lokacioni) }}">
                <span class="pd-loc-icon">📍</span>
                <span class="pd-loc-name">{{ $post->lokacioni }}</span>
                <span class="pd-loc-arrow">↗</span>
            </a>
        @endif

        {{-- Models --}}
        @if (! empty($post->modelet))
            <div class="pd-lbl">👥 Modelet</div>
            <div>
                @foreach (preg_split('/,\s*/', $post->modelet) as $m)
                    @if (! empty(trim($m)))
                        <span class="pd-model">
                            <span class="pd-model-av">{{ \Illuminate\Support\Str::substr(trim($m), 0, 1) }}</span>
                            {{ trim($m) }}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Notes --}}
        @if ($post->reference_notes)
            <div class="pd-lbl">💭 Mood / shënime</div>
            <div class="pd-notes">{{ $post->reference_notes }}</div>
        @endif

        {{-- Upload --}}
        <div class="pd-lbl">📸 Materiali yt</div>
        <label class="pd-upload {{ $claimState !== 'mine' ? 'disabled' : '' }}" id="prUploadZone">
            <div class="pd-upload-icon">📷</div>
            <div><strong>Hap kamerën</strong> ose tërhiq foto/video këtu</div>
            <input type="file" accept="image/*,video/*" capture="environment" multiple
                   {{ $claimState !== 'mine' ? 'disabled' : '' }} id="prFileInput">
            <div class="pd-progress" id="prProgress" style="display:none">
                <div class="pd-progress-bar" id="prProgressBar"></div>
            </div>
        </label>

        <div class="pd-uploaded" id="prUploaded">
            @foreach ($post->media as $m)
                <div class="pd-uploaded-tile" data-media-id="{{ $m->id }}">
                    @if ($m->is_video)
                        <video src="{{ $m->url }}" muted></video>
                    @else
                        <img src="{{ $m->thumbnail_url ?? $m->url }}" alt="">
                    @endif
                    @if ($claimState === 'mine')
                        <button class="pd-uploaded-del" data-media-id="{{ $m->id }}">×</button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="pd-foot">
        <button class="pd-btn-secondary" onclick="window.location='{{ route('marketing.production.queue') }}'">← Lista</button>
        <button class="pd-btn-primary" id="prAdvanceBtn" {{ $claimState !== 'mine' ? 'disabled' : '' }}>✓ Dërgo në Editim →</button>
    </div>
</div>

<script>
(function () {
    'use strict';
    const POST_ID   = {{ $post->id }};
    const CSRF      = document.querySelector('meta[name="csrf-token"]').content;
    const QUEUE_URL = @json(route('marketing.production.queue'));

    const claimBtn    = document.getElementById('prClaimBtn');
    const releaseBtn  = document.getElementById('prReleaseBtn');
    const advanceBtn  = document.getElementById('prAdvanceBtn');
    const fileInput   = document.getElementById('prFileInput');
    const progressEl  = document.getElementById('prProgress');
    const progressBar = document.getElementById('prProgressBar');
    const uploadedEl  = document.getElementById('prUploaded');

    const post_url = (suffix) => `/marketing/production/${POST_ID}${suffix}`;
    const media_url = (suffix) => `/marketing/daily-basket/api/posts/${POST_ID}/media${suffix || ''}`;

    async function postJson(url, body = null) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : null,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw Object.assign(new Error(data.message || 'HTTP ' + res.status), { status: res.status, data });
        return data;
    }

    if (claimBtn) {
        claimBtn.addEventListener('click', async () => {
            try {
                await postJson(post_url('/claim'));
                window.location.reload();
            } catch (e) {
                if (e.status === 409 && e.data?.claimed_by) {
                    alert(`${e.data.claimed_by} e mori ${e.data.claimed_at_human ?? 'sapo'}.`);
                } else {
                    alert('Nuk u mor: ' + e.message);
                }
                window.location.reload();
            }
        });
    }

    if (releaseBtn) {
        releaseBtn.addEventListener('click', async () => {
            if (!confirm('Të heqësh marrjen e këtij posti? Tjetri mund ta marrë.')) return;
            try { await postJson(post_url('/release')); window.location.reload(); }
            catch (e) { alert('Heqja dështoi: ' + e.message); }
        });
    }

    if (advanceBtn) {
        advanceBtn.addEventListener('click', async () => {
            try {
                await postJson(post_url('/advance'));
                window.location = QUEUE_URL;
            } catch (e) {
                if (e.status === 422 && e.data?.code === 'no_media') {
                    if (confirm((e.data.warning || 'Pa media.') + '\nTë vazhdosh prapë?')) {
                        try {
                            await postJson(post_url('/advance'), { force: true });
                            window.location = QUEUE_URL;
                        } catch (ee) { alert('Dështoi: ' + ee.message); }
                    }
                } else {
                    alert('Dështoi: ' + e.message);
                }
            }
        });
    }

    // Client-side image compression: resize to max 2400px wide, JPEG 0.9.
    async function compressImage(file) {
        if (!file.type.startsWith('image/')) return file;
        try {
            const bitmap = await createImageBitmap(file);
            const maxW   = 2400;
            const ratio  = bitmap.width > maxW ? maxW / bitmap.width : 1;
            const w = Math.round(bitmap.width * ratio);
            const h = Math.round(bitmap.height * ratio);
            const canvas = document.createElement('canvas');
            canvas.width  = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(bitmap, 0, 0, w, h);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (!blob) return file;
            return new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' });
        } catch (_) {
            return file;
        }
    }

    function uploadOne(file) {
        return new Promise(async (resolve, reject) => {
            const compressed = await compressImage(file);
            const fd = new FormData();
            fd.append('file', compressed);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', media_url());
            xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) progressBar.style.width = (e.loaded / e.total * 100) + '%';
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText)); }
                    catch (_) { resolve({}); }
                } else { reject(new Error('HTTP ' + xhr.status)); }
            };
            xhr.onerror = () => reject(new Error('Rrjeti ra'));
            xhr.send(fd);
        });
    }

    function appendUploadedTile(media) {
        const tile = document.createElement('div');
        tile.className = 'pd-uploaded-tile';
        tile.dataset.mediaId = media.id;

        const node = document.createElement(media.is_video ? 'video' : 'img');
        if (media.is_video) {
            node.src = media.url;
            node.muted = true;
        } else {
            node.src = media.thumbnail_url || media.url;
            node.alt = '';
        }
        tile.appendChild(node);

        const del = document.createElement('button');
        del.className = 'pd-uploaded-del';
        del.dataset.mediaId = media.id;
        del.textContent = '×';
        tile.appendChild(del);

        uploadedEl.appendChild(tile);
    }

    if (fileInput) {
        fileInput.addEventListener('change', async (e) => {
            const files = Array.from(e.target.files || []);
            if (files.length === 0) return;
            progressEl.style.display = 'block';
            for (const f of files) {
                progressBar.style.width = '0%';
                try {
                    const m = await uploadOne(f);
                    if (m && m.id) appendUploadedTile(m);
                } catch (err) {
                    alert(`Upload "${f.name}" dështoi: ${err.message}. Provo përsëri.`);
                }
            }
            progressEl.style.display = 'none';
            fileInput.value = '';
        });
    }

    // Delegate delete button clicks (also works for tiles added at runtime).
    uploadedEl.addEventListener('click', async (e) => {
        const btn = e.target.closest('.pd-uploaded-del');
        if (!btn) return;
        const mediaId = parseInt(btn.dataset.mediaId, 10);
        if (!mediaId) return;
        if (!confirm('Të hiqet kjo media?')) return;
        try {
            const res = await fetch(media_url('/' + mediaId), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const tile = uploadedEl.querySelector(`[data-media-id="${mediaId}"]`);
            if (tile) tile.remove();
        } catch (err) {
            alert('Heqja dështoi: ' + err.message);
        }
    });
})();
</script>
@endsection
