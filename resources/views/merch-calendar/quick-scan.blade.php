@extends('_layouts.app', [
    'title'     => 'Quick Scan — Merch Calendar',
    'pageTitle' => 'Quick Scan',
])

@section('styles')
<style>
    .qs-wrap { font-family: Inter, system-ui, sans-serif; color: #0f172a; }

    .qs-ctx { display: flex; align-items: center; gap: 24px; padding: 12px 0; border-bottom: 1px solid #e5e7eb; margin-bottom: 24px; flex-wrap: wrap; }
    .qs-ctx-item { display: flex; align-items: center; gap: 8px; }
    .qs-ctx-lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
    .qs-ctx-val { font-size: 13px; font-weight: 600; color: #0f172a; padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 5px; background: #fff; }
    .qs-ctx-day-select { font-size: 13px; font-weight: 600; padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 5px; background: #fff; cursor: pointer; }
    .qs-ctx-day-select:disabled { background: #f8fafc; cursor: not-allowed; opacity: 0.7; }
    .qs-ctx-meta { margin-left: auto; font-size: 12px; color: #64748b; }
    .qs-ctx-meta strong { color: #0f172a; }

    .qs-scan { background: #fff; border: 2px dashed #c7d2fe; border-radius: 14px; padding: 28px; text-align: center; max-width: 720px; margin: 0 auto 18px; }
    .qs-scan-icon { font-size: 32px; color: #818cf8; margin-bottom: 10px; }
    .qs-scan-title { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
    .qs-scan-hint { font-size: 12px; color: #94a3b8; margin-bottom: 16px; }
    .qs-scan-input { width: 100%; max-width: 480px; padding: 14px 18px; font-size: 18px; font-family: 'SF Mono', monospace; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; letter-spacing: 0.05em; color: #0f172a; }
    .qs-scan-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

    .qs-feedback { max-width: 720px; margin: 0 auto 16px; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; gap: 12px; font-size: 13px; }
    .qs-feedback.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .qs-feedback.warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .qs-feedback.info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .qs-feedback.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .qs-feedback-msg { flex: 1; }

    .qs-summary { max-width: 720px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: space-between; padding: 0 4px; }
    .qs-summary-count { font-size: 13px; color: #475569; }
    .qs-summary-count strong { color: #0f172a; font-weight: 700; font-size: 16px; }
    .qs-btn { padding: 6px 12px; font-size: 12px; font-weight: 500; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; }
    .qs-btn:hover:not(:disabled) { background: #f8fafc; }
    .qs-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .qs-list { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    .qs-list-empty { padding: 50px 20px; text-align: center; color: #94a3b8; font-size: 13px; }
    .qs-row { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
    .qs-row:last-child { border-bottom: 0; }
    .qs-row.just-added { background: #ecfdf5; animation: qs-flash 1.2s ease-out; }
    @keyframes qs-flash { 0% { background: #d1fae5; } 100% { background: #fff; } }
    .qs-num { width: 32px; height: 32px; flex-shrink: 0; background: #f1f5f9; color: #475569; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
    .qs-img { width: 48px; height: 48px; flex-shrink: 0; background: #f1f5f9; border-radius: 6px; object-fit: cover; }
    .qs-img-placeholder { width: 48px; height: 48px; flex-shrink: 0; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #cbd5e1; }
    .qs-info { flex: 1; min-width: 0; }
    .qs-name { font-size: 13px; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .qs-meta { font-size: 11px; color: #94a3b8; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .qs-badges { display: flex; gap: 4px; margin-top: 4px; }
    .qs-badge { font-size: 9px; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
    .qs-badge-best_seller { background: #fef3c7; color: #92400e; }
    .qs-badge-karrem { background: #d1fae5; color: #065f46; }
    .qs-badge-fashion { background: #fce7f3; color: #9d174d; }
    .qs-badge-plotesues { background: #e0e7ff; color: #3730a3; }
    .qs-badge-new { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .qs-right { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
    .qs-price { font-size: 13px; font-weight: 700; color: #0f172a; }
    .qs-stock { font-size: 11px; color: #64748b; }
    .qs-remove { width: 28px; height: 28px; border: 1px solid transparent; background: transparent; color: #94a3b8; cursor: pointer; border-radius: 6px; font-size: 16px; line-height: 1; }
    .qs-remove:hover { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

    .qs-savebar { position: sticky; bottom: 12px; max-width: 720px; margin: 14px auto 0; background: #18181b; color: #fff; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 24px rgba(0,0,0,0.18); }
    .qs-savebar-info { font-size: 13px; }
    .qs-savebar-info strong { font-weight: 700; }
    .qs-savebar-info .small { color: #a1a1aa; font-size: 11px; margin-top: 2px; display: block; }
    .qs-savebar-actions { display: flex; gap: 8px; }
    .qs-savebar .qs-btn-cancel { background: transparent; color: #d4d4d8; border-color: #3f3f46; }
    .qs-savebar .qs-btn-cancel:hover { background: #27272a; color: #fff; }
    .qs-savebar .qs-btn-save { background: #22c55e; color: #fff; border-color: #22c55e; font-weight: 600; padding: 8px 16px; }
    .qs-savebar .qs-btn-save:hover:not(:disabled) { background: #16a34a; }
</style>
@endsection

@section('content')
<div class="qs-wrap">

    <div class="qs-ctx">
        <div class="qs-ctx-item">
            <span class="qs-ctx-lbl">Kolekcioni</span>
            <span class="qs-ctx-val" id="qsCollName">Duke ngarkuar…</span>
        </div>
        <div class="qs-ctx-item">
            <span class="qs-ctx-lbl">Dita</span>
            <select class="qs-ctx-day-select" id="qsDaySelect" disabled>
                <option>Po ngarkohet…</option>
            </select>
        </div>
        <div class="qs-ctx-meta" id="qsCollMeta">—</div>
    </div>

    <div class="qs-scan">
        <div class="qs-scan-icon">📷</div>
        <div class="qs-scan-title">Skano barcode-in</div>
        <div class="qs-scan-hint">Palmar-i dergon code + Enter automatikisht. Mund te shkruash dhe me dore.</div>
        <input type="text" class="qs-scan-input" id="qsScanInput" placeholder="Skano ose shkruaj barcode…" autocomplete="off" disabled>
    </div>

    <div id="qsFeedback"></div>

    <div class="qs-summary">
        <div class="qs-summary-count" id="qsSummary"><strong>0</strong> produkte ne shporte</div>
        <div>
            <button class="qs-btn" id="qsClearBtn" disabled>Pastro te gjitha</button>
        </div>
    </div>

    <div class="qs-list" id="qsList">
        <div class="qs-list-empty">Ende pa skanim. Klik tek input-i lart dhe filo te skanesh.</div>
    </div>

    <div class="qs-savebar" id="qsSaveBar" style="display:none;">
        <div class="qs-savebar-info">
            <strong id="qsSaveBarText">0 produkte</strong>
            <span class="small">Klik "Ruaj" per te krijuar caktimet ne distribution_week_item_group_dates</span>
        </div>
        <div class="qs-savebar-actions">
            <button class="qs-btn qs-btn-cancel" id="qsCancelBtn">Anulo</button>
            <button class="qs-btn qs-btn-save" id="qsSaveBtn">Ruaj shporten →</button>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const DAY_NAMES = ['Die', 'Hen', 'Mar', 'Mer', 'Enj', 'Pre', 'Sht'];
    const MONTH_NAMES = ['Jan', 'Shk', 'Mar', 'Pri', 'Maj', 'Qer', 'Kor', 'Gsh', 'Sht', 'Tet', 'Nën', 'Dhj'];

    const qs = (sel) => document.querySelector(sel);
    function num(n) { return Number.isFinite(+n) ? +n : 0; }
    function getQuery(name) { return new URLSearchParams(location.search).get(name); }

    function parseYmd(ymd) { const [y, m, d] = String(ymd).split('-').map(Number); return new Date(y, m - 1, d); }
    function toYmd(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    function fmtDayLabel(ymd) {
        if (!ymd) return '';
        const dt = parseYmd(ymd);
        return `${DAY_NAMES[dt.getDay()]} · ${dt.getDate()} ${MONTH_NAMES[dt.getMonth()]}`;
    }

    function clearChildren(node) {
        while (node.firstChild) node.removeChild(node.firstChild);
    }

    const state = {
        weekId: parseInt(getQuery('week'), 10) || null,
        week: null,
        scanLocked: false,
        scans: [],
        seenIds: new Set(),
    };

    const STORAGE_KEY = (weekId, date) => `qs_session:${weekId}:${date}`;

    async function bootstrap() {
        if (!state.weekId) {
            qs('#qsCollName').textContent = 'Pa kolekcion ne URL';
            return;
        }
        try {
            const res = await fetch('/marketing/merch-calendar/api/weeks/' + state.weekId, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            state.week = await res.json();
        } catch (e) {
            qs('#qsCollName').textContent = 'Ngarkimi deshtoi: ' + e.message;
            return;
        }

        qs('#qsCollName').textContent = state.week.name;
        const meta = qs('#qsCollMeta');
        clearChildren(meta);
        const groups = (state.week.item_groups || []).length;
        meta.appendChild(document.createTextNode('Kolekcioni ka '));
        const strong = document.createElement('strong');
        strong.textContent = groups + ' grupe';
        meta.appendChild(strong);
        meta.appendChild(document.createTextNode(' · sesioni do shtoje produkte per nje dite te vetme'));

        renderDaySelector();

        const today = toYmd(new Date());
        const days = generateDays();
        const initialDay = days.includes(today) ? today : days[0];
        if (initialDay) {
            qs('#qsDaySelect').value = initialDay;
            tryRestoreSession(initialDay);
        }

        qs('#qsScanInput').disabled = false;
        qs('#qsDaySelect').disabled = state.scanLocked;
        qs('#qsScanInput').focus();
    }

    function generateDays() {
        if (!state.week) return [];
        const days = [];
        for (let d = parseYmd(state.week.week_start); d <= parseYmd(state.week.week_end); d.setDate(d.getDate() + 1)) {
            days.push(toYmd(d));
        }
        return days;
    }

    function renderDaySelector() {
        const sel = qs('#qsDaySelect');
        clearChildren(sel);
        generateDays().forEach(ymd => {
            const opt = document.createElement('option');
            opt.value = ymd;
            opt.textContent = fmtDayLabel(ymd);
            sel.appendChild(opt);
        });
    }

    function tryRestoreSession(day) {
        try {
            const saved = localStorage.getItem(STORAGE_KEY(state.weekId, day));
            if (!saved) return;
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed.scans) && parsed.scans.length) {
                state.scans = parsed.scans;
                state.seenIds = new Set(parsed.scans.map(s => s.item_group_id));
                state.scanLocked = true;
                qs('#qsDaySelect').disabled = true;
                renderList();
                renderSummary();
                showFeedback('info', `🔄 U ngarkua nje sesion i ruajtur me ${state.scans.length} produkte.`);
            }
        } catch (e) { console.warn('Restore session failed', e); }
    }

    function persistSession() {
        if (!state.weekId) return;
        const day = qs('#qsDaySelect').value;
        if (!day) return;
        try {
            localStorage.setItem(STORAGE_KEY(state.weekId, day), JSON.stringify({
                scans: state.scans, ts: Date.now(),
            }));
        } catch (e) {}
    }

    function clearPersistedSession() {
        if (!state.weekId) return;
        const day = qs('#qsDaySelect').value;
        if (!day) return;
        try { localStorage.removeItem(STORAGE_KEY(state.weekId, day)); } catch (e) {}
    }

    async function processScan(rawBarcode) {
        const barcode = (rawBarcode || '').trim();
        if (!barcode) return;
        try {
            const url = `/marketing/merch-calendar/api/items/by-barcode?barcode=${encodeURIComponent(barcode)}&week_id=${state.weekId}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const body = await res.json();
            if (!res.ok || body.success === false) {
                showFeedback('error', `❌ Barcode "${barcode}" nuk u gjet ne sistem`);
                return;
            }
            const data = body.data || body;
            const groupId = num(data.item_group_id);
            if (!groupId) {
                showFeedback('error', `❌ Barcode "${barcode}" u gjet por pa item_group_id`);
                return;
            }
            if (state.seenIds.has(groupId)) {
                showFeedback('warning', `⚠️ ${data.name || data.cf_group} eshte tashme ne shporte · u kapercye`);
                return;
            }
            const inCollection = (state.week.item_groups || []).some(g => Number(g.id) === groupId);
            const entry = {
                item_group_id: groupId,
                name: data.name || data.cf_group,
                code: data.cf_group || '',
                image_url: data.image_url || null,
                classification: data.classification || null,
                vendor_name: data.vendor_name || null,
                avg_price: data.avg_price || null,
                total_stock: data.total_stock || 0,
                addedAsRemarketing: !inCollection,
            };
            state.scans.unshift(entry);
            state.seenIds.add(groupId);
            state.scanLocked = true;
            qs('#qsDaySelect').disabled = true;
            persistSession();
            renderList(true);
            renderSummary();
            const msg = entry.addedAsRemarketing
                ? `📦 ${entry.name} nuk ishte ne kete kolekcion. U shtua dhe do caktohet si re-marketing.`
                : `✓ ${entry.name} u shtua ne shporte · ${entry.code}`;
            showFeedback(entry.addedAsRemarketing ? 'info' : 'success', msg);
        } catch (e) {
            showFeedback('error', `❌ Lookup deshtoi: ${e.message}`);
        }
    }

    function showFeedback(kind, msg) {
        const host = qs('#qsFeedback');
        clearChildren(host);
        const div = document.createElement('div');
        div.className = 'qs-feedback ' + kind;
        const span = document.createElement('div');
        span.className = 'qs-feedback-msg';
        span.textContent = msg;
        div.appendChild(span);
        host.appendChild(div);
    }

    function renderList(flashFirst = false) {
        const host = qs('#qsList');
        clearChildren(host);
        if (state.scans.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'qs-list-empty';
            empty.textContent = 'Ende pa skanim. Klik tek input-i lart dhe filo te skanesh.';
            host.appendChild(empty);
            qs('#qsSaveBar').style.display = 'none';
            qs('#qsClearBtn').disabled = true;
            return;
        }
        qs('#qsClearBtn').disabled = false;
        qs('#qsSaveBar').style.display = 'flex';
        state.scans.forEach((s, idx) => host.appendChild(buildRow(s, state.scans.length - idx, idx === 0 && flashFirst)));
    }

    function buildRow(scan, displayNum, flash) {
        const row = document.createElement('div');
        row.className = 'qs-row' + (flash ? ' just-added' : '');

        const numEl = document.createElement('div');
        numEl.className = 'qs-num';
        numEl.textContent = String(displayNum);
        row.appendChild(numEl);

        if (scan.image_url) {
            const img = document.createElement('img');
            img.className = 'qs-img';
            img.src = scan.image_url;
            img.alt = '';
            img.onerror = () => { img.replaceWith(makePlaceholder()); };
            row.appendChild(img);
        } else {
            row.appendChild(makePlaceholder());
        }

        const info = document.createElement('div');
        info.className = 'qs-info';
        const name = document.createElement('div');
        name.className = 'qs-name';
        name.textContent = scan.name;
        const meta = document.createElement('div');
        meta.className = 'qs-meta';
        meta.textContent = [scan.code, scan.vendor_name].filter(Boolean).join(' · ');
        info.append(name, meta);

        if (scan.classification || scan.addedAsRemarketing) {
            const badges = document.createElement('div');
            badges.className = 'qs-badges';
            if (scan.classification) {
                const b = document.createElement('span');
                b.className = 'qs-badge qs-badge-' + scan.classification;
                b.textContent = scan.classification.replace('_', ' ');
                badges.appendChild(b);
            }
            if (scan.addedAsRemarketing) {
                const b = document.createElement('span');
                b.className = 'qs-badge qs-badge-new';
                b.textContent = '+ I shtuar (re-marketing)';
                badges.appendChild(b);
            }
            info.appendChild(badges);
        }
        row.appendChild(info);

        const right = document.createElement('div');
        right.className = 'qs-right';
        const priceWrap = document.createElement('div');
        const price = document.createElement('div');
        price.className = 'qs-price';
        price.textContent = scan.avg_price != null ? '€' + Math.round(+scan.avg_price) : '—';
        const stock = document.createElement('div');
        stock.className = 'qs-stock';
        stock.textContent = (scan.total_stock || 0) + ' stk';
        priceWrap.append(price, stock);
        right.appendChild(priceWrap);

        const remove = document.createElement('button');
        remove.className = 'qs-remove';
        remove.textContent = '×';
        remove.title = 'Hiq nga sesioni';
        remove.addEventListener('click', () => removeScan(scan.item_group_id));
        right.appendChild(remove);

        row.appendChild(right);
        return row;
    }

    function makePlaceholder() {
        const ph = document.createElement('div');
        ph.className = 'qs-img-placeholder';
        ph.textContent = '📦';
        return ph;
    }

    function renderSummary() {
        const count = state.scans.length;
        const counter = qs('#qsSummary');
        clearChildren(counter);
        const strong = document.createElement('strong');
        strong.textContent = String(count);
        counter.append(strong, document.createTextNode(' produkte ne shporte'));
        qs('#qsSaveBarText').textContent = `${count} produkte per ${fmtDayLabel(qs('#qsDaySelect').value || '')}`;
    }

    function removeScan(groupId) {
        state.scans = state.scans.filter(s => s.item_group_id !== groupId);
        state.seenIds.delete(groupId);
        if (state.scans.length === 0) {
            state.scanLocked = false;
            qs('#qsDaySelect').disabled = false;
            clearPersistedSession();
        } else {
            persistSession();
        }
        renderList();
        renderSummary();
    }

    async function saveSession() {
        if (state.scans.length === 0) return;
        const day = qs('#qsDaySelect').value;
        const ids = state.scans.map(s => s.item_group_id);
        const saveBtn = qs('#qsSaveBtn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Po ruan…';
        try {
            const res = await fetch('/marketing/merch-calendar/api/weeks/' + state.weekId + '/quick-scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ date: day, item_group_ids: ids }),
            });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message || 'HTTP ' + res.status);
            clearPersistedSession();
            state.scans = [];
            state.seenIds.clear();
            state.scanLocked = false;
            qs('#qsDaySelect').disabled = false;
            renderList();
            renderSummary();
            const summary = `✓ Ruajtur: ${body.saved} caktime`
                + (body.added_to_collection_as_remarketing ? ` (${body.added_to_collection_as_remarketing} si re-marketing)` : '')
                + (body.skipped_duplicates ? ` · ${body.skipped_duplicates} duplikate u kapercyen` : '');
            showFeedback('success', summary);
        } catch (e) {
            showFeedback('error', `❌ Ruajtja deshtoi: ${e.message}`);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Ruaj shporten →';
        }
    }

    function clearAll() {
        if (state.scans.length === 0) return;
        if (!confirm(`Hiq te gjithe ${state.scans.length} produktet nga sesioni?`)) return;
        state.scans = [];
        state.seenIds.clear();
        state.scanLocked = false;
        qs('#qsDaySelect').disabled = false;
        clearPersistedSession();
        renderList();
        renderSummary();
        showFeedback('info', 'Sesioni u pastrua');
    }

    function wire() {
        qs('#qsScanInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const v = e.target.value;
                e.target.value = '';
                processScan(v);
            }
        });
        qs('#qsClearBtn').addEventListener('click', clearAll);
        qs('#qsSaveBtn').addEventListener('click', saveSession);
        qs('#qsCancelBtn').addEventListener('click', () => {
            if (state.scans.length === 0 || confirm('Sesioni do humbasi. Vazhdo?')) {
                clearPersistedSession();
                location.href = '/marketing/merch-calendar';
            }
        });
        qs('#qsDaySelect').addEventListener('change', () => {
            if (state.scanLocked) return;
            const newDay = qs('#qsDaySelect').value;
            tryRestoreSession(newDay);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        wire();
        bootstrap();
    });
})();
</script>
@endsection
