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

    /* Section header ne product picker (Produkte te caktuara / Te gjitha) */
    .db-picker-section {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 4px 6px;
        font-size: 11px;
        font-weight: 700;
        color: var(--db-text-2);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .db-picker-section.secondary { color: var(--db-text-3); }
    .db-picker-section-count {
        font-size: 10px;
        font-weight: 600;
        color: var(--db-text-3);
        background: var(--db-accent-soft);
        padding: 2px 8px;
        border-radius: 10px;
    }
    .db-picker-section-hint {
        font-size: 10px;
        font-weight: 400;
        color: var(--db-text-3);
        text-transform: none;
        letter-spacing: 0;
        margin-left: auto;
    }
    .db-picker-item.is-assigned {
        background: #eef7ff;
        border: 1px solid #bfdbfe;
    }
    .db-picker-item.is-assigned.selected {
        background: #dbeafe;
        border-color: #60a5fa;
    }

    /* Quick-post butoni ne karten e panoramen */
    .db-pano-quick-post {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--db-text);
        color: #fff;
        border: none;
        border-radius: 14px;
        padding: 4px 10px;
        font-size: 10px;
        font-weight: 600;
        cursor: pointer;
        opacity: 0;
        transform: translateY(-2px);
        transition: opacity 0.15s, transform 0.15s;
        white-space: nowrap;
    }
    .db-pano-card { position: relative; }
    .db-pano-card:hover .db-pano-quick-post,
    .db-pano-card:focus-within .db-pano-quick-post {
        opacity: 1;
        transform: translateY(0);
    }
    .db-pano-quick-post:hover { background: #27272a; }
    /* On touch devices we can't hover — show button always */
    @media (hover: none) {
        .db-pano-quick-post { opacity: 1; transform: translateY(0); }
    }

    /* Collection picker (dropdown in the collection row) */
    .db-coll-picker { position: relative; }
    .db-coll-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        border: 1px solid transparent;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
        font: inherit;
        color: var(--db-text);
    }
    .db-coll-trigger:hover { background: var(--db-accent-soft); border-color: var(--db-border); }
    .db-coll-trigger-name { font-weight: 500; }
    .db-coll-trigger-caret { font-size: 10px; color: var(--db-text-3); }

    .db-coll-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: -8px;
        width: 360px;
        max-height: 420px;
        overflow-y: auto;
        background: var(--db-surface);
        border: 1px solid var(--db-border);
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        z-index: 100;
        display: none;
        padding: 6px;
    }
    .db-coll-menu.open { display: block; }
    .db-coll-menu-section {
        font-size: 10px;
        font-weight: 600;
        color: var(--db-text-3);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 10px 10px 4px;
    }
    .db-coll-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: 6px;
        cursor: pointer;
    }
    .db-coll-menu-item:hover { background: var(--db-accent-soft); }
    .db-coll-menu-item.active { background: #eef2ff; }
    .db-coll-menu-item-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--db-text-3);
        flex-shrink: 0;
    }
    .db-coll-menu-item.is-current .db-coll-menu-item-dot { background: #22c55e; }
    .db-coll-menu-item.is-upcoming .db-coll-menu-item-dot { background: #3b82f6; }
    .db-coll-menu-item-body { flex: 1; min-width: 0; }
    .db-coll-menu-item-name {
        font-size: 13px;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .db-coll-menu-item-sub { font-size: 11px; color: var(--db-text-3); margin-top: 1px; }
    .db-coll-menu-item-badge {
        font-size: 9px;
        padding: 2px 7px;
        border-radius: 10px;
        background: var(--db-accent-soft);
        color: var(--db-text-2);
        font-weight: 600;
    }
    .db-coll-menu-item.is-current .db-coll-menu-item-badge { background: #dcfce7; color: #166534; }
    .db-coll-menu-item.is-upcoming .db-coll-menu-item-badge { background: #dbeafe; color: #1e40af; }

    .db-coll-menu-empty { padding: 30px; text-align: center; color: var(--db-text-3); font-size: 12px; }

    /* Detail sheet header — Edit button prominent */
    .db-sheet-head { display: flex; justify-content: space-between; align-items: flex-start; }
    .db-sheet-head-text { flex: 1; min-width: 0; }
    .db-btn-edit {
        background: var(--db-accent-soft);
        color: var(--db-text);
        border: 1px solid var(--db-border-strong);
        padding: 7px 14px;
        font-size: 12px;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        margin-left: 12px;
    }
    .db-btn-edit:hover { background: var(--db-text); color: #fff; border-color: var(--db-text); }
    .db-btn-edit-icon { font-size: 14px; line-height: 1; }

    /* Inline reference input */
    .db-inline-input {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid var(--db-border);
        border-radius: 5px;
        font-family: inherit;
        font-size: 12px;
        color: var(--db-text);
        background: #fff;
    }
    .db-inline-input:focus { outline: none; border-color: var(--db-text); }
    .db-inline-hint { font-size: 10px; color: var(--db-text-3); margin-top: 4px; }

    /* ─── Panorama sidebar (orientim per produktet e dites) ────── */
    .db-pano-btn {
        background: #eef2ff; color: #4338ca;
        border: 1px solid #c7d2fe; border-radius: 6px;
        padding: 7px 12px; font-size: 12px; font-weight: 500;
        cursor: pointer; margin-right: 8px;
    }
    .db-pano-btn:hover:not(:disabled) { background: #e0e7ff; }
    .db-pano-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .db-pano {
        display: none;
        position: fixed; top: 0; right: 0; bottom: 0;
        width: 420px; z-index: 9985;
        background: #fff; border-left: 1px solid #e5e7eb;
        box-shadow: -4px 0 24px rgba(0,0,0,0.08);
        font-family: Inter, system-ui, sans-serif;
        flex-direction: column; overflow: hidden;
    }
    .db-pano.open { display: flex; }

    .db-pano-head { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .db-pano-head-title { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.3; }
    .db-pano-head-sub { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .db-pano-close { width: 28px; height: 28px; border: none; background: none; cursor: pointer; border-radius: 6px; font-size: 16px; color: #94a3b8; flex-shrink: 0; }
    .db-pano-close:hover { background: #f1f5f9; color: #0f172a; }

    .db-pano-body { flex: 1; overflow-y: auto; }

    .db-pano-strip { display: flex; padding: 10px 16px; gap: 4px; border-bottom: 1px solid #f1f5f9; }
    .db-pano-day { flex: 1; text-align: center; padding: 6px 4px; border-radius: 5px; font-size: 9px; color: #94a3b8; cursor: pointer; }
    .db-pano-day:hover:not(.active) { background: #f8fafc; color: #475569; }
    .db-pano-day.active { background: #18181b; color: #fff; font-weight: 600; }
    .db-pano-day .num { font-size: 11px; font-weight: 700; margin-top: 2px; }
    .db-pano-day .cnt { font-size: 9px; margin-top: 2px; opacity: 0.85; }

    .db-pano-stats { display: grid; grid-template-columns: repeat(4, 1fr); padding: 14px 16px; border-bottom: 1px solid #f1f5f9; gap: 4px; }
    .db-pano-stat { text-align: center; }
    .db-pano-stat-val { font-size: 15px; font-weight: 700; color: #0f172a; }
    .db-pano-stat-lbl { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 2px; }

    .db-pano-filters { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px 8px; }
    .db-pano-filters-title { font-size: 13px; font-weight: 600; color: #0f172a; }
    .db-pano-filters-btns { display: flex; gap: 3px; flex-wrap: wrap; }
    .db-pano-filter-btn { padding: 3px 8px; font-size: 10px; border-radius: 4px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; }
    .db-pano-filter-btn.active { background: #6366f1; color: #fff; border-color: #6366f1; }
    .db-pano-filter-btn:hover:not(.active) { background: #f8fafc; }

    .db-pano-card { display: flex; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f8fafc; }
    .db-pano-card:hover { background: #fafbfc; }
    .db-pano-img { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: #f1f5f9; }
    .db-pano-img-ph { width: 56px; height: 56px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; color: #cbd5e1; }
    .db-pano-card-info { flex: 1; min-width: 0; }
    .db-pano-card-name { font-size: 12px; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-pano-card-meta { font-size: 10px; color: #94a3b8; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-pano-card-badges { display: flex; gap: 4px; margin-top: 5px; flex-wrap: wrap; }
    .db-pano-badge { display: inline-flex; align-items: center; font-size: 9px; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
    .db-pano-b-best_seller { background: #fef3c7; color: #92400e; }
    .db-pano-b-karrem { background: #d1fae5; color: #065f46; }
    .db-pano-b-fashion { background: #fce7f3; color: #9d174d; }
    .db-pano-b-plotesues { background: #e0e7ff; color: #3730a3; }
    .db-pano-b-rem { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .db-pano-b-used { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .db-pano-b-unused { background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1; }
    .db-pano-card-right { text-align: right; flex-shrink: 0; min-width: 70px; }
    .db-pano-card-price { font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .db-pano-card-stock { font-size: 10px; color: #64748b; margin-top: 2px; }
    .db-pano-card-value { font-size: 10px; color: #16a34a; font-weight: 600; margin-top: 1px; }

    .db-pano-empty { padding: 30px 20px; text-align: center; color: #94a3b8; font-size: 12px; line-height: 1.6; }
    .db-pano-empty strong { color: #475569; display: block; margin-bottom: 6px; }
    .db-pano-fallback-hint { padding: 12px 16px; background: #fffbeb; border-top: 1px dashed #fde68a; font-size: 11px; color: #92400e; line-height: 1.5; text-align: center; }

    /* ─── Inline 3-column edit panel ─────────────────────────────── */
    .db-sheet-body-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; }
    .db-col-group { padding: 20px 24px; border-right: 1px solid var(--db-border); min-width: 0; }
    .db-col-group:last-child { border-right: 0; }
    .db-field-inline { min-width: 0; }
    .db-col-group-title {
        font-size: 10px; color: var(--db-text-3); text-transform: uppercase;
        letter-spacing: 0.08em; font-weight: 700; margin-bottom: 14px;
        display: flex; align-items: center; gap: 6px;
    }
    .db-col-group-icon { font-size: 14px; }

    .db-field-inline { margin-bottom: 14px; }
    .db-field-inline:last-child { margin-bottom: 0; }

    .db-inline-textarea {
        width: 100%;
        padding: 7px 9px;
        border: 1px solid var(--db-border);
        border-radius: 5px;
        font-family: inherit;
        font-size: 12px;
        color: var(--db-text);
        background: #fff;
        resize: vertical;
        min-height: 76px;
    }
    .db-inline-textarea:focus { outline: none; border-color: var(--db-text); box-shadow: 0 0 0 3px rgba(24,24,27,0.05); }

    .db-inline-seg { display: flex; gap: 4px; flex-wrap: wrap; }
    .db-inline-seg .db-seg-opt { padding: 5px 10px; font-size: 11px; border-radius: 5px; }

    .db-save-flash { animation: dbSaveFlash 1.2s; }
    @keyframes dbSaveFlash {
        0% { background: #fff; }
        25% { background: #dcfce7; }
        100% { background: #fff; }
    }

    /* Smart URL preview (favicon + short domain, clickable) */
    .db-url-preview { margin-top: 6px; font-size: 11px; color: var(--db-text-3); min-height: 18px; }
    .db-url-preview.empty { font-style: italic; }
    .db-url-link {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 3px 9px 3px 6px;
        border-radius: 14px;
        background: var(--db-accent-soft);
        color: var(--db-text-2); text-decoration: none;
        font-size: 11px; font-weight: 500;
        max-width: 100%;
        transition: background 0.1s, color 0.1s;
    }
    .db-url-link:hover { background: var(--db-border-strong); color: var(--db-text); }
    .db-url-favicon { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; background: #fff; }
    .db-url-host { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-url-ext { font-size: 10px; opacity: 0.7; flex-shrink: 0; }

    /* ─── Media uploader ─────────────────────────────────────────── */
    .db-media-slot {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        border: 1.5px dashed var(--db-border-strong);
        border-radius: 8px;
        background: #fafafa;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.15s;
        overflow: hidden;
    }
    .db-media-slot:hover { border-color: var(--db-text); background: var(--db-accent-soft); }
    .db-media-slot.is-dragover { border-color: var(--db-text); background: #eef2ff; }
    .db-media-slot.has-media { border-style: solid; cursor: default; padding: 0; }
    .db-media-slot.has-media:hover { background: transparent; border-color: var(--db-border-strong); }
    .db-media-slot.is-uploading { cursor: wait; }
    .db-media-slot-icon { font-size: 28px; color: var(--db-text-3); margin-bottom: 4px; }
    .db-media-slot-txt { font-size: 11px; color: var(--db-text-2); text-align: center; padding: 0 8px; }
    .db-media-preview { width: 100%; height: 100%; object-fit: cover; display: block; }
    .db-media-video { width: 100%; height: 100%; object-fit: cover; display: block; background: #000; }
    .db-media-del {
        position: absolute; top: 6px; right: 6px;
        width: 22px; height: 22px; border-radius: 50%;
        background: rgba(0,0,0,0.7); color: #fff;
        border: none; cursor: pointer; font-size: 14px;
        display: flex; align-items: center; justify-content: center;
        z-index: 2;
    }
    .db-media-del:hover { background: #dc2626; }
    .db-media-meta {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.6));
        color: #fff; padding: 14px 8px 6px;
        font-size: 10px; font-weight: 500;
        pointer-events: none;
    }
    .db-media-order {
        position: absolute; top: 6px; left: 6px;
        width: 22px; height: 22px; border-radius: 50%;
        background: rgba(0,0,0,0.75); color: #fff;
        font-size: 11px; font-weight: 600;
        display: flex; align-items: center; justify-content: center;
        z-index: 2;
    }
    .db-media-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .db-media-grid .db-media-slot { aspect-ratio: 1/1; }

    .db-media-reel { aspect-ratio: 9/16; max-width: 180px; }

    .db-media-progress {
        position: absolute; inset: 0;
        background: rgba(255,255,255,0.9);
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; color: var(--db-text-2); font-weight: 500;
    }

</style>
@endsection

@section('content')
<div class="db-wrap">

    <div class="db-head">
        <div>
            <div class="db-title">Shporta Ditore</div>
            <div class="db-meta" id="dbCurrentDate">—</div>
        </div>
        <div>
            <button class="db-pano-btn" id="dbBtnPano" disabled>📦 Panorama</button>
            <button class="db-btn db-btn-primary" id="dbBtnNewPost" disabled>+ Post i ri</button>
        </div>
    </div>

    <!-- Panorama sidebar — orientim per produktet e dites -->
    <div class="db-pano" id="dbPano">
        <div class="db-pano-head">
            <div style="flex:1; min-width:0;">
                <div class="db-pano-head-title" id="dbPanoTitle">Panorama</div>
                <div class="db-pano-head-sub" id="dbPanoSub">—</div>
            </div>
            <button class="db-pano-close" id="dbPanoClose" aria-label="Mbyll">×</button>
        </div>
        <div class="db-pano-body" id="dbPanoBody">
            <div class="db-pano-empty">Po ngarkohet…</div>
        </div>
    </div>

    <!-- New/Edit Post modal -->
    <div class="db-modal-backdrop" id="dbModal">
        <div class="db-modal">
            <div class="db-modal-head">
                <div class="db-modal-title" id="dbModalTitle">Post i ri</div>
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
            <div class="db-coll-picker">
                <button class="db-coll-trigger" id="dbCollTrigger" type="button">
                    <span class="db-coll-trigger-name" id="dbCollName">Duke ngarkuar…</span>
                    <span class="db-coll-trigger-caret">▾</span>
                </button>
                <div class="db-coll-menu" id="dbCollMenu" role="menu"></div>
            </div>
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
        collections: [],
        week: null,
        days: [],
        selectedDate: null,
        selectedPostId: null,
        kanban: null,
        availableProducts: [],
        // Create-post modal state (edits happen inline in the panel).
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
        return apiSend('POST', url, body);
    }

    async function apiPutJson(url, body) {
        return apiSend('PUT', url, body);
    }

    async function apiSend(method, url, body) {
        const res = await fetch(url, {
            method,
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

    async function apiDelete(url) {
        const res = await fetch(url, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
        return data;
    }

    async function apiUploadFile(url, file) {
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: fd,
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

    function setUrlWeek(id) {
        const url = new URL(location.href);
        url.searchParams.set('week', String(id));
        history.replaceState(null, '', url.toString());
    }

    async function bootstrap() {
        // Always load the collection list first — it powers the picker.
        try {
            state.collections = await apiGet('/marketing/daily-basket/api/collections');
        } catch (e) {
            showError('S\'u ngarkuan kolekcionet: ' + e.message);
            state.collections = [];
        }

        renderCollectionMenu();

        // Decide which collection to open: URL query wins, else current, else first.
        const urlId = getWeekIdFromUrl();
        const current = state.collections.find(c => c.is_current);
        const target =
            (urlId && state.collections.find(c => c.id === urlId))
            || current
            || state.collections[0];

        if (target) {
            await openCollection(target.id);
        } else {
            document.getElementById('dbCollName').textContent = 'Asnjë kolekcion në dispozicion';
            document.getElementById('dbBtnNewPost').disabled = true;
        }
    }

    async function openCollection(weekId) {
        setUrlWeek(weekId);

        try {
            const data = await apiGet('/marketing/daily-basket/api/collections/' + encodeURIComponent(weekId));
            state.week = data.collection;
            state.days = data.days;
            renderCollectionHeader();
            renderDays();

            const today = new Date().toISOString().slice(0, 10);
            const todayInRange = state.days.find(d => d.date === today);
            const targetDay = todayInRange ? today : state.days[0]?.date;
            if (targetDay) selectDay(targetDay);
        } catch (e) {
            showError('Ngarkimi dështoi: ' + e.message);
        }
    }

    function renderCollectionHeader() {
        const c = state.week;
        document.getElementById('dbCollName').textContent = c.name;
        // Keep the menu's "active" highlight in sync with the loaded collection.
        renderCollectionMenu();

        const prog = document.getElementById('dbCollProg');
        const total = state.days.reduce((s, d) => s + (d.posts_total || 0), 0);
        const done = state.days.reduce((s, d) => s + (d.posts_published || 0), 0);
        prog.textContent = '';
        const strong = document.createElement('strong');
        strong.textContent = done;
        prog.appendChild(strong);
        prog.append(' / ' + total + ' posts publikuar · ' + (c.week_start || '') + ' → ' + (c.week_end || ''));
    }

    function renderCollectionMenu() {
        const menu = document.getElementById('dbCollMenu');
        menu.textContent = '';

        if (state.collections.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'db-coll-menu-empty';
            empty.textContent = 'Asnjë kolekcion — krijo një te Merch Calendar';
            menu.appendChild(empty);
            return;
        }

        // Group by bucket (current / upcoming / past) — backend already sorted them.
        const groups = { current: [], upcoming: [], past: [] };
        const today = new Date().toISOString().slice(0, 10);
        state.collections.forEach(c => {
            if (c.is_current) groups.current.push(c);
            else if ((c.week_start || '') > today) groups.upcoming.push(c);
            else groups.past.push(c);
        });

        const sections = [
            { key: 'current',  label: 'Aktiv tani' },
            { key: 'upcoming', label: 'Të ardhshme' },
            { key: 'past',     label: 'Të kaluara' },
        ];

        sections.forEach(sec => {
            if (groups[sec.key].length === 0) return;

            const title = document.createElement('div');
            title.className = 'db-coll-menu-section';
            title.textContent = sec.label;
            menu.appendChild(title);

            groups[sec.key].forEach(c => menu.appendChild(buildCollectionMenuItem(c, sec.key)));
        });
    }

    function buildCollectionMenuItem(c, bucket) {
        const item = document.createElement('div');
        item.className = 'db-coll-menu-item';
        if (bucket === 'current') item.classList.add('is-current');
        if (bucket === 'upcoming') item.classList.add('is-upcoming');
        if (state.week && state.week.id === c.id) item.classList.add('active');
        item.setAttribute('role', 'menuitem');

        const dot = document.createElement('div');
        dot.className = 'db-coll-menu-item-dot';
        item.appendChild(dot);

        const body = document.createElement('div');
        body.className = 'db-coll-menu-item-body';

        const name = document.createElement('div');
        name.className = 'db-coll-menu-item-name';
        name.textContent = c.name;
        body.appendChild(name);

        const sub = document.createElement('div');
        sub.className = 'db-coll-menu-item-sub';
        const bits = [];
        if (c.week_start && c.week_end) bits.push(c.week_start + ' → ' + c.week_end);
        if (c.item_groups_count) bits.push(c.item_groups_count + ' produkte');
        sub.textContent = bits.join(' · ');
        body.appendChild(sub);

        item.appendChild(body);

        if (bucket !== 'past') {
            const badge = document.createElement('div');
            badge.className = 'db-coll-menu-item-badge';
            badge.textContent = bucket === 'current' ? 'Sot' : 'Vjen';
            item.appendChild(badge);
        }

        item.addEventListener('click', () => {
            closeCollectionMenu();
            openCollection(num(c.id));
        });

        return item;
    }

    function toggleCollectionMenu() {
        document.getElementById('dbCollMenu').classList.toggle('open');
    }
    function closeCollectionMenu() {
        document.getElementById('dbCollMenu').classList.remove('open');
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

            // Enable "+ Post i ri" + Panorama now that we have a basket
            document.getElementById('dbBtnNewPost').disabled = false;
            document.getElementById('dbBtnPano').disabled = false;
            renderPanorama();
        } catch (e) {
            showError('Ngarkimi i ditës dështoi: ' + e.message);
        }
    }

    // ── Panorama: orientim per produktet e dites ──────────────
    const panoState = { filter: 'all' };

    function openPanorama() {
        if (!state.kanban) return;
        document.getElementById('dbPano').classList.add('open');
        renderPanorama();
    }
    function closePanorama() {
        document.getElementById('dbPano').classList.remove('open');
    }

    function renderPanorama() {
        if (!document.getElementById('dbPano').classList.contains('open')) return;

        const dt = state.selectedDate ? new Date(state.selectedDate) : null;
        const collName = state.week?.name || '—';
        document.getElementById('dbPanoTitle').textContent = dt
            ? DAY_NAMES[dt.getDay()] + ' · ' + String(dt.getDate()).padStart(2, '0') + ' ' + MONTH_NAMES[dt.getMonth()] + ' ' + dt.getFullYear()
            : 'Panorama';
        document.getElementById('dbPanoSub').textContent = collName +
            (state.week ? ' · ' + state.week.week_start + ' → ' + state.week.week_end : '');

        const all = state.availableProducts || [];
        const noAssignmentsAtAll = all.length > 0 && all.every(p => !(p.assigned_dates && p.assigned_dates.length));

        let products;
        let isFallback = false;
        if (noAssignmentsAtAll) {
            products = all;
            isFallback = true;
        } else {
            products = all.filter(p => (p.assigned_dates || []).some(a => a.date === state.selectedDate));
        }

        // Compute per-product flags for the selected day.
        const postProductIds = new Map();
        if (state.kanban) {
            state.kanban.columns.forEach(col => {
                col.posts.forEach(post => {
                    (post.products || []).forEach(p => {
                        postProductIds.set(p.item_group_id, (postProductIds.get(p.item_group_id) || 0) + 1);
                    });
                });
            });
        }
        products = products.map(p => {
            const todayAssignment = (p.assigned_dates || []).find(a => a.date === state.selectedDate);
            return Object.assign({}, p, {
                _is_remarketing: todayAssignment ? !todayAssignment.is_primary : false,
                _post_count: postProductIds.get(p.id) || 0,
            });
        });

        const filtered = panoState.filter === 'all'
            ? products
            : products.filter(p => (p.classification || 'plotesues') === panoState.filter);

        const body = document.getElementById('dbPanoBody');
        body.textContent = '';

        body.appendChild(buildPanoStrip());
        body.appendChild(buildPanoStats(filtered));
        body.appendChild(buildPanoFilters(products));

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'db-pano-empty';
            const heading = document.createElement('strong');
            heading.textContent = 'Asnje produkt per kete dite';
            empty.appendChild(heading);
            empty.appendChild(document.createTextNode('Hap nje dite tjeter ose krijo poste me cdo produkt te kolekcionit (modali i postit nuk kufizohet).'));
            body.appendChild(empty);
        } else {
            filtered.forEach(p => body.appendChild(buildPanoCard(p)));
        }

        if (isFallback) {
            const hint = document.createElement('div');
            hint.className = 'db-pano-fallback-hint';
            hint.textContent = 'Caktimi behet ne DIS · Po shfaqim te gjithe kolekcionin (fallback per kolekcionet pa caktime).';
            body.appendChild(hint);
        }
    }

    function buildPanoStrip() {
        const wrap = document.createElement('div');
        wrap.className = 'db-pano-strip';
        (state.days || []).forEach(d => {
            const dt = new Date(d.date);
            const cell = document.createElement('div');
            cell.className = 'db-pano-day' + (d.date === state.selectedDate ? ' active' : '');
            const lbl = document.createElement('div');
            lbl.textContent = DAY_NAMES[dt.getDay()].toUpperCase();
            const num = document.createElement('div');
            num.className = 'num';
            num.textContent = String(dt.getDate()).padStart(2, '0');
            const cnt = document.createElement('div');
            cnt.className = 'cnt';
            cnt.textContent = (d.posts_total || 0) + ' p';
            cell.append(lbl, num, cnt);
            cell.addEventListener('click', () => selectDay(d.date));
            wrap.appendChild(cell);
        });
        return wrap;
    }

    function buildPanoStats(products) {
        const wrap = document.createElement('div');
        wrap.className = 'db-pano-stats';
        const totalStock = products.reduce((s, p) => s + (Number(p.total_stock) || 0), 0);
        const totalValue = products.reduce((s, p) => s + (Number(p.total_stock) || 0) * (Number(p.avg_price) || 0), 0);
        const usedCount = products.filter(p => p._post_count > 0).length;

        const stats = [
            { val: products.length, lbl: 'Caktuar' },
            { val: totalStock.toLocaleString('sq-AL'), lbl: 'Stok' },
            { val: Math.round(totalValue).toLocaleString('sq-AL') + ' L', lbl: 'Vlere' },
            { val: usedCount + '/' + products.length, lbl: 'Posts' },
        ];
        stats.forEach(s => {
            const cell = document.createElement('div');
            cell.className = 'db-pano-stat';
            const v = document.createElement('div');
            v.className = 'db-pano-stat-val';
            v.textContent = String(s.val);
            const l = document.createElement('div');
            l.className = 'db-pano-stat-lbl';
            l.textContent = s.lbl;
            cell.append(v, l);
            wrap.appendChild(cell);
        });
        return wrap;
    }

    function buildPanoFilters(products) {
        const wrap = document.createElement('div');
        wrap.className = 'db-pano-filters';
        const title = document.createElement('span');
        title.className = 'db-pano-filters-title';
        title.textContent = 'Produkte (' + products.length + ')';
        wrap.appendChild(title);

        const counts = {};
        products.forEach(p => {
            const c = p.classification || 'plotesues';
            counts[c] = (counts[c] || 0) + 1;
        });

        const btns = document.createElement('div');
        btns.className = 'db-pano-filters-btns';
        const tabs = [['all', 'Te gjitha'], ['best_seller', 'Best'], ['karrem', 'Karrem'], ['fashion', 'Fashion'], ['plotesues', 'Plotes']];
        tabs.forEach(([key, label]) => {
            if (key !== 'all' && !counts[key]) return;
            const b = document.createElement('button');
            b.className = 'db-pano-filter-btn' + (panoState.filter === key ? ' active' : '');
            b.textContent = key === 'all' ? label : `${label} (${counts[key]})`;
            b.addEventListener('click', () => { panoState.filter = key; renderPanorama(); });
            btns.appendChild(b);
        });
        wrap.appendChild(btns);
        return wrap;
    }

    function buildPanoCard(p) {
        const card = document.createElement('div');
        card.className = 'db-pano-card';

        // Quick-post button (visible on hover/touch) — ngec kartes context per
        // te krijuar nje post me kete produkt pre-selected ne modal.
        if (state.kanban) {
            const quick = document.createElement('button');
            quick.type = 'button';
            quick.className = 'db-pano-quick-post';
            quick.textContent = '+ Post';
            quick.title = 'Krijo post me kete produkt';
            quick.addEventListener('click', (e) => {
                e.stopPropagation();
                closePanorama();
                openNewPostModal(num(p.id));
            });
            card.appendChild(quick);
        }

        if (p.image_url) {
            const img = document.createElement('img');
            img.className = 'db-pano-img';
            img.src = p.image_url;
            img.alt = '';
            img.onerror = () => { img.replaceWith(makePanoPlaceholder()); };
            card.appendChild(img);
        } else {
            card.appendChild(makePanoPlaceholder());
        }

        const info = document.createElement('div');
        info.className = 'db-pano-card-info';
        const name = document.createElement('div');
        name.className = 'db-pano-card-name';
        name.textContent = p.name || '—';
        const meta = document.createElement('div');
        meta.className = 'db-pano-card-meta';
        meta.textContent = p.code || '';
        info.append(name, meta);

        const badges = document.createElement('div');
        badges.className = 'db-pano-card-badges';
        const cls = p.classification || 'plotesues';
        const clsBadge = document.createElement('span');
        clsBadge.className = 'db-pano-badge db-pano-b-' + cls;
        clsBadge.textContent = cls.replace('_', ' ');
        badges.appendChild(clsBadge);

        if (p._is_remarketing) {
            const b = document.createElement('span');
            b.className = 'db-pano-badge db-pano-b-rem';
            b.textContent = '🔁 Ri-marketim';
            badges.appendChild(b);
        }
        if (p._post_count > 0) {
            const b = document.createElement('span');
            b.className = 'db-pano-badge db-pano-b-used';
            b.textContent = '✓ Ne ' + p._post_count + ' post' + (p._post_count > 1 ? 'e' : '');
            badges.appendChild(b);
        } else {
            const b = document.createElement('span');
            b.className = 'db-pano-badge db-pano-b-unused';
            b.textContent = 'Pa post ende';
            badges.appendChild(b);
        }
        info.appendChild(badges);
        card.appendChild(info);

        const right = document.createElement('div');
        right.className = 'db-pano-card-right';
        const price = document.createElement('div');
        price.className = 'db-pano-card-price';
        price.textContent = p.avg_price != null ? Math.round(+p.avg_price).toLocaleString('sq-AL') + ' L' : '—';
        const stock = document.createElement('div');
        stock.className = 'db-pano-card-stock';
        stock.textContent = (p.total_stock || 0) + ' pcs';
        right.append(price, stock);

        // Stock value (price × stock) — e njejta metrike si "Vlere stoku"
        // per te gjithe diten, por per cdo produkt ne karte.
        if (p.avg_price != null && (p.total_stock || 0) > 0) {
            const value = document.createElement('div');
            value.className = 'db-pano-card-value';
            const stockValue = Math.round((+p.avg_price) * p.total_stock);
            value.textContent = stockValue.toLocaleString('sq-AL') + ' L';
            right.appendChild(value);
        }

        card.appendChild(right);
        return card;
    }

    function makePanoPlaceholder() {
        const ph = document.createElement('div');
        ph.className = 'db-pano-img-ph';
        ph.textContent = '📦';
        return ph;
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

        // Head — just title; Edit button removed (everything editable inline).
        const head = document.createElement('div');
        head.className = 'db-sheet-head';

        const headText = document.createElement('div');
        headText.className = 'db-sheet-head-text';
        const crumb = document.createElement('div');
        crumb.className = 'db-sheet-crumb';
        crumb.textContent = (state.selectedDate || '') + ' · ' + post.post_type_label;
        const title = document.createElement('div');
        title.className = 'db-sheet-title';
        title.textContent = post.title;
        headText.append(crumb, title);
        head.appendChild(headText);

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

        // Body — 3 columns (Burimi / Publikimi / Kontenti), every field
        // edits inline with auto-save on blur/Enter. No more edit modal.
        const body = document.createElement('div');
        body.className = 'db-sheet-body-3';

        // ── Col 1: Burimi (products + reference) ──
        const col1 = colGroup('📦', 'Burimi');

        col1.appendChild(labeledField('Produktet', renderProductsBlock(post)));

        col1.appendChild(buildReferenceUrlBlock(post));

        col1.appendChild(labeledField('Reference notes', inlineTextarea({
            value: post.reference_notes,
            rows: 3,
            placeholder: 'Mood, location, model…',
            save: (v) => savePostField(post, { reference_notes: v }),
        })));

        body.appendChild(col1);

        // ── Col 2: Publikimi (platforms + schedule + priority) ──
        const col2 = colGroup('📅', 'Publikimi');

        col2.appendChild(labeledField('Platformat', inlineSegmented({
            options: PLATFORM_OPTIONS,
            value: post.target_platforms,
            multi: true,
            save: (arr) => savePostField(post, { target_platforms: arr }),
        })));

        col2.appendChild(labeledField('Data/Ora (skedulim)', inlineDateTime({
            value: post.scheduled_for,
            save: (v) => savePostField(post, { scheduled_for: v }),
        })));

        col2.appendChild(labeledField('Prioriteti', inlineSegmented({
            options: PRIORITY_OPTIONS,
            value: post.priority,
            multi: false,
            save: (v) => savePostField(post, { priority: v }),
        })));

        body.appendChild(col2);

        // ── Col 3: Kontenti (media + caption + hashtags) ──
        const col3 = colGroup('✍️', 'Kontenti');

        col3.appendChild(labeledField(mediaLabelFor(post.post_type), buildMediaWidget(post)));

        col3.appendChild(labeledField('Caption', inlineTextarea({
            value: post.caption,
            rows: 4,
            placeholder: 'Shkruaj tekstin e postit…',
            save: (v) => savePostField(post, { caption: v }),
        })));

        col3.appendChild(labeledField('Hashtags', inlineInput({
            value: post.hashtags,
            placeholder: '#zeroabsolute #drop #sale',
            save: (v) => savePostField(post, { hashtags: v }),
        })));

        body.appendChild(col3);

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

        // Every field is edited inline above; footer only drives stage moves.
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

    // ── Inline editor helpers ──────────────────────────────────
    //
    // Every editable field follows the same pattern:
    //   blur/Enter → call `save(newValue)` which wraps apiPutJson();
    //   Escape → revert to previous value, blur;
    //   on success → flash green briefly;
    //   on error → toast + revert DOM to the last known good value.

    function colGroup(icon, title) {
        const wrap = document.createElement('div');
        wrap.className = 'db-col-group';
        const h = document.createElement('div');
        h.className = 'db-col-group-title';
        const ic = document.createElement('span');
        ic.className = 'db-col-group-icon';
        ic.textContent = icon;
        const tx = document.createElement('span');
        tx.textContent = title;
        h.append(ic, tx);
        wrap.appendChild(h);
        return wrap;
    }

    function labeledField(label, body, hint) {
        const wrap = document.createElement('div');
        wrap.className = 'db-field-inline';
        const lbl = document.createElement('label');
        lbl.className = 'db-field-lbl';
        lbl.textContent = label;
        wrap.append(lbl, body);
        if (hint) {
            const h = document.createElement('div');
            h.className = 'db-inline-hint';
            h.textContent = hint;
            wrap.appendChild(h);
        }
        return wrap;
    }

    function flashSaved(el) {
        el.classList.add('db-save-flash');
        setTimeout(() => el.classList.remove('db-save-flash'), 1200);
    }

    function makeInlineSaver(el, { initial, save, onError }) {
        let last = initial;
        return async function commit(raw) {
            const next = raw == null ? null : raw;
            if ((next || '') === (last || '')) return;
            try {
                await save(next);
                last = next;
                flashSaved(el);
            } catch (e) {
                showError('Ruajtja deshtoi: ' + e.message);
                if (onError) onError(last);
            }
        };
    }

    function inlineInput({ value, placeholder, type, save, onSaved }) {
        const input = document.createElement('input');
        input.type = type || 'text';
        input.className = 'db-inline-input';
        if (placeholder) input.placeholder = placeholder;
        input.value = value == null ? '' : value;

        const commit = makeInlineSaver(input, {
            initial: input.value,
            save: async (next) => {
                await save(next ? next.trim() : null);
                if (onSaved) onSaved(next ? next.trim() : null);
            },
            onError: (prev) => { input.value = prev == null ? '' : prev; },
        });

        input.addEventListener('blur', () => commit(input.value));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { input.value = value == null ? '' : value; input.blur(); }
        });
        return input;
    }

    function inlineTextarea({ value, placeholder, rows, save, onSaved }) {
        const ta = document.createElement('textarea');
        ta.className = 'db-inline-textarea';
        ta.rows = rows || 3;
        if (placeholder) ta.placeholder = placeholder;
        ta.value = value == null ? '' : value;

        const commit = makeInlineSaver(ta, {
            initial: ta.value,
            save: async (next) => {
                await save(next ? next.trim() : null);
                if (onSaved) onSaved(next ? next.trim() : null);
            },
            onError: (prev) => { ta.value = prev == null ? '' : prev; },
        });

        ta.addEventListener('blur', () => commit(ta.value));
        ta.addEventListener('keydown', (e) => {
            // Enter-without-modifier in a textarea should keep inserting newline
            // (that's the natural thing for captions/hashtags). Escape reverts.
            if (e.key === 'Escape') { ta.value = value == null ? '' : value; ta.blur(); }
        });
        return ta;
    }

    function inlineDateTime({ value, save, onSaved }) {
        const input = document.createElement('input');
        input.type = 'datetime-local';
        input.className = 'db-inline-input';
        // datetime-local wants 'YYYY-MM-DDTHH:MM' in local time; reuse the same
        // formatting the edit modal used so the displayed value matches what
        // the server stores.
        if (value) {
            const d = new Date(value);
            const pad = (n) => String(n).padStart(2, '0');
            input.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
                'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        const initial = input.value;
        const commit = makeInlineSaver(input, {
            initial,
            save: async (next) => {
                await save(next || null);
                if (onSaved) onSaved(next || null);
            },
            onError: (prev) => { input.value = prev || ''; },
        });

        input.addEventListener('blur', () => commit(input.value));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { input.value = initial; input.blur(); }
        });
        return input;
    }

    function inlineSegmented({ options, value, multi, save }) {
        const wrap = document.createElement('div');
        wrap.className = 'db-inline-seg db-seg';

        // Normalize state: multi holds a Set of strings; single holds string|null.
        const state = multi
            ? new Set(Array.isArray(value) ? value : [])
            : { v: value || null };

        options.forEach(opt => {
            const el = document.createElement('div');
            el.className = 'db-seg-opt';
            el.dataset.value = opt.value;
            el.textContent = opt.label;
            const isActive = multi ? state.has(opt.value) : state.v === opt.value;
            el.classList.toggle('active', isActive);
            el.addEventListener('click', async () => {
                if (multi) {
                    if (state.has(opt.value)) state.delete(opt.value);
                    else state.add(opt.value);
                    el.classList.toggle('active');
                    try {
                        await save(Array.from(state));
                        flashSaved(el);
                    } catch (e) {
                        showError('Ruajtja deshtoi: ' + e.message);
                        // Revert local state + DOM
                        if (state.has(opt.value)) state.delete(opt.value);
                        else state.add(opt.value);
                        el.classList.toggle('active');
                    }
                } else {
                    const prev = state.v;
                    if (prev === opt.value) return;
                    state.v = opt.value;
                    wrap.querySelectorAll('.db-seg-opt').forEach(o => {
                        o.classList.toggle('active', o.dataset.value === opt.value);
                    });
                    try {
                        await save(opt.value);
                        flashSaved(el);
                    } catch (e) {
                        showError('Ruajtja deshtoi: ' + e.message);
                        state.v = prev;
                        wrap.querySelectorAll('.db-seg-opt').forEach(o => {
                            o.classList.toggle('active', o.dataset.value === prev);
                        });
                    }
                }
            });
            wrap.appendChild(el);
        });
        return wrap;
    }

    const PLATFORM_OPTIONS = [
        { value: 'instagram', label: 'Instagram' },
        { value: 'facebook',  label: 'Facebook' },
        { value: 'tiktok',    label: 'TikTok' },
        { value: 'web',       label: 'Web' },
    ];
    const PRIORITY_OPTIONS = [
        { value: 'low',    label: 'Low' },
        { value: 'normal', label: 'Normal' },
        { value: 'high',   label: 'High' },
        { value: 'urgent', label: 'Urgent' },
    ];

    function savePostField(post, payload) {
        return apiPutJson('/marketing/daily-basket/api/posts/' + num(post.id), payload)
            .then(() => {
                // Update the local copy so subsequent renders see the new value.
                Object.assign(post, payload);
            });
    }

    // ── Reference URL field (smart favicon + short domain preview) ──
    //
    // The preview is compact (pill with favicon + hostname + ↗) so the first
    // column stays narrow. Clicking opens the link in a new tab. Updates
    // reactively after save via onSaved.
    function buildReferenceUrlBlock(post) {
        const wrap = document.createElement('div');
        wrap.className = 'db-field-inline';

        const lbl = document.createElement('label');
        lbl.className = 'db-field-lbl';
        lbl.textContent = 'Reference URL';
        wrap.appendChild(lbl);

        const preview = document.createElement('div');
        preview.className = 'db-url-preview';

        const renderPreview = (url) => {
            preview.textContent = '';
            preview.classList.toggle('empty', !url);
            if (!url) {
                preview.textContent = 'Guard-i i Fazës 1 kërkon një reference para prodhimit';
                return;
            }
            let host;
            try { host = new URL(url).hostname.replace(/^www\./, ''); }
            catch (_) { host = url; }

            const a = document.createElement('a');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.className = 'db-url-link';
            a.title = url;

            const fav = document.createElement('img');
            fav.className = 'db-url-favicon';
            // Google's favicon service works for any domain and falls back
            // automatically; onerror hides the img so the layout doesn't jump.
            fav.src = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(host) + '&sz=32';
            fav.alt = '';
            fav.onerror = () => { fav.style.display = 'none'; };

            const name = document.createElement('span');
            name.className = 'db-url-host';
            name.textContent = host;

            const ext = document.createElement('span');
            ext.className = 'db-url-ext';
            ext.textContent = '↗';

            a.append(fav, name, ext);
            preview.appendChild(a);
        };

        const input = inlineInput({
            value: post.reference_url,
            type: 'url',
            placeholder: 'https://pinterest.com/pin/… (Enter per ruajtje)',
            save: (v) => savePostField(post, { reference_url: v }),
            onSaved: (v) => renderPreview(v),
        });

        wrap.appendChild(input);
        wrap.appendChild(preview);
        renderPreview(post.reference_url);
        return wrap;
    }

    // ── Inline media uploader (adapts per post_type) ────────────
    //
    // photo/video/reel/story → a single slot. Uploading replaces the current
    // asset. Carousel → a grid of slots with a trailing "+ Shto" tile.

    function mediaLabelFor(postType) {
        switch (postType) {
            case 'photo':    return '📸 Foto e postimit';
            case 'video':    return '🎥 Video e postimit';
            case 'reel':     return '🎬 Video (Reel)';
            case 'story':    return '✨ Story (foto ose video)';
            case 'carousel': return '🖼️ Carousel (shume foto)';
            default:         return 'Media';
        }
    }

    function buildMediaWidget(post) {
        const isCarousel = post.post_type === 'carousel';
        const isVertical = post.post_type === 'reel' || post.post_type === 'story';

        const wrap = document.createElement('div');
        const media = Array.isArray(post.media) ? post.media.slice() : [];

        if (isCarousel) {
            const grid = document.createElement('div');
            grid.className = 'db-media-grid';
            media.forEach((m, i) => grid.appendChild(buildMediaTile(post, m, i + 1)));
            grid.appendChild(buildUploadTile(post, { isCarousel: true }));
            wrap.appendChild(grid);
            return wrap;
        }

        // Single-asset modes (photo/video/reel/story)
        if (media.length > 0) {
            wrap.appendChild(buildMediaTile(post, media[0], null, { isVertical }));
        } else {
            wrap.appendChild(buildUploadTile(post, { isVertical }));
        }
        return wrap;
    }

    function buildMediaTile(post, media, orderNum, opts = {}) {
        const tile = document.createElement('div');
        tile.className = 'db-media-slot has-media';
        if (opts.isVertical) tile.classList.add('db-media-reel');

        if (orderNum != null) {
            const order = document.createElement('div');
            order.className = 'db-media-order';
            order.textContent = String(orderNum);
            tile.appendChild(order);
        }

        if (media.is_video) {
            const video = document.createElement('video');
            video.className = 'db-media-video';
            video.src = media.url;
            video.muted = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.addEventListener('click', () => {
                if (video.paused) video.play(); else video.pause();
            });
            tile.appendChild(video);
        } else {
            const img = document.createElement('img');
            img.className = 'db-media-preview';
            img.src = media.thumbnail_url || media.url;
            img.alt = media.original_filename || '';
            tile.appendChild(img);
        }

        const meta = document.createElement('div');
        meta.className = 'db-media-meta';
        const parts = [];
        if (media.original_filename) parts.push(media.original_filename);
        if (media.size_bytes) parts.push(humanSize(media.size_bytes));
        meta.textContent = parts.join(' · ');
        tile.appendChild(meta);

        const del = document.createElement('button');
        del.className = 'db-media-del';
        del.type = 'button';
        del.title = 'Hiq';
        del.textContent = '×';
        del.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!confirm('Te hiqet kjo media?')) return;
            try {
                await apiDelete('/marketing/daily-basket/api/posts/' + num(post.id) + '/media/' + num(media.id));
                post.media = (post.media || []).filter(m => m.id !== media.id);
                renderSheet(post);
            } catch (err) {
                showError('Fshirja deshtoi: ' + err.message);
            }
        });
        tile.appendChild(del);
        return tile;
    }

    function buildUploadTile(post, opts = {}) {
        const tile = document.createElement('div');
        tile.className = 'db-media-slot';
        if (opts.isVertical) tile.classList.add('db-media-reel');
        tile.tabIndex = 0;

        const icon = document.createElement('div');
        icon.className = 'db-media-slot-icon';
        icon.textContent = opts.isCarousel ? '+' : '⬆';
        tile.appendChild(icon);

        const txt = document.createElement('div');
        txt.className = 'db-media-slot-txt';
        txt.textContent = opts.isCarousel
            ? 'Shto foto'
            : 'Kliko ose tërhiq skedarin ketu';
        tile.appendChild(txt);

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = acceptFor(post.post_type);
        input.style.display = 'none';
        if (opts.isCarousel) input.multiple = true;
        tile.appendChild(input);

        const trigger = () => input.click();
        tile.addEventListener('click', trigger);
        tile.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); }
        });

        // Drag-drop
        tile.addEventListener('dragover', (e) => {
            e.preventDefault();
            tile.classList.add('is-dragover');
        });
        tile.addEventListener('dragleave', () => tile.classList.remove('is-dragover'));
        tile.addEventListener('drop', (e) => {
            e.preventDefault();
            tile.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                handleUpload(post, tile, Array.from(e.dataTransfer.files));
            }
        });

        input.addEventListener('change', () => {
            if (input.files && input.files.length) {
                handleUpload(post, tile, Array.from(input.files));
            }
        });

        return tile;
    }

    async function handleUpload(post, tile, files) {
        tile.classList.add('is-uploading');
        const progress = document.createElement('div');
        progress.className = 'db-media-progress';
        progress.textContent = 'Po ngarkohet…';
        tile.appendChild(progress);

        try {
            for (const f of files) {
                const uploaded = await apiUploadFile(
                    '/marketing/daily-basket/api/posts/' + num(post.id) + '/media',
                    f,
                );
                if (!Array.isArray(post.media)) post.media = [];
                if (post.post_type !== 'carousel') post.media = [];
                post.media.push(uploaded);
            }
            renderSheet(post);
        } catch (e) {
            showError('Ngarkimi deshtoi: ' + e.message);
            tile.classList.remove('is-uploading');
            if (progress.parentNode) progress.remove();
        }
    }

    function acceptFor(postType) {
        switch (postType) {
            case 'photo':    return 'image/*';
            case 'video':
            case 'reel':     return 'video/*';
            case 'story':    return 'image/*,video/*';
            case 'carousel': return 'image/*';
            default:         return 'image/*,video/*';
        }
    }

    function humanSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024)    return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    // Products display is still read-only inline — edits happen through the
    // existing product picker in the create modal. Here we just list them.
    function renderProductsBlock(post) {
        if (!post.products || post.products.length === 0) {
            const v = document.createElement('div');
            v.className = 'db-sec-val muted';
            v.style.fontSize = '12px';
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

    // ── Create-post modal ─────────────────────────────────
    // Only the "create new post" flow uses the modal. Every field that
    // used to live in the edit modal is edited inline in the panel.
    function resetModalFields() {
        state.modal = {
            title: '',
            post_type: null,
            priority: 'normal',
            selectedProductIds: new Set(),
            heroProductId: null,
        };

        document.getElementById('dbFieldTitle').value = '';

        document.querySelectorAll('#dbFieldType .db-seg-opt').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('#dbFieldPriority .db-seg-opt').forEach(el => {
            el.classList.toggle('active', el.dataset.value === 'normal');
        });
    }

    // Returns Set<int> of item_group_ids assigned for selectedDate, or empty set if none.
    function getAssignedIdsForSelectedDay() {
        const ids = new Set();
        if (!state.selectedDate) return ids;
        (state.availableProducts || []).forEach(p => {
            const matches = (p.assigned_dates || []).some(a => a.date === state.selectedDate);
            if (matches) ids.add(num(p.id));
        });
        return ids;
    }

    // preselectedProductId: when called from the "+ Post" button on a
    // panorama card, we start with just that one product checked (and skip the
    // bulk pre-select). When called from the header "+ Post i ri", we pre-select
    // everything that's assigned to the selected day so the common case is 1-click.
    function openNewPostModal(preselectedProductId = null) {
        resetModalFields();

        const titleEl = document.getElementById('dbModalTitle');
        let title = 'Post i ri';
        if (state.selectedDate) {
            const dt = new Date(state.selectedDate);
            title += ' · ' + DAY_NAMES[dt.getDay()] + ' ' +
                String(dt.getDate()).padStart(2, '0') + ' ' + MONTH_NAMES[dt.getMonth()];
        }
        titleEl.textContent = title;
        document.getElementById('dbModalSubmit').textContent = 'Krijo post';

        if (preselectedProductId != null) {
            state.modal.selectedProductIds.add(num(preselectedProductId));
            state.modal.heroProductId = num(preselectedProductId);
        } else {
            // Pre-select products assigned to the selected day (orientim nga paneli).
            const assigned = getAssignedIdsForSelectedDay();
            assigned.forEach(id => state.modal.selectedProductIds.add(id));
            if (assigned.size > 0) {
                state.modal.heroProductId = assigned.values().next().value;
            }
        }

        renderProductPicker();
        document.getElementById('dbModal').classList.add('open');
        setTimeout(() => document.getElementById('dbFieldTitle').focus(), 50);
    }

    // NOTE: openEditPostModal has been removed — every post field is now
    // editable inline inside the selected-post panel. The modal is reserved
    // for creation only (title + type + priority + product picker).

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

        // Partition: assigned-for-today first, everything else after. Empty
        // assigned set falls through to a single flat list (old behavior).
        const assignedIds = getAssignedIdsForSelectedDay();
        const assigned = [];
        const others = [];
        state.availableProducts.forEach(p => {
            (assignedIds.has(num(p.id)) ? assigned : others).push(p);
        });

        const renderItem = (p, isAssigned) => {
            const item = document.createElement('div');
            item.className = 'db-picker-item';
            if (isAssigned) item.classList.add('is-assigned');
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
            if (p.avg_price != null) bits.push(Math.round(+p.avg_price).toLocaleString('sq-AL') + ' L');
            sub.textContent = bits.join(' · ');
            info.append(name, sub);

            const check = document.createElement('div');
            check.className = 'db-picker-check';
            check.textContent = state.modal.selectedProductIds.has(p.id) ? '✓' : '';

            item.append(info, check);
            item.addEventListener('click', () => toggleProduct(num(p.id)));
            return item;
        };

        const makeSectionHeader = (text, count, hint, secondary = false) => {
            const h = document.createElement('div');
            h.className = 'db-picker-section' + (secondary ? ' secondary' : '');
            const label = document.createElement('span');
            label.textContent = text;
            h.appendChild(label);
            const badge = document.createElement('span');
            badge.className = 'db-picker-section-count';
            badge.textContent = String(count);
            h.appendChild(badge);
            if (hint) {
                const hintEl = document.createElement('span');
                hintEl.className = 'db-picker-section-hint';
                hintEl.textContent = hint;
                h.appendChild(hintEl);
            }
            return h;
        };

        // Section 1: day-assigned products (only if we actually have some)
        if (assigned.length > 0) {
            let title = 'Produkte te caktuara per kete dite';
            if (state.selectedDate) {
                const dt = new Date(state.selectedDate);
                title = 'Produkte per ' + DAY_NAMES[dt.getDay()] + ' ' +
                    String(dt.getDate()).padStart(2, '0') + ' ' + MONTH_NAMES[dt.getMonth()];
            }
            host.appendChild(makeSectionHeader(title, assigned.length, null, false));
            assigned.forEach(p => host.appendChild(renderItem(p, true)));
        }

        // Section 2: all other collection products
        if (others.length > 0) {
            const label = assigned.length > 0
                ? 'Te tjeret nga kolekcioni'
                : 'Te gjitha produktet e kolekcionit';
            const hint = assigned.length > 0 ? 'pa caktim per kete dite' : null;
            host.appendChild(makeSectionHeader(label, others.length, hint, true));
            others.forEach(p => host.appendChild(renderItem(p, false)));
        }
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

    async function submitModal() {
        // The modal is create-only now; every field after creation is
        // edited inline in the selected-post panel.
        const title = document.getElementById('dbFieldTitle').value.trim();
        const postType = state.modal.post_type;

        if (!title) { showError('Titulli është i detyrueshëm'); return; }
        if (!postType) { showError('Zgjidh një tip posti'); return; }

        const basketId = state.kanban?.basket?.id;
        if (!basketId) { showError('Basket-i nuk është i ngarkuar'); return; }

        const productIds = Array.from(state.modal.selectedProductIds);

        let createdId = null;
        try {
            const resp = await apiPost('/marketing/daily-basket/api/baskets/' + num(basketId) + '/posts', {
                title,
                post_type: postType,
                priority: state.modal.priority,
                product_ids: productIds,
                hero_product_id: state.modal.heroProductId,
            });
            createdId = resp && resp.id ? resp.id : null;
        } catch (e) {
            showError('Krijimi i postit dështoi: ' + e.message);
            return;
        }

        closeNewPostModal();
        await selectDay(state.selectedDate);

        // Auto-open the new post in the panel so the user can fill the
        // remaining inline fields without an extra click.
        if (createdId != null) selectPost(num(createdId));
    }

    function wireModalOnce() {
        document.getElementById('dbBtnNewPost').addEventListener('click', openNewPostModal);
        document.getElementById('dbModalClose').addEventListener('click', closeNewPostModal);
        document.getElementById('dbModalCancel').addEventListener('click', closeNewPostModal);
        document.getElementById('dbModalSubmit').addEventListener('click', submitModal);

        // Panorama
        document.getElementById('dbBtnPano').addEventListener('click', openPanorama);
        document.getElementById('dbPanoClose').addEventListener('click', closePanorama);

        // (The legacy #dbFieldPlatforms wiring lived in the edit modal; that
        // modal is gone — platforms are now edited inline in the panel.)

        document.getElementById('dbModal').addEventListener('click', (e) => {
            if (e.target.id === 'dbModal') closeNewPostModal();
        });

        // Collection picker trigger + outside-click close
        document.getElementById('dbCollTrigger').addEventListener('click', (e) => {
            e.stopPropagation();
            toggleCollectionMenu();
        });
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('dbCollMenu');
            if (menu.classList.contains('open') && !menu.contains(e.target) && e.target.id !== 'dbCollTrigger') {
                closeCollectionMenu();
            }
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

        // Esc closes modal (or panorama if modal not open)
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (document.getElementById('dbModal').classList.contains('open')) {
                closeNewPostModal();
            } else if (document.getElementById('dbPano').classList.contains('open')) {
                closePanorama();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        wireModalOnce();
        bootstrap();
    });
})();
</script>
@endsection
