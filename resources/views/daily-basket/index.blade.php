@extends('_layouts.app', [
    'title'     => 'Shporta Ditore',
    'pageTitle' => 'Shporta Ditore',
])

@section('styles')
<style>
    :root {
        --db-bg: #fafaf9;
        --db-surface: #ffffff;
        --db-border: #eeeeec;
        --db-border-strong: #e4e4e2;
        --db-text: #18181b;
        --db-text-2: #71717a;
        --db-text-3: #a1a1aa;
        --db-accent-soft: #f4f4f5;
    }

    .db-wrap { font-size: 13px; line-height: 1.5; color: var(--db-text); }

    .db-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 24px; }
    .db-title { font-size: 22px; font-weight: 600; letter-spacing: -0.01em; }
    .db-meta { font-size: 12px; color: var(--db-text-3); }

    .db-coll { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--db-border); margin-bottom: 24px; }
    .db-coll-left { display: flex; align-items: center; gap: 10px; }
    .db-coll-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; }
    .db-coll-name { font-weight: 500; }
    .db-coll-range { color: var(--db-text-3); }
    .db-coll-sep { color: var(--db-text-3); margin: 0 6px; }
    .db-coll-prog { font-size: 12px; color: var(--db-text-2); }
    .db-coll-prog strong { color: var(--db-text); font-weight: 500; }

    .db-days { display: flex; gap: 2px; margin-bottom: 32px; }
    .db-day { flex: 1; padding: 10px 8px; cursor: pointer; border-bottom: 2px solid transparent; text-align: left; transition: background 0.1s; }
    .db-day:hover { background: var(--db-accent-soft); }
    .db-day.active { border-bottom-color: var(--db-text); }
    .db-day-lbl { font-size: 10px; color: var(--db-text-3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; }
    .db-day.active .db-day-lbl { color: var(--db-text); }
    .db-day-date { font-size: 15px; font-weight: 500; margin-top: 2px; }
    .db-day-count { font-size: 11px; color: var(--db-text-3); margin-top: 3px; }
    .db-day-count.complete { color: #22c55e; }

    .db-board { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
    .db-col { min-width: 0; }
    .db-col-head { display: flex; align-items: center; justify-content: space-between; padding: 0 4px 12px; margin-bottom: 8px; border-bottom: 1px solid var(--db-border); }
    .db-col-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--db-text-2); display: flex; align-items: center; gap: 6px; }
    .db-col-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--db-text-3); }
    .db-col[data-stage="production"] .db-col-dot { background: #f59e0b; }
    .db-col[data-stage="editing"] .db-col-dot { background: #8b5cf6; }
    .db-col[data-stage="scheduling"] .db-col-dot { background: #3b82f6; }
    .db-col[data-stage="published"] .db-col-dot { background: #22c55e; }
    .db-col-count { font-size: 11px; color: var(--db-text-3); }
    .db-col-body { display: flex; flex-direction: column; gap: 8px; min-height: 60px; }

    .db-card { background: var(--db-surface); border: 1px solid var(--db-border); border-radius: 8px; padding: 12px; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
    .db-card:hover { border-color: var(--db-border-strong); box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .db-card.selected { border-color: var(--db-text); box-shadow: 0 0 0 3px rgba(24,24,27,0.06); }
    .db-card-type { font-size: 10px; color: var(--db-text-3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; margin-bottom: 6px; }
    .db-card-title { font-size: 13px; font-weight: 500; line-height: 1.35; margin-bottom: 10px; }
    .db-card-products { display: flex; gap: 3px; margin-bottom: 10px; }
    .db-thumb { width: 28px; height: 28px; border-radius: 4px; background: #f4f4f5; flex-shrink: 0; object-fit: cover; }

    .db-card-foot { display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: var(--db-text-3); }
    .db-avatar { width: 18px; height: 18px; border-radius: 50%; background: var(--db-accent-soft); color: var(--db-text-2); display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 500; border: 1px solid var(--db-border); }
    .db-plat { display: flex; gap: 3px; }
    .db-plat-tag { width: 14px; height: 14px; border-radius: 3px; background: #f4f4f5; color: var(--db-text-2); font-size: 7px; font-weight: 600; display: flex; align-items: center; justify-content: center; }

    .db-empty { padding: 20px 12px; text-align: center; color: var(--db-text-3); font-size: 11px; border: 1px dashed var(--db-border); border-radius: 8px; }

    .db-skel { background: var(--db-accent-soft); border-radius: 4px; position: relative; overflow: hidden; }
    .db-skel::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent); animation: db-shimmer 1.2s infinite; }
    @keyframes db-shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

    .db-sheet-label { font-size: 11px; color: var(--db-text-3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; margin: 40px 0 10px; }
    .db-sheet { background: var(--db-surface); border: 1px solid var(--db-border); border-radius: 12px; overflow: hidden; }
    .db-sheet-placeholder { padding: 40px; text-align: center; color: var(--db-text-3); font-size: 12px; }
    .db-sheet-head { padding: 20px 24px; border-bottom: 1px solid var(--db-border); }
    .db-sheet-crumb { font-size: 11px; color: var(--db-text-3); margin-bottom: 4px; }
    .db-sheet-title { font-size: 18px; font-weight: 600; letter-spacing: -0.01em; }

    .db-track { display: flex; padding: 16px 24px; background: #fafafa; border-bottom: 1px solid var(--db-border); gap: 4px; }
    .db-track-step { flex: 1; padding: 4px 0; }
    .db-track-line { height: 2px; background: var(--db-border); border-radius: 1px; margin-bottom: 6px; }
    .db-track-step.done .db-track-line { background: #22c55e; }
    .db-track-step.current .db-track-line { background: var(--db-text); }
    .db-track-lbl { font-size: 10px; color: var(--db-text-3); font-weight: 500; }
    .db-track-step.done .db-track-lbl { color: #22c55e; }
    .db-track-step.current .db-track-lbl { color: var(--db-text); font-weight: 600; }

    .db-sheet-body { display: grid; grid-template-columns: 1fr 1fr; }
    .db-sec { padding: 20px 24px; border-bottom: 1px solid var(--db-border); }
    .db-sec:nth-child(odd) { border-right: 1px solid var(--db-border); }
    .db-sec:last-child, .db-sec:nth-last-child(2) { border-bottom: 0; }
    .db-sec-lbl { font-size: 10px; color: var(--db-text-3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 8px; }
    .db-sec-val { font-size: 13px; color: var(--db-text); line-height: 1.55; }
    .db-sec-val.muted { color: var(--db-text-3); }

    .db-prod-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--db-border); }
    .db-prod-row:last-child { border-bottom: 0; }
    .db-prod-row-name { font-size: 12px; font-weight: 500; flex: 1; }
    .db-prod-row-role { font-size: 10px; color: var(--db-text-3); }

    .db-sheet-foot { display: flex; justify-content: space-between; padding: 14px 20px; border-top: 1px solid var(--db-border); background: #fafafa; }
    .db-btn { padding: 7px 14px; font-size: 12px; border-radius: 6px; border: 1px solid transparent; cursor: pointer; font-weight: 500; background: transparent; color: var(--db-text-2); }
    .db-btn:hover { background: var(--db-accent-soft); color: var(--db-text); }
    .db-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .db-btn-primary { background: var(--db-text); color: #fff; }
    .db-btn-primary:hover:not(:disabled) { background: #27272a; color: #fff; }
    .db-btn-group { display: flex; gap: 4px; }

    .db-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 7px; font-size: 12px; margin: 12px 0; }

    /* Modal */
    .db-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9990; display: none; align-items: center; justify-content: center; }
    .db-modal-backdrop.open { display: flex; }
    .db-modal { background: var(--db-surface); border-radius: 12px; width: 680px; max-width: 95vw; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
    .db-modal-head { padding: 16px 20px; border-bottom: 1px solid var(--db-border); display: flex; justify-content: space-between; align-items: center; }
    .db-modal-title { font-size: 15px; font-weight: 600; }
    .db-modal-close { background: none; border: none; cursor: pointer; color: var(--db-text-3); font-size: 20px; padding: 4px 8px; }
    .db-modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
    .db-modal-foot { padding: 12px 20px; border-top: 1px solid var(--db-border); display: flex; justify-content: space-between; background: #fafafa; }

    .db-field { margin-bottom: 14px; }
    .db-field-lbl { font-size: 11px; font-weight: 600; color: var(--db-text-2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; display: block; }
    .db-input { width: 100%; padding: 8px 10px; border: 1px solid var(--db-border-strong); border-radius: 6px; font-size: 13px; font-family: inherit; }
    .db-input:focus { outline: none; border-color: var(--db-text); }

    .db-seg { display: flex; gap: 4px; flex-wrap: wrap; }
    .db-seg-opt { padding: 6px 12px; border: 1px solid var(--db-border-strong); border-radius: 6px; cursor: pointer; font-size: 12px; background: #fff; color: var(--db-text-2); }
    .db-seg-opt:hover { background: var(--db-accent-soft); }
    .db-seg-opt.active { background: var(--db-text); color: #fff; border-color: var(--db-text); }

    .db-picker-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; max-height: 300px; overflow-y: auto; border: 1px solid var(--db-border); border-radius: 6px; padding: 6px; }
    .db-picker-item { display: flex; gap: 8px; padding: 6px 8px; border-radius: 5px; cursor: pointer; align-items: center; }
    .db-picker-item:hover { background: var(--db-accent-soft); }
    .db-picker-item.selected { background: #eef2ff; }
    .db-picker-thumb { width: 36px; height: 36px; border-radius: 5px; background: #f4f4f5; object-fit: cover; flex-shrink: 0; }
    .db-picker-name { font-size: 12px; font-weight: 500; line-height: 1.2; }
    .db-picker-sub { font-size: 10px; color: var(--db-text-3); margin-top: 2px; }
    .db-picker-check { width: 16px; height: 16px; border: 1.5px solid var(--db-border-strong); border-radius: 4px; display: flex; align-items: center; justify-content: center; margin-left: auto; font-size: 11px; color: var(--db-text); flex-shrink: 0; }
    .db-picker-item.selected .db-picker-check { background: var(--db-text); border-color: var(--db-text); color: #fff; }

    .db-picker-empty { grid-column: 1/-1; padding: 24px; text-align: center; color: var(--db-text-3); font-size: 12px; }
</style>
@endsection

@section('content')
<div class="db-wrap">

    <div class="db-head">
        <div>
            <div class="db-title">Shporta Ditore</div>
            <div class="db-meta" id="dbCurrentDate">—</div>
        </div>
        <button class="db-btn db-btn-primary" id="dbBtnNewPost" disabled>+ Post i ri</button>
    </div>

    <!-- New Post modal -->
    <div class="db-modal-backdrop" id="dbModal">
        <div class="db-modal">
            <div class="db-modal-head">
                <div class="db-modal-title">Post i ri</div>
                <button class="db-modal-close" id="dbModalClose" aria-label="Mbyll">×</button>
            </div>
            <div class="db-modal-body">
                <div class="db-field">
                    <label class="db-field-lbl">Titull</label>
                    <input type="text" class="db-input" id="dbFieldTitle" placeholder="P.sh. Spring Weekend Outfit" maxlength="255">
                </div>

                <div class="db-field">
                    <label class="db-field-lbl">Tipi i postit</label>
                    <div class="db-seg" id="dbFieldType">
                        <div class="db-seg-opt" data-value="photo">Photo</div>
                        <div class="db-seg-opt" data-value="video">Video</div>
                        <div class="db-seg-opt" data-value="reel">Reel</div>
                        <div class="db-seg-opt" data-value="carousel">Carousel</div>
                        <div class="db-seg-opt" data-value="story">Story</div>
                    </div>
                </div>

                <div class="db-field">
                    <label class="db-field-lbl">Prioriteti</label>
                    <div class="db-seg" id="dbFieldPriority">
                        <div class="db-seg-opt" data-value="low">Low</div>
                        <div class="db-seg-opt active" data-value="normal">Normal</div>
                        <div class="db-seg-opt" data-value="high">High</div>
                        <div class="db-seg-opt" data-value="urgent">Urgent</div>
                    </div>
                </div>

                <div class="db-field">
                    <label class="db-field-lbl">Produktet nga kolekcioni (klik për të zgjedhur)</label>
                    <div class="db-picker-list" id="dbFieldProducts">
                        <div class="db-picker-empty">Pa produkte nga kolekcioni</div>
                    </div>
                </div>
            </div>
            <div class="db-modal-foot">
                <button class="db-btn" id="dbModalCancel">Anulo</button>
                <button class="db-btn db-btn-primary" id="dbModalSubmit">Krijo post</button>
            </div>
        </div>
    </div>

    <div class="db-coll" id="dbColl">
        <div class="db-coll-left">
            <div class="db-coll-dot"></div>
            <span class="db-coll-name" id="dbCollName">Duke ngarkuar kolekcionin aktiv…</span>
        </div>
        <div class="db-coll-prog" id="dbCollProg"></div>
    </div>

    <div class="db-days" id="dbDays">
        @for ($i = 0; $i < 8; $i++)
            <div class="db-day">
                <div class="db-skel" style="height: 8px; width: 30px;"></div>
                <div class="db-skel" style="height: 16px; width: 24px; margin-top: 6px;"></div>
                <div class="db-skel" style="height: 10px; width: 30px; margin-top: 6px;"></div>
            </div>
        @endfor
    </div>

    <div id="dbErrors"></div>

    <div class="db-board" id="dbBoard">
        @foreach (['planning' => 'Planifikim', 'production' => 'Prodhim', 'editing' => 'Editim', 'scheduling' => 'Skedulim', 'published' => 'Publikuar'] as $key => $label)
            <div class="db-col" data-stage="{{ $key }}">
                <div class="db-col-head">
                    <div class="db-col-title"><span class="db-col-dot"></span>{{ $label }}</div>
                    <div class="db-col-count" data-count="{{ $key }}">0</div>
                </div>
                <div class="db-col-body" data-column="{{ $key }}">
                    <div class="db-empty">—</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="db-sheet-label">Posti i zgjedhur</div>
    <div class="db-sheet" id="dbSheet">
        <div class="db-sheet-placeholder">Kliko një kartë më lart për të parë detajet.</div>
    </div>

</div>

<script>
// All DB-sourced strings pass through the `esc()` helper before being
// concatenated into template literals. Numeric ids are coerced with
// Number() / parseInt before interpolation. innerHTML is therefore safe.
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const STAGE_LABELS = {
        planning: 'Planifikim',
        production: 'Prodhim',
        editing: 'Editim',
        scheduling: 'Skedulim',
        published: 'Publikuar',
    };
    const STAGE_ORDER = ['planning', 'production', 'editing', 'scheduling', 'published'];

    const DAY_NAMES = ['Die', 'Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht'];
    const MONTH_NAMES = ['Jan', 'Shk', 'Mar', 'Pri', 'Maj', 'Qer', 'Kor', 'Gsh', 'Sht', 'Tet', 'Nën', 'Dhj'];

    // HTML-escape every untrusted value before interpolating it into
    // a template string. Using a detached div guarantees browser-native
    // escape rules so we never emit raw `<`, `>`, `&`, quotes, etc.
    const _esc = document.createElement('div');
    function esc(s) {
        if (s == null) return '';
        _esc.textContent = String(s);
        return _esc.innerHTML;
    }

    function num(n) {
        // Defensive: only numeric ids allowed into template strings.
        return Number.isFinite(+n) ? +n : 0;
    }

    const state = {
        week: null,
        days: [],
        selectedDate: null,
        selectedPostId: null,
        kanban: null,
        availableProducts: [],
        // Modal (new-post) state
        modal: {
            title: '',
            post_type: null,
            priority: 'normal',
            selectedProductIds: new Set(),
            heroProductId: null,
        },
    };

    async function apiGet(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    async function apiPost(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: body ? JSON.stringify(body) : null,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
        return data;
    }

    function showError(msg) {
        const host = document.getElementById('dbErrors');
        const div = document.createElement('div');
        div.className = 'db-error';
        div.textContent = msg; // textContent — no HTML injection
        host.innerHTML = '';
        host.appendChild(div);
        setTimeout(() => { if (div.parentNode) div.remove(); }, 6000);
    }

    function getWeekIdFromUrl() {
        const params = new URLSearchParams(location.search);
        return parseInt(params.get('week'), 10) || null;
    }

    async function loadCollection() {
        const weekId = getWeekIdFromUrl();
        if (!weekId) {
            document.getElementById('dbCollName').textContent = 'Zgjidh kolekcion nga Merch Calendar (shto ?week=ID në URL)';
            return;
        }

        try {
            const data = await apiGet('/marketing/daily-basket/api/collections/' + encodeURIComponent(weekId));
            state.week = data.collection;
            state.days = data.days;
            renderCollection();
            renderDays();

            const today = new Date().toISOString().slice(0, 10);
            const todayInRange = state.days.find(d => d.date === today);
            const target = todayInRange ? today : state.days[0]?.date;
            if (target) selectDay(target);
        } catch (e) {
            showError('Ngarkimi dështoi: ' + e.message);
        }
    }

    function renderCollection() {
        const c = state.week;
        document.getElementById('dbCollName').textContent = c.name;

        const prog = document.getElementById('dbCollProg');
        const total = state.days.reduce((s, d) => s + (d.posts_total || 0), 0);
        const done = state.days.reduce((s, d) => s + (d.posts_published || 0), 0);
        prog.textContent = '';
        const strong = document.createElement('strong');
        strong.textContent = done;
        prog.appendChild(strong);
        prog.append(' / ' + total + ' posts publikuar');

        const left = document.querySelector('.db-coll-left');
        const sep = document.createElement('span');
        sep.className = 'db-coll-sep';
        sep.textContent = '·';
        const range = document.createElement('span');
        range.className = 'db-coll-range';
        range.textContent = c.week_start + ' → ' + c.week_end;
        left.append(sep, range);
    }

    function renderDays() {
        const host = document.getElementById('dbDays');
        host.textContent = '';
        state.days.forEach(d => {
            const dt = new Date(d.date);
            const el = document.createElement('div');
            el.className = 'db-day';
            el.dataset.date = d.date;

            const isFull = d.posts_total > 0 && d.posts_published === d.posts_total;
            const countTxt = d.posts_total === 0 ? '— / —' : (d.posts_published + ' / ' + d.posts_total);

            const lbl = document.createElement('div');
            lbl.className = 'db-day-lbl';
            lbl.textContent = DAY_NAMES[dt.getDay()];

            const date = document.createElement('div');
            date.className = 'db-day-date';
            date.textContent = String(dt.getDate()).padStart(2, '0');

            const count = document.createElement('div');
            count.className = 'db-day-count' + (isFull ? ' complete' : '');
            count.textContent = countTxt;

            el.append(lbl, date, count);
            el.addEventListener('click', () => selectDay(d.date));
            host.appendChild(el);
        });
    }

    async function selectDay(date) {
        state.selectedDate = date;
        state.selectedPostId = null;

        document.querySelectorAll('.db-day').forEach(el => {
            el.classList.toggle('active', el.dataset.date === date);
        });

        const dt = new Date(date);
        document.getElementById('dbCurrentDate').textContent =
            DAY_NAMES[dt.getDay()] + ', ' + String(dt.getDate()).padStart(2, '0') + ' ' +
            MONTH_NAMES[dt.getMonth()] + ' ' + dt.getFullYear();

        try {
            const data = await apiGet(
                '/marketing/daily-basket/api/collections/' + encodeURIComponent(state.week.id) + '/' + encodeURIComponent(date)
            );
            state.kanban = data;
            state.availableProducts = data.available_products || [];
            renderBoard(data);
            renderSheet(null);

            // Enable "+ Post i ri" now that we have a basket
            document.getElementById('dbBtnNewPost').disabled = false;
        } catch (e) {
            showError('Ngarkimi i ditës dështoi: ' + e.message);
        }
    }

    function renderBoard(data) {
        data.columns.forEach(col => {
            const body = document.querySelector('.db-col-body[data-column="' + col.key + '"]');
            const count = document.querySelector('.db-col-count[data-count="' + col.key + '"]');
            count.textContent = col.count;

            body.textContent = '';

            if (col.posts.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'db-empty';
                empty.textContent = '—';
                body.appendChild(empty);
                return;
            }

            col.posts.forEach(post => body.appendChild(buildPostCard(post)));
        });
    }

    function buildPostCard(post) {
        const card = document.createElement('div');
        card.className = 'db-card' + (post.id === state.selectedPostId ? ' selected' : '');
        card.dataset.postId = post.id;
        card.addEventListener('click', () => selectPost(num(post.id)));

        const type = document.createElement('div');
        type.className = 'db-card-type';
        type.textContent = post.post_type_label;
        card.appendChild(type);

        const title = document.createElement('div');
        title.className = 'db-card-title';
        title.textContent = post.title;
        card.appendChild(title);

        if (post.products && post.products.length) {
            const products = document.createElement('div');
            products.className = 'db-card-products';
            post.products.slice(0, 5).forEach(p => {
                if (p.image_url) {
                    const img = document.createElement('img');
                    img.className = 'db-thumb';
                    img.src = p.image_url;
                    img.alt = p.name || '';
                    img.title = p.name || '';
                    img.onerror = () => {
                        const fallback = document.createElement('div');
                        fallback.className = 'db-thumb';
                        fallback.title = p.name || '';
                        img.replaceWith(fallback);
                    };
                    products.appendChild(img);
                } else {
                    const t = document.createElement('div');
                    t.className = 'db-thumb';
                    t.title = p.name || '';
                    products.appendChild(t);
                }
            });
            card.appendChild(products);
        }

        const foot = document.createElement('div');
        foot.className = 'db-card-foot';

        const left = document.createElement('div');
        left.style.cssText = 'display:flex; align-items:center; gap:6px;';
        const avatar = document.createElement('div');
        avatar.className = 'db-avatar';
        avatar.textContent = post.assigned_to ? '·' : '—';
        left.appendChild(avatar);
        const meta = document.createElement('span');
        meta.textContent = post.scheduled_for
            ? new Date(post.scheduled_for).toLocaleString('sq-AL', { hour: '2-digit', minute: '2-digit' })
            : (post.reference_url ? 'Reference OK' : 'Pa reference');
        left.appendChild(meta);
        foot.appendChild(left);

        const plat = document.createElement('div');
        plat.className = 'db-plat';
        (post.target_platforms || []).slice(0, 3).forEach(p => {
            const tag = document.createElement('div');
            tag.className = 'db-plat-tag';
            tag.textContent = String(p).slice(0, 2).toUpperCase();
            plat.appendChild(tag);
        });
        foot.appendChild(plat);

        card.appendChild(foot);
        return card;
    }

    function selectPost(postId) {
        state.selectedPostId = postId;
        const post = findPostById(postId);
        if (!post) return;

        document.querySelectorAll('.db-card').forEach(el =>
            el.classList.toggle('selected', parseInt(el.dataset.postId, 10) === postId)
        );

        renderSheet(post);
    }

    function findPostById(id) {
        if (!state.kanban) return null;
        for (const col of state.kanban.columns) {
            const p = col.posts.find(x => x.id === id);
            if (p) return p;
        }
        return null;
    }

    function renderSheet(post) {
        const sheet = document.getElementById('dbSheet');
        sheet.textContent = '';

        if (!post) {
            const ph = document.createElement('div');
            ph.className = 'db-sheet-placeholder';
            ph.textContent = 'Kliko një kartë më lart për të parë detajet.';
            sheet.appendChild(ph);
            return;
        }

        const currentIdx = STAGE_ORDER.indexOf(post.stage);

        // Head
        const head = document.createElement('div');
        head.className = 'db-sheet-head';
        const crumb = document.createElement('div');
        crumb.className = 'db-sheet-crumb';
        crumb.textContent = (state.selectedDate || '') + ' · ' + post.post_type_label;
        const title = document.createElement('div');
        title.className = 'db-sheet-title';
        title.textContent = post.title;
        head.append(crumb, title);
        sheet.appendChild(head);

        // Stepper
        const track = document.createElement('div');
        track.className = 'db-track';
        STAGE_ORDER.forEach((s, i) => {
            const step = document.createElement('div');
            const cls = i < currentIdx ? 'done' : (i === currentIdx ? 'current' : 'todo');
            step.className = 'db-track-step ' + cls;
            const line = document.createElement('div');
            line.className = 'db-track-line';
            const lbl = document.createElement('div');
            lbl.className = 'db-track-lbl';
            lbl.textContent = STAGE_LABELS[s];
            step.append(line, lbl);
            track.appendChild(step);
        });
        sheet.appendChild(track);

        // Body
        const body = document.createElement('div');
        body.className = 'db-sheet-body';

        body.appendChild(section('Produktet', () => {
            if (!post.products || post.products.length === 0) {
                const v = document.createElement('div');
                v.className = 'db-sec-val muted';
                v.textContent = 'Asnjë produkt i caktuar';
                return v;
            }
            const wrap = document.createElement('div');
            post.products.forEach(p => {
                const row = document.createElement('div');
                row.className = 'db-prod-row';

                if (p.image_url) {
                    const img = document.createElement('img');
                    img.className = 'db-thumb';
                    img.src = p.image_url;
                    img.alt = p.name || '';
                    img.onerror = () => {
                        const fb = document.createElement('div');
                        fb.className = 'db-thumb';
                        img.replaceWith(fb);
                    };
                    row.appendChild(img);
                } else {
                    const thumb = document.createElement('div');
                    thumb.className = 'db-thumb';
                    row.appendChild(thumb);
                }

                const info = document.createElement('div');
                info.style.cssText = 'flex: 1; min-width: 0;';
                const name = document.createElement('div');
                name.className = 'db-prod-row-name';
                name.style.cssText = 'overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
                name.textContent = p.name || ('Product #' + num(p.item_group_id));
                const role = document.createElement('div');
                role.className = 'db-prod-row-role';
                const bits = [];
                if (p.is_hero) bits.push('Hero');
                if (p.code) bits.push(p.code);
                if (p.classification) bits.push(p.classification);
                role.textContent = bits.join(' · ') || 'Anëtar';
                info.append(name, role);
                row.appendChild(info);
                wrap.appendChild(row);
            });
            return wrap;
        }));

        body.appendChild(section('Reference', () => {
            const v = document.createElement('div');
            v.className = 'db-sec-val';
            if (post.reference_url) {
                const a = document.createElement('a');
                a.href = post.reference_url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.style.cssText = 'color: var(--db-text); text-decoration: underline;';
                a.textContent = post.reference_url;
                v.appendChild(a);
            } else {
                v.classList.add('muted');
                v.textContent = 'Pa reference';
            }
            return v;
        }));

        body.appendChild(section('Platformat', () => {
            const v = document.createElement('div');
            v.className = 'db-sec-val';
            if (post.target_platforms && post.target_platforms.length) {
                v.textContent = post.target_platforms.join(', ');
            } else {
                v.classList.add('muted');
                v.textContent = 'Pa platforma ende';
            }
            return v;
        }));

        body.appendChild(section('Caption', () => {
            const v = document.createElement('div');
            v.className = 'db-sec-val';
            if (post.caption) {
                v.textContent = post.caption;
            } else {
                v.classList.add('muted');
                v.textContent = 'Do plotësohet në fazën e editimit';
            }
            return v;
        }));

        sheet.appendChild(body);

        // Footer
        const foot = document.createElement('div');
        foot.className = 'db-sheet-foot';

        const btnBack = document.createElement('button');
        btnBack.className = 'db-btn';
        btnBack.textContent = '← Kthe';
        btnBack.disabled = currentIdx <= 0;
        btnBack.addEventListener('click', () => transition(post, STAGE_ORDER[currentIdx - 1]));
        foot.appendChild(btnBack);

        const group = document.createElement('div');
        group.className = 'db-btn-group';

        const btnEdit = document.createElement('button');
        btnEdit.className = 'db-btn';
        btnEdit.textContent = 'Edit';
        btnEdit.addEventListener('click', () => alert('Editim i detajuar: v2'));
        group.appendChild(btnEdit);

        const btnForward = document.createElement('button');
        btnForward.className = 'db-btn db-btn-primary';
        const canForward = currentIdx < STAGE_ORDER.length - 1;
        btnForward.textContent = canForward
            ? 'Kalo te ' + STAGE_LABELS[STAGE_ORDER[currentIdx + 1]] + ' →'
            : 'Faza finale';
        btnForward.disabled = !canForward;
        btnForward.addEventListener('click', () => transition(post, STAGE_ORDER[currentIdx + 1]));
        group.appendChild(btnForward);

        foot.appendChild(group);
        sheet.appendChild(foot);
    }

    function section(label, buildVal) {
        const wrap = document.createElement('div');
        wrap.className = 'db-sec';
        const lbl = document.createElement('div');
        lbl.className = 'db-sec-lbl';
        lbl.textContent = label;
        wrap.appendChild(lbl);
        wrap.appendChild(buildVal());
        return wrap;
    }

    async function transition(post, targetStage) {
        if (!targetStage) return;
        try {
            await apiPost(
                '/marketing/daily-basket/api/posts/' + num(post.id) + '/transition',
                { stage: targetStage }
            );
            await selectDay(state.selectedDate);
            state.selectedPostId = post.id;
            const refreshed = findPostById(post.id);
            if (refreshed) selectPost(post.id);
        } catch (e) {
            showError(e.message);
        }
    }

    // ── New-post modal ─────────────────────────────────
    function openNewPostModal() {
        state.modal = {
            title: '',
            post_type: null,
            priority: 'normal',
            selectedProductIds: new Set(),
            heroProductId: null,
        };

        document.getElementById('dbFieldTitle').value = '';

        // Clear active states on segmented controls
        document.querySelectorAll('#dbFieldType .db-seg-opt').forEach(el =>
            el.classList.remove('active')
        );
        document.querySelectorAll('#dbFieldPriority .db-seg-opt').forEach(el => {
            el.classList.toggle('active', el.dataset.value === 'normal');
        });

        renderProductPicker();
        document.getElementById('dbModal').classList.add('open');
        setTimeout(() => document.getElementById('dbFieldTitle').focus(), 50);
    }

    function closeNewPostModal() {
        document.getElementById('dbModal').classList.remove('open');
    }

    function renderProductPicker() {
        const host = document.getElementById('dbFieldProducts');
        host.textContent = '';

        if (state.availableProducts.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'db-picker-empty';
            empty.textContent = 'Pa produkte nga kolekcioni';
            host.appendChild(empty);
            return;
        }

        state.availableProducts.forEach(p => {
            const item = document.createElement('div');
            item.className = 'db-picker-item';
            item.dataset.productId = p.id;
            if (state.modal.selectedProductIds.has(p.id)) {
                item.classList.add('selected');
            }

            const thumb = document.createElement('div');
            thumb.className = 'db-picker-thumb';
            if (p.image_url) {
                const img = document.createElement('img');
                img.className = 'db-picker-thumb';
                img.src = p.image_url;
                img.alt = '';
                img.onerror = () => { img.replaceWith(thumb); };
                item.appendChild(img);
            } else {
                item.appendChild(thumb);
            }

            const info = document.createElement('div');
            info.style.cssText = 'flex: 1; min-width: 0;';
            const name = document.createElement('div');
            name.className = 'db-picker-name';
            name.style.cssText = 'overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
            name.textContent = p.name;
            const sub = document.createElement('div');
            sub.className = 'db-picker-sub';
            const bits = [];
            if (p.code) bits.push(p.code);
            if (p.classification) bits.push(p.classification);
            if (p.avg_price != null) bits.push('€' + Math.round(+p.avg_price));
            sub.textContent = bits.join(' · ');
            info.append(name, sub);

            const check = document.createElement('div');
            check.className = 'db-picker-check';
            check.textContent = state.modal.selectedProductIds.has(p.id) ? '✓' : '';

            item.append(info, check);
            item.addEventListener('click', () => toggleProduct(num(p.id)));
            host.appendChild(item);
        });
    }

    function toggleProduct(id) {
        if (state.modal.selectedProductIds.has(id)) {
            state.modal.selectedProductIds.delete(id);
            if (state.modal.heroProductId === id) state.modal.heroProductId = null;
        } else {
            state.modal.selectedProductIds.add(id);
            if (!state.modal.heroProductId) state.modal.heroProductId = id;
        }
        renderProductPicker();
    }

    async function submitNewPost() {
        const title = document.getElementById('dbFieldTitle').value.trim();
        const postType = state.modal.post_type;

        if (!title) { showError('Titulli është i detyrueshëm'); return; }
        if (!postType) { showError('Zgjidh një tip posti'); return; }

        const basketId = state.kanban?.basket?.id;
        if (!basketId) { showError('Basket-i nuk është i ngarkuar'); return; }

        const productIds = Array.from(state.modal.selectedProductIds);

        try {
            await apiPost('/marketing/daily-basket/api/baskets/' + num(basketId) + '/posts', {
                title,
                post_type: postType,
                priority: state.modal.priority,
                product_ids: productIds,
                hero_product_id: state.modal.heroProductId,
            });
            closeNewPostModal();
            await selectDay(state.selectedDate);
        } catch (e) {
            showError('Krijimi i postit dështoi: ' + e.message);
        }
    }

    function wireModalOnce() {
        document.getElementById('dbBtnNewPost').addEventListener('click', openNewPostModal);
        document.getElementById('dbModalClose').addEventListener('click', closeNewPostModal);
        document.getElementById('dbModalCancel').addEventListener('click', closeNewPostModal);
        document.getElementById('dbModalSubmit').addEventListener('click', submitNewPost);

        document.getElementById('dbModal').addEventListener('click', (e) => {
            if (e.target.id === 'dbModal') closeNewPostModal();
        });

        // Segmented controls
        document.querySelectorAll('#dbFieldType .db-seg-opt').forEach(el => {
            el.addEventListener('click', () => {
                state.modal.post_type = el.dataset.value;
                document.querySelectorAll('#dbFieldType .db-seg-opt').forEach(o => o.classList.remove('active'));
                el.classList.add('active');
            });
        });
        document.querySelectorAll('#dbFieldPriority .db-seg-opt').forEach(el => {
            el.addEventListener('click', () => {
                state.modal.priority = el.dataset.value;
                document.querySelectorAll('#dbFieldPriority .db-seg-opt').forEach(o => o.classList.remove('active'));
                el.classList.add('active');
            });
        });

        // Esc closes modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('dbModal').classList.contains('open')) {
                closeNewPostModal();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        wireModalOnce();
        loadCollection();
    });
})();
</script>
@endsection
