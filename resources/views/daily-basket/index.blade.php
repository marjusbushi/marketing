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
    @keyframes db-flash-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        50%      { box-shadow: 0 0 0 6px rgba(245,158,11,0.4); }
    }
    .db-card.deeplink-flash,
    .db-post.deeplink-flash { outline: 3px solid #f59e0b; outline-offset: 2px; animation: db-flash-pulse 0.8s ease-in-out 2; }
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
    .db-sheet-close {
        font-size: 11px; color: var(--db-text-2);
        background: var(--db-accent-soft); border: none;
        padding: 5px 11px; border-radius: 5px; cursor: pointer;
        flex-shrink: 0;
    }
    .db-sheet-close:hover { background: var(--db-border); color: var(--db-text); }

    .db-track { display: flex; padding: 16px 24px; background: #fafafa; border-bottom: 1px solid var(--db-border); gap: 4px; }
    .db-track-step {
        flex: 1; padding: 4px 0;
        transition: opacity 0.12s;
    }
    .db-track-step.todo:hover,
    .db-track-step.done:hover { opacity: 0.75; }
    .db-track-step:focus-visible { outline: 2px solid var(--db-text); outline-offset: 2px; border-radius: 3px; }
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

    /* Toast notifications — floating at the top of the viewport so the
       user doesn't need to scroll up to see feedback after clicking a
       stage-transition button inside the detail panel. */
    .db-toast-host {
        position: fixed;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
        max-width: 90vw;
    }
    .db-toast {
        pointer-events: auto;
        padding: 10px 16px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 500;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        animation: dbToastIn 0.2s ease-out;
    }
    .db-toast.err {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .db-toast.ok {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    .db-toast.info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }
    @keyframes dbToastIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    /* Kept for backward compat with any inline usage; shares the toast look. */
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

    /* ─── Shporta v2: Summary strip + Main (canvas + rail) ─────── */
    .db-summary {
        display: flex; align-items: center; gap: 14px;
        padding: 10px 14px; margin-top: 6px;
        background: #fff; border: 1px solid var(--db-border);
        border-radius: 8px;
    }
    .db-summary-title {
        font-size: 10px; font-weight: 700; color: var(--db-text-3);
        text-transform: uppercase; letter-spacing: 0.06em;
        padding-right: 12px; border-right: 1px solid var(--db-border);
    }
    .db-summary-counts, .db-summary-stats { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .db-summary-stats { margin-left: auto; }
    .db-summary-sep { width: 1px; height: 22px; background: var(--db-border); }
    .db-summary-item { display: flex; align-items: baseline; gap: 4px; font-size: 12px; }
    .db-summary-item .num { font-size: 14px; font-weight: 700; color: var(--db-text); }
    .db-summary-item .lbl { font-size: 11px; color: var(--db-text-3); }
    .db-summary-type { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: var(--db-text-2); }
    .db-summary-type .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .db-summary-type[data-type="reel"] .dot     { background: #db2777; }
    .db-summary-type[data-type="photo"] .dot    { background: #2563eb; }
    .db-summary-type[data-type="story"] .dot    { background: #7c3aed; }
    .db-summary-type[data-type="carousel"] .dot { background: #0891b2; }
    .db-summary-type[data-type="video"] .dot    { background: #64748b; }
    .db-summary-cov {
        font-size: 11px; font-weight: 600; color: var(--db-text-2);
        background: var(--db-accent-soft); padding: 4px 8px; border-radius: 4px;
    }
    .db-summary-cov.warn { background: #fef3c7; color: #92400e; }
    .db-summary-cov.ok   { background: #dcfce7; color: #166534; }

    /* Two-column layout: canvas (posts) + persistent product rail */
    .db-main {
        display: grid; grid-template-columns: 1fr 280px; gap: 14px;
        margin-top: 10px;
    }
    .db-canvas { min-width: 0; }
    .db-rail {
        background: #fff; border: 1px solid var(--db-border); border-radius: 8px;
        display: flex; flex-direction: column; max-height: calc(100vh - 220px);
        position: sticky; top: 12px; align-self: flex-start;
    }
    .db-rail-hdr { padding: 12px 14px; border-bottom: 1px solid var(--db-border); flex-shrink: 0; }
    .db-rail-hdr-top {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px;
    }
    .db-rail-hdr-title { font-size: 13px; font-weight: 700; color: var(--db-text); }
    .db-rail-hdr-sub { font-size: 11px; color: var(--db-text-3); margin-top: 3px; }
    .db-rail-filter {
        font-size: 10px; padding: 3px 6px; border-radius: 4px;
        border: 1px solid var(--db-border); background: var(--db-accent-soft);
        color: var(--db-text-2); cursor: pointer; max-width: 120px;
    }
    .db-rail-filter:focus { outline: none; border-color: var(--db-text); }

    /* Rail product card */
    .db-p-card {
        display: flex; align-items: center; gap: 8px;
        padding: 7px 12px; margin: 2px 6px; border-radius: 7px;
        cursor: pointer; position: relative;
        transition: background 0.1s;
    }
    .db-p-card:hover { background: #f5f5f4; }
    .db-p-card.selected { background: rgba(99, 102, 241, 0.10); }
    .db-p-card.selected::before {
        content: ''; position: absolute; left: 3px; top: 6px; bottom: 6px;
        width: 3px; background: #6366f1; border-radius: 2px;
    }
    .db-p-card.dragging { opacity: 0.55; }
    .db-p-thumb {
        width: 40px; height: 40px; border-radius: 6px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 600; font-size: 13px; overflow: hidden;
        background: var(--db-accent-soft);
    }
    .db-p-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .db-p-body { flex: 1; min-width: 0; }
    .db-p-name {
        font-size: 12px; font-weight: 600; color: var(--db-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .db-p-meta {
        font-size: 10px; color: var(--db-text-3);
        display: flex; gap: 6px; margin-top: 1px;
    }
    .db-cov-badge {
        width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
        font-size: 10px; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .db-cov-0 { background: #fee2e2; color: #991b1b; }
    .db-cov-1 { background: #fef3c7; color: #92400e; }
    .db-cov-2 { background: #dcfce7; color: #166534; }

    /* Drop-target state on a post during rail drag */
    .db-post.dragover {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.22);
    }
    .db-rail-cov-bar {
        padding: 8px 14px; font-size: 11px; color: var(--db-text-2);
        background: var(--db-accent-soft); border-bottom: 1px solid var(--db-border);
        flex-shrink: 0;
    }
    .db-rail-list { flex: 1; overflow-y: auto; padding: 6px 0; }
    .db-rail-empty { padding: 24px 16px; text-align: center; font-size: 11px; color: var(--db-text-3); line-height: 1.5; }

    @media (max-width: 1024px) {
        .db-main { grid-template-columns: 1fr; }
        .db-rail { position: static; max-height: 360px; }
    }

    /* ─── Post card v2 (#1323) — flat grid inside .db-canvas ──── */
    .db-grid {
        display: grid;
        /* Columns are capped at 260px so cards don't stretch to 500px on
           wide screens — a 9:16 reel at 500px wide would be >850px tall
           and blow the row alignment apart. 260px keeps reels at ~460px
           tall which is readable without dominating the layout. */
        grid-template-columns: repeat(auto-fill, minmax(220px, 260px));
        gap: 14px;
        padding: 14px;
        background: #fff;
        border: 1px solid var(--db-border);
        border-radius: 8px;
        min-height: 300px;
    }
    .db-grid-empty {
        grid-column: 1 / -1;
        padding: 40px 20px;
        text-align: center;
        color: var(--db-text-3);
        font-size: 12px;
    }

    .db-post {
        background: #fff; border: 1px solid var(--db-border);
        border-radius: 10px; overflow: hidden;
        display: flex; flex-direction: column;
        cursor: pointer;
        transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
        min-width: 0;
    }
    .db-post:hover { border-color: var(--db-border-strong); }
    .db-post.highlighted {
        border-color: #6366f1;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.18);
    }
    .db-post.selected {
        border-color: var(--db-text);
        box-shadow: 0 0 0 2px var(--db-accent-soft);
    }

    .db-post-top {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 10px; border-bottom: 1px solid var(--db-border);
    }
    .db-post-type {
        display: inline-flex; align-items: center;
        padding: 3px 8px; border-radius: 999px;
        font-size: 9px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.05em;
        color: #fff; flex-shrink: 0;
    }
    .db-post-type[data-type="reel"]     { background: #db2777; }
    .db-post-type[data-type="photo"]    { background: #2563eb; }
    .db-post-type[data-type="story"]    { background: #7c3aed; }
    .db-post-type[data-type="carousel"] { background: #0891b2; }
    .db-post-type[data-type="video"]    { background: #64748b; }

    .db-post-num {
        font-size: 10px; color: var(--db-text-3); font-weight: 500;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        min-width: 0; flex: 1;
    }
    .db-post-stage {
        font-size: 10px; color: var(--db-text-3);
        display: inline-flex; align-items: center; gap: 4px;
        flex-shrink: 0;
    }
    .db-stage-dot { width: 7px; height: 7px; border-radius: 50%; background: #cbd5e1; }
    .db-stage-dot[data-stage="planning"]   { background: #9ca3af; }
    .db-stage-dot[data-stage="production"] { background: #f59e0b; }
    .db-stage-dot[data-stage="editing"]    { background: #2563eb; }
    .db-stage-dot[data-stage="scheduling"] { background: #7c3aed; }
    .db-stage-dot[data-stage="published"]  { background: #22c55e; }

    /* Material thumbnail — always 1:1 in the grid (IG profile grid style).
       User tested 9:16 for reels twice and rejected both times: at any
       reasonable column width it towers over 1:1 cards and breaks row
       alignment. The REEL/STORY badge at the top already labels the
       format. Real 9:16 aspect is still honored inside the detail view. */
    .db-mat {
        aspect-ratio: 1 / 1;
        background: var(--db-accent-soft);
        position: relative;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        border-bottom: 1px solid var(--db-border);
    }
    .db-mat-slot { position: absolute; inset: 0; display: block; }
    .db-mat-slot img, .db-mat-slot video,
    .db-mat > img, .db-mat > video {
        width: 100%; height: 100%;
        object-fit: contain; display: block;
    }
    .db-mat-empty {
        display: flex; flex-direction: column; align-items: center; gap: 4px;
        color: var(--db-text-3); font-size: 11px; padding: 16px; text-align: center;
    }
    .db-mat-empty .icon { font-size: 22px; }

    /* Carousel nav overlays */
    .db-mat-dots {
        position: absolute; bottom: 8px; left: 0; right: 0;
        display: flex; gap: 4px; justify-content: center; z-index: 2;
        pointer-events: none;
    }
    .db-mat-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: rgba(255, 255, 255, 0.55);
        box-shadow: 0 0 2px rgba(0, 0, 0, 0.35);
        cursor: pointer; pointer-events: auto;
        transition: background 0.12s, transform 0.12s;
    }
    .db-mat-dot:hover { transform: scale(1.2); }
    .db-mat-dot.active { background: #fff; }
    .db-mat-arrow {
        position: absolute; top: 50%; transform: translateY(-50%);
        width: 26px; height: 26px; border-radius: 50%;
        border: none; background: rgba(0, 0, 0, 0.55); color: #fff;
        font-size: 16px; line-height: 1; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.15s; z-index: 2;
    }
    .db-mat:hover .db-mat-arrow { opacity: 1; }
    .db-mat-arrow:hover { background: rgba(0, 0, 0, 0.75); }
    .db-mat-arrow.prev { left: 6px; }
    .db-mat-arrow.next { right: 6px; }

    .db-post-body {
        padding: 9px 11px;
        display: flex; flex-direction: column; gap: 7px;
    }
    .db-post-chips { display: flex; flex-wrap: wrap; gap: 4px; }
    .db-post-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 7px 2px 2px; border-radius: 10px;
        background: var(--db-accent-soft); font-size: 10px;
        color: var(--db-text-2); font-weight: 500;
        max-width: 100%;
    }
    .db-post-chip-thumb {
        width: 16px; height: 16px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 8px;
        flex-shrink: 0; object-fit: cover;
    }
    .db-post-chip-name {
        max-width: 80px; overflow: hidden; text-overflow: ellipsis;
        white-space: nowrap;
    }

    .db-post-ref-compact {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 7px; background: var(--db-accent-soft);
        border-radius: 5px; font-size: 10px; color: var(--db-text-2);
        text-decoration: none; align-self: flex-start;
        max-width: 100%;
    }
    .db-post-ref-compact .fav {
        width: 12px; height: 12px; border-radius: 2px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 7px; flex-shrink: 0;
    }
    .db-post-ref-compact .host {
        max-width: 130px; overflow: hidden; text-overflow: ellipsis;
        white-space: nowrap;
    }
    .db-post-ref-compact:hover { background: var(--db-border); color: var(--db-text); }

    /* Inline "add" affordances on the card body — keep products + reference
       editable without forcing the user into the detail view. */
    .db-post-chip-add {
        padding: 2px 7px; border-radius: 10px;
        border: 1px dashed var(--db-border-strong);
        font-size: 10px; color: var(--db-text-3);
        background: transparent; cursor: pointer;
    }
    .db-post-chip-add:hover { border-color: var(--db-text); color: var(--db-text); background: var(--db-accent-soft); }

    .db-post-ref-add {
        display: inline-flex; align-items: center;
        padding: 2px 7px; border-radius: 5px;
        border: 1px dashed var(--db-border-strong);
        background: transparent; color: var(--db-text-3);
        font-size: 10px; cursor: pointer;
        align-self: flex-start;
    }
    .db-post-ref-add:hover { border-color: var(--db-text); color: var(--db-text); background: var(--db-accent-soft); }

    /* Delete button on card header — only shows on hover so the header
       stays clean most of the time. */
    .db-post-del {
        width: 18px; height: 18px; border-radius: 50%;
        border: none; background: transparent;
        color: var(--db-text-3); cursor: pointer;
        font-size: 14px; line-height: 1;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; opacity: 0;
        transition: opacity 0.12s, background 0.12s, color 0.12s;
    }
    .db-post:hover .db-post-del { opacity: 1; }
    .db-post-del:hover { background: #fee2e2; color: #dc2626; opacity: 1; }

    /* Empty / "+ Post i ri" card */
    .db-post-empty {
        border-style: dashed; border-color: var(--db-border-strong);
        background: #fafafa;
        min-height: 280px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 10px; cursor: pointer;
        padding: 14px;
    }
    .db-post-empty:hover { border-color: var(--db-text); background: var(--db-accent-soft); }
    .db-post-empty .plus {
        width: 40px; height: 40px; border-radius: 50%;
        background: var(--db-accent-soft); color: var(--db-text-2);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 22px; font-weight: 300;
    }
    .db-post-empty .lbl { font-size: 12px; color: var(--db-text-2); font-weight: 500; }
    .db-post-empty .sub { font-size: 10px; color: var(--db-text-3); }
    .db-post-empty .type-row { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
    .db-post-empty .type-pick {
        padding: 3px 8px; border-radius: 999px;
        font-size: 9px; font-weight: 600; text-transform: uppercase;
        border: 1px solid var(--db-border-strong); background: #fff;
        color: var(--db-text-3); cursor: pointer;
    }
    .db-post-empty .type-pick:hover { background: var(--db-accent-soft); color: var(--db-text-2); border-color: var(--db-text); }

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

    /* Post card v2 (#1323) inherits stage-dot + post-type colours; the
       helper classes below are kept for the inline detail panel reusing
       them via `.db-plan-field*`, `.db-plan-chips`, `.db-plan-media`,
       `.db-plan-pop*`, `.db-plan-ref`. */

    .db-plan-field { display: flex; flex-direction: column; gap: 4px; }
    .db-plan-field-lbl {
        font-size: 9px; font-weight: 600; color: var(--db-text-3);
        text-transform: uppercase; letter-spacing: 0.06em;
    }
    .db-plan-input, .db-plan-textarea {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid var(--db-border);
        border-radius: 5px;
        font-family: inherit; font-size: 11px;
        color: var(--db-text); background: #fff;
    }
    .db-plan-input:focus, .db-plan-textarea:focus { outline: none; border-color: var(--db-text); box-shadow: 0 0 0 3px rgba(24,24,27,0.05); }
    .db-plan-textarea { resize: none; min-height: 44px; }

    /* Product chips */
    .db-plan-chips { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
    .db-plan-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 6px 2px 3px;
        border-radius: 10px; background: var(--db-accent-soft);
        font-size: 10px; color: var(--db-text-2);
        max-width: 100%;
    }
    .db-plan-chip-thumb { width: 14px; height: 14px; border-radius: 3px; object-fit: cover; flex-shrink: 0; background: #f4f4f5; }
    .db-plan-chip-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 90px; }
    .db-plan-chip-del { margin-left: 2px; cursor: pointer; color: var(--db-text-3); font-size: 12px; line-height: 1; }
    .db-plan-chip-del:hover { color: #dc2626; }
    .db-plan-chip-add {
        padding: 2px 7px; border-radius: 10px;
        border: 1px dashed var(--db-border-strong);
        background: transparent; font-size: 10px; color: var(--db-text-3);
        cursor: pointer;
    }
    .db-plan-chip-add:hover { border-color: var(--db-text); color: var(--db-text); }

    /* Mini product picker popover */
    .db-plan-pop {
        position: absolute; z-index: 9900;
        background: var(--db-surface); border: 1px solid var(--db-border);
        border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        padding: 6px; width: 260px; max-height: 340px; overflow-y: auto;
    }
    .db-plan-pop-item {
        display: flex; align-items: center; gap: 8px;
        padding: 6px 8px; border-radius: 5px; cursor: pointer;
    }
    .db-plan-pop-item:hover { background: var(--db-accent-soft); }
    .db-plan-pop-item.selected { background: #eef2ff; }
    .db-plan-pop-thumb { width: 28px; height: 28px; border-radius: 4px; object-fit: cover; background: #f4f4f5; flex-shrink: 0; }
    .db-plan-pop-name { font-size: 11px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-plan-pop-sub { font-size: 9px; color: var(--db-text-3); }
    .db-plan-pop-search {
        width: 100%; padding: 6px 8px; font-size: 11px;
        border: 1px solid var(--db-border); border-radius: 5px;
        margin-bottom: 6px;
    }
    .db-plan-pop-empty { padding: 16px; text-align: center; color: var(--db-text-3); font-size: 11px; }

    /* Inline media thumb — aspekti ndryshon sipas post_type, per t'i
       treguar user-it formatin e materialit qe do postohet:
         photo/video/carousel → 4:5 (IG portrait standard)
         reel/story           → 9:16 (vertikale)
       Sfondi eshte neutral, object-fit: contain qe foto shihet e plote.*/
    .db-plan-media {
        position: relative; width: 100%;
        border: 1.5px dashed var(--db-border-strong);
        border-radius: 6px; background: #f4f4f5;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; overflow: hidden;
    }
    .db-plan-media.aspect-45 { aspect-ratio: 4/5; }
    .db-plan-media.aspect-916 { aspect-ratio: 9/16; margin: 0 auto; max-width: 70%; }
    .db-plan-media.aspect-11 { aspect-ratio: 1/1; }
    .db-plan-media:hover { border-color: var(--db-text); background: var(--db-accent-soft); }
    .db-plan-media.has-media { border-style: solid; cursor: default; background: #f4f4f5; }
    .db-plan-media.is-dragover { border-color: var(--db-text); background: #eef2ff; }
    .db-plan-media img, .db-plan-media video { width: 100%; height: 100%; object-fit: contain; display: block; }

    /* Small hint under the media showing the target aspect */
    .db-plan-media-hint {
        font-size: 9px; color: var(--db-text-3);
        text-align: center; margin-top: 3px;
        text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500;
    }
    .db-plan-media-empty { font-size: 10px; color: var(--db-text-3); text-align: center; padding: 4px 6px; }
    .db-plan-media-empty strong { font-size: 18px; display: block; color: var(--db-text-3); margin-bottom: 2px; }
    .db-plan-media-del {
        position: absolute; top: 4px; right: 4px;
        width: 20px; height: 20px; border-radius: 50%;
        background: rgba(0,0,0,0.7); color: #fff;
        border: none; cursor: pointer; font-size: 12px;
        display: flex; align-items: center; justify-content: center;
    }
    .db-plan-media-count {
        position: absolute; bottom: 4px; right: 4px;
        background: rgba(0,0,0,0.7); color: #fff;
        font-size: 9px; font-weight: 600;
        padding: 2px 6px; border-radius: 8px;
    }

    /* Ref preview inside plan cell */
    .db-plan-ref {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 2px 8px 2px 5px;
        border-radius: 10px; background: var(--db-accent-soft);
        color: var(--db-text-2); font-size: 10px;
        max-width: 100%; text-decoration: none;
    }
    .db-plan-ref:hover { background: var(--db-border-strong); color: var(--db-text); }
    .db-plan-ref img { width: 11px; height: 11px; border-radius: 2px; flex-shrink: 0; }
    .db-plan-ref span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Full-featured editor modal — larger than the create-post modal because
       it hosts the Studio SPA in an iframe. 80vw × 85vh per spec §1247. */
    .db-studio-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9995; display: none; align-items: center; justify-content: center; }
    .db-studio-modal-backdrop.open { display: flex; }
    .db-studio-modal { background: #09090b; border-radius: 10px; width: 80vw; height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 30px 80px rgba(0,0,0,0.5); }
    .db-studio-modal-head { padding: 10px 14px; border-bottom: 1px solid #27272a; display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #18181b; color: #e4e4e7; }
    .db-studio-modal-title { font-size: 13px; font-weight: 600; }
    .db-studio-modal-actions { display: flex; gap: 8px; align-items: center; }
    .db-studio-modal-open {
        font-size: 11px;
        text-decoration: none;
        color: #a78bfa;
        border: 1px solid #6d28d9;
        background: rgba(109, 40, 217, 0.25);
        padding: 4px 10px;
        border-radius: 5px;
    }
    .db-studio-modal-open:hover { background: rgba(109, 40, 217, 0.45); }
    .db-studio-modal-close { background: none; border: none; cursor: pointer; color: #a1a1aa; font-size: 20px; padding: 2px 8px; }
    .db-studio-modal-close:hover { color: #fff; }
    .db-studio-modal-iframe { flex: 1; border: 0; width: 100%; height: 100%; background: #09090b; }

    /* Caption + AI polish styling — lives next to the caption textarea in
       the post detail panel. Single button, single status row. */
    .db-caption-wrap { display: flex; flex-direction: column; gap: 6px; }
    .db-caption-ai-row { display: flex; align-items: center; gap: 10px; }
    .db-caption-ai-btn {
        border: 1px solid #6d28d9;
        background: rgba(109, 40, 217, 0.18);
        color: #6d28d9;
        font-size: 11px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 5px;
        cursor: pointer;
    }
    .db-caption-ai-btn:hover:not(:disabled) { background: rgba(109, 40, 217, 0.3); }
    .db-caption-ai-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .db-caption-ai-status { font-size: 11px; color: var(--db-text-2); }
    .db-caption-ai-status.err { color: #dc2626; }

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
    .db-media-preview { width: 100%; height: 100%; object-fit: contain; display: block; }
    .db-media-video { width: 100%; height: 100%; object-fit: contain; display: block; background: #000; }
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
        /* Filename bar removed — was visual noise on the thumbnail. Kept
           the rule so legacy renders that still attach .db-media-meta
           collapse silently instead of breaking layout. */
        display: none;
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
        <div style="display:flex; align-items:center; gap:8px;">
            <button class="db-btn db-btn-primary" id="dbBtnNewPost" disabled>+ Post i ri</button>
        </div>
    </div>

    <!-- Studio editor modal (iframe → /marketing/studio/{id}?embedded=1) (#1247) -->
    <div class="db-studio-modal-backdrop" id="dbStudioModal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="db-studio-modal">
            <div class="db-studio-modal-head">
                <div class="db-studio-modal-title" id="dbStudioModalTitle">Visual Studio</div>
                <div class="db-studio-modal-actions">
                    <a id="dbStudioModalOpenFull" class="db-studio-modal-open" href="#" target="_blank" rel="noopener">Hap në Studio</a>
                    <button class="db-studio-modal-close" id="dbStudioModalClose" type="button" aria-label="Mbyll">×</button>
                </div>
            </div>
            <iframe
                id="dbStudioModalIframe"
                class="db-studio-modal-iframe"
                title="Visual Studio editor"
                src="about:blank"
                allow="clipboard-read; clipboard-write; fullscreen"
            ></iframe>
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

    {{-- Shporta Ditore v2: summary strip (counts + stock + vlere) --}}
    <div class="db-summary" id="dbSummary" hidden>
        <div class="db-summary-title">Posts sot</div>
        <div class="db-summary-counts" id="dbSummaryCounts"></div>
        <div class="db-summary-sep"></div>
        <div class="db-summary-stats" id="dbSummaryStats"></div>
    </div>

    {{-- Two-column canvas + rail. Canvas: flat post-card grid (#1323).
         Rail: persistent product panel fed by /coverage (#1324). --}}
    <div class="db-main">
        <div class="db-canvas">
            <div class="db-grid" id="dbGrid">
                <div class="db-grid-empty">Zgjidh një ditë për të parë postet.</div>
            </div>
        </div>
        <aside class="db-rail" id="dbRail">
            <div class="db-rail-hdr">
                <div class="db-rail-hdr-top">
                    <div class="db-rail-hdr-title">📦 Shporta e ditës</div>
                    <select class="db-rail-filter" id="dbRailFilter" aria-label="Filtro produktet">
                        <option value="all">Të gjitha</option>
                        <option value="uncovered">Të pambuluara</option>
                        <option value="karrem">Karrem</option>
                        <option value="fashion">Fashion</option>
                        <option value="plotesues">Plotesues</option>
                        <option value="best_seller">Best Seller</option>
                    </select>
                </div>
                <div class="db-rail-hdr-sub" id="dbRailSub">—</div>
            </div>
            <div class="db-rail-cov-bar" id="dbRailCovBar">—</div>
            <div class="db-rail-list" id="dbRailList">
                <div class="db-rail-empty">Zgjidh një ditë për të parë produktet.</div>
            </div>
        </aside>
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
        coverage: null, // populated by /coverage endpoint after selectDay
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

    // Toast host is lazily mounted once at first use so the viewport-fixed
    // position doesn't care where the original dbErrors anchor sits in
    // the DOM. All message helpers funnel through `showToast`.
    function dbToastHost() {
        let host = document.getElementById('dbToastHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'dbToastHost';
            host.className = 'db-toast-host';
            document.body.appendChild(host);
        }
        return host;
    }

    function showToast(kind, msg, ttlMs) {
        const host = dbToastHost();
        const div = document.createElement('div');
        div.className = 'db-toast ' + (kind === 'ok' || kind === 'info' ? kind : 'err');
        div.textContent = msg;
        host.appendChild(div);
        const lifetime = typeof ttlMs === 'number' ? ttlMs : (kind === 'ok' ? 3500 : 6000);
        setTimeout(() => {
            if (!div.parentNode) return;
            div.style.transition = 'opacity 180ms';
            div.style.opacity = '0';
            setTimeout(() => { if (div.parentNode) div.remove(); }, 200);
        }, lifetime);
    }

    function showError(msg)   { showToast('err',  msg); }
    function showSuccess(msg) { showToast('ok',   msg); }
    function showInfo(msg)    { showToast('info', msg); }

    function getWeekIdFromUrl() {
        const params = new URLSearchParams(location.search);
        return parseInt(params.get('week'), 10) || null;
    }

    function getInitialDateFromUrl() {
        const params = new URLSearchParams(location.search);
        const d = params.get('date');
        return /^\d{4}-\d{2}-\d{2}$/.test(d || '') ? d : null;
    }

    function getInitialPostIdFromUrl() {
        const params = new URLSearchParams(location.search);
        return parseInt(params.get('post'), 10) || null;
    }

    // Consume once — after a deep-link open we clear ?date & ?post so reloads
    // or day changes don't keep flashing / overriding user navigation.
    function consumeDeepLinkParams() {
        const url = new URL(location.href);
        url.searchParams.delete('date');
        url.searchParams.delete('post');
        history.replaceState(null, '', url.toString());
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

            // Deep-link: grid sends ?week=&date=&post=. Honor the requested day
            // if it falls inside this collection; otherwise fall back to today
            // or the first day like before.
            const today = new Date().toISOString().slice(0, 10);
            const requested = getInitialDateFromUrl();
            const requestedInRange = requested && state.days.find(d => d.date === requested);
            const todayInRange = state.days.find(d => d.date === today);
            const targetDay = requestedInRange
                ? requested
                : (todayInRange ? today : state.days[0]?.date);
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

            // Enable "+ Post i ri" now that we have a basket.
            document.getElementById('dbBtnNewPost').disabled = false;

            // Coverage rollup for summary strip + rail (#1321 endpoint).
            // Fire-and-forget: board renders immediately, summary/rail
            // populate when the request completes.
            loadCoverage(num(data.basket?.id)).catch((e) => {
                console.warn('Coverage load failed:', e);
            });

            // Re-open the previously selected post if the user left this
            // basket with one open. Deep-linked post param overrides (below).
            const remembered = readPersistedSelectedPostId(num(data.basket?.id));
            if (remembered && findPostById(remembered)) {
                selectPost(remembered);
            }

            // Deep-link from grid: scroll to + flash the requested post, then
            // consume the URL params so subsequent navigation stays clean.
            const deepPostId = getInitialPostIdFromUrl();
            if (deepPostId) {
                // #1323 swapped .db-card (kanban columns) for .db-post
                // (flat grid). Keep .db-card as a fallback so a lingering
                // deploy with both classes still scrolls+flashes.
                const card = document.querySelector(
                    `.db-post[data-post-id="${deepPostId}"], .db-card[data-post-id="${deepPostId}"]`
                );
                if (card) {
                    selectPost(deepPostId);
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.classList.add('deeplink-flash');
                    setTimeout(() => card.classList.remove('deeplink-flash'), 1800);
                }
                consumeDeepLinkParams();
            }
        } catch (e) {
            showError('Ngarkimi i ditës dështoi: ' + e.message);
        }
    }

    // ── Coverage rollup (#1321 API): drives the summary strip above the
    //    canvas and the product rail on the right. Refetched whenever the
    //    user attaches/detaches a product or creates/deletes a post.
    //
    // Race guard: if the user switches days while a previous coverage
    // fetch is in flight, the stale response could land last and clobber
    // the current day's rail. We capture the basketId at call time and
    // bail on mismatch after the promise resolves.
    async function loadCoverage(basketId) {
        if (!basketId) return;
        const pendingBasketId = num(basketId);
        try {
            const data = await apiGet('/marketing/daily-basket/api/baskets/' + pendingBasketId + '/coverage');
            if (num(state.kanban?.basket?.id) !== pendingBasketId) return; // stale
            state.coverage = data;
            renderSummary(data);
            renderRail(data);
        } catch (e) {
            if (num(state.kanban?.basket?.id) !== pendingBasketId) return; // stale
            // Non-fatal — keep the board usable even if coverage fails.
            console.warn('Coverage fetch failed:', e);
            renderSummary(null);
            renderRail(null);
        }
    }

    function renderSummary(coverage) {
        const el = document.getElementById('dbSummary');
        if (!coverage || !coverage.summary) {
            el.hidden = true;
            return;
        }
        el.hidden = false;

        const s = coverage.summary;
        const countsWrap = document.getElementById('dbSummaryCounts');
        const statsWrap = document.getElementById('dbSummaryStats');
        countsWrap.textContent = '';
        statsWrap.textContent = '';

        // Total posts + per-type dots
        const total = document.createElement('div');
        total.className = 'db-summary-item';
        const totalNum = document.createElement('span');
        totalNum.className = 'num';
        totalNum.textContent = String(s.posts_total || 0);
        const totalLbl = document.createElement('span');
        totalLbl.className = 'lbl';
        totalLbl.textContent = 'poste';
        total.append(totalNum, totalLbl);
        countsWrap.appendChild(total);

        const typeOrder = [['reel', 'Reels'], ['photo', 'Photo'], ['story', 'Story'], ['carousel', 'Carousel']];
        typeOrder.forEach(([key, label]) => {
            const c = (s.posts_by_type && s.posts_by_type[key]) || 0;
            if (c === 0) return;
            const pill = document.createElement('span');
            pill.className = 'db-summary-type';
            pill.dataset.type = key;
            const dot = document.createElement('span');
            dot.className = 'dot';
            pill.appendChild(dot);
            pill.appendChild(document.createTextNode(c + ' ' + label));
            countsWrap.appendChild(pill);
        });

        // Coverage chip
        const cov = document.createElement('span');
        const uncovered = s.products_uncovered || 0;
        cov.className = 'db-summary-cov' + (uncovered === 0 ? ' ok' : (s.products_covered > 0 ? ' warn' : ''));
        cov.textContent = (s.products_covered || 0) + '/' + (s.products_total || 0) + ' produkte me post';
        countsWrap.appendChild(cov);

        // Right-side stats: stock + value
        const mkStat = (num, lbl) => {
            const d = document.createElement('div');
            d.className = 'db-summary-item';
            const n = document.createElement('span');
            n.className = 'num';
            n.textContent = num;
            const l = document.createElement('span');
            l.className = 'lbl';
            l.textContent = lbl;
            d.append(n, l);
            return d;
        };
        statsWrap.appendChild(mkStat((s.stok_total || 0).toLocaleString('sq-AL'), 'stok'));
        statsWrap.appendChild(mkStat(Math.round(s.vlere_total || 0).toLocaleString('sq-AL') + ' L', 'vlerë'));
    }

    // Rail UI state — filter + highlighted product for cross-grid selection.
    const railState = {
        filter: 'all',              // all | uncovered | karrem | fashion | plotesues | best_seller
        highlightedProductId: null, // click-to-highlight: mirrors .selected on the p-card
    };

    function renderRail(coverage) {
        const subEl = document.getElementById('dbRailSub');
        const covBar = document.getElementById('dbRailCovBar');
        const list = document.getElementById('dbRailList');

        if (!coverage || !coverage.products) {
            subEl.textContent = '—';
            covBar.textContent = '—';
            list.textContent = '';
            const empty = document.createElement('div');
            empty.className = 'db-rail-empty';
            empty.textContent = 'Zgjidh një ditë për të parë produktet.';
            list.appendChild(empty);
            return;
        }

        const dt = coverage.basket_date ? new Date(coverage.basket_date) : null;
        subEl.textContent = dt
            ? DAY_NAMES[dt.getDay()] + ' · ' + String(dt.getDate()).padStart(2, '0') + ' ' + MONTH_NAMES[dt.getMonth()]
            : '—';

        const s = coverage.summary || {};
        covBar.textContent = (s.products_covered || 0) + '/' + (s.products_total || 0) + ' mbuluar · '
            + (s.products_uncovered || 0) + ' pa post';

        list.textContent = '';

        // Apply filter BEFORE rendering. "uncovered" uses the posts_count
        // field; classification filters use the first tag.
        const filtered = (coverage.products || []).filter(p => {
            if (railState.filter === 'all') return true;
            if (railState.filter === 'uncovered') return (p.posts_count || 0) === 0;
            return (p.tags || []).includes(railState.filter);
        });

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'db-rail-empty';
            if (coverage.products.length === 0) {
                const heading = document.createElement('strong');
                heading.style.cssText = 'display:block; margin-bottom:6px; color:var(--db-text-2);';
                heading.textContent = 'Asnjë produkt i caktuar';
                empty.appendChild(heading);
                empty.appendChild(document.createTextNode('Cakto produkte për këtë ditë në DIS Merch Calendar.'));
            } else {
                empty.textContent = 'Asnjë produkt nuk përputhet me filtrin.';
            }
            list.appendChild(empty);
            return;
        }

        filtered.forEach(p => list.appendChild(buildRailCard(p)));
    }

    function buildRailCard(p) {
        const card = document.createElement('div');
        card.className = 'db-p-card';
        card.dataset.productId = p.item_group_id;
        card.draggable = true;
        card.tabIndex = 0; // keyboard reachable for ↑/↓ navigation
        card.title = 'Tërhiq mbi një post për ta shtuar atje';
        if (railState.highlightedProductId === p.item_group_id) {
            card.classList.add('selected');
        }

        // Thumb (image if available, otherwise colored initial)
        const thumb = document.createElement('div');
        thumb.className = 'db-p-thumb';
        if (p.thumbnail_url) {
            const img = document.createElement('img');
            img.src = p.thumbnail_url;
            img.alt = '';
            img.loading = 'lazy';
            img.onerror = () => {
                img.remove();
                thumb.style.background = 'hsl(' + hueFor(p.name || p.item_group_id) + ', 55%, 55%)';
                thumb.textContent = (p.name || '?').charAt(0).toUpperCase();
            };
            thumb.appendChild(img);
        } else {
            thumb.style.background = 'hsl(' + hueFor(p.name || p.item_group_id) + ', 55%, 55%)';
            thumb.textContent = (p.name || '?').charAt(0).toUpperCase();
        }
        card.appendChild(thumb);

        const body = document.createElement('div');
        body.className = 'db-p-body';
        const name = document.createElement('div');
        name.className = 'db-p-name';
        name.textContent = p.name || '—';
        const meta = document.createElement('div');
        meta.className = 'db-p-meta';
        const metaParts = [];
        if (p.sku) metaParts.push(p.sku);
        if (p.price) metaParts.push(Math.round(p.price).toLocaleString('sq-AL') + ' L');
        metaParts.push((p.stock || 0) + ' pcs');
        meta.textContent = metaParts.join(' · ');
        body.append(name, meta);
        card.appendChild(body);

        const cov = document.createElement('span');
        const c = p.posts_count || 0;
        const badgeClass = c === 0 ? 'db-cov-0' : (c === 1 ? 'db-cov-1' : 'db-cov-2');
        cov.className = 'db-cov-badge ' + badgeClass;
        cov.textContent = String(c);
        cov.title = c + ' post' + (c === 1 ? '' : 'e');
        card.appendChild(cov);

        // Click → toggle highlight of posts that contain this product.
        card.addEventListener('click', () => toggleProductHighlight(num(p.item_group_id)));

        // Drag → attach product onto a post on drop (wired in `wirePostDropTargets`).
        card.addEventListener('dragstart', (e) => {
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', 'product:' + p.item_group_id);
        });
        card.addEventListener('dragend', () => card.classList.remove('dragging'));

        return card;
    }

    // Click-to-highlight: marks the rail card as .selected and adds
    // .highlighted to every post card whose data-product-ids CSV contains
    // that id. Re-click (or click a different product) clears the prior
    // highlight first.
    function toggleProductHighlight(productId) {
        const same = railState.highlightedProductId === productId;
        railState.highlightedProductId = same ? null : productId;

        document.querySelectorAll('.db-p-card').forEach(c => {
            c.classList.toggle('selected', num(c.dataset.productId) === railState.highlightedProductId);
        });

        document.querySelectorAll('.db-post[data-post-id]').forEach(card => {
            const ids = (card.dataset.productIds || '').split(',').map(s => parseInt(s, 10));
            card.classList.toggle(
                'highlighted',
                railState.highlightedProductId != null && ids.includes(railState.highlightedProductId),
            );
        });
    }

    // Drag-drop: rail cards → post cards. Endpoint is SYNC semantics, so
    // we assemble the FULL product list from data-product-ids + the new id
    // before PUT'ing. Re-fetches coverage + redraws so the cov-badge
    // increments immediately.
    function wirePostDropTargets() {
        const grid = document.getElementById('dbGrid');
        if (!grid || grid.dataset.dropWired === '1') return;
        grid.dataset.dropWired = '1';

        grid.addEventListener('dragover', (e) => {
            const post = e.target.closest('.db-post[data-post-id]');
            if (!post) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            post.classList.add('dragover');
        });
        grid.addEventListener('dragleave', (e) => {
            const post = e.target.closest('.db-post[data-post-id]');
            if (post) post.classList.remove('dragover');
        });
        grid.addEventListener('drop', async (e) => {
            const post = e.target.closest('.db-post[data-post-id]');
            if (!post) return;
            e.preventDefault();
            post.classList.remove('dragover');

            const payload = (e.dataTransfer.getData('text/plain') || '').trim();
            if (!payload.startsWith('product:')) return;
            const newProductId = num(payload.slice('product:'.length));
            if (!newProductId) return;

            const postId = num(post.dataset.postId);
            const existing = (post.dataset.productIds || '')
                .split(',').map(s => parseInt(s, 10)).filter(Boolean);
            if (existing.includes(newProductId)) {
                showInfo('Produkti ishte tashmë i bashkangjitur.');
                return;
            }
            const fullList = existing.concat([newProductId]);

            try {
                await apiPutJson(
                    '/marketing/daily-basket/api/posts/' + num(postId) + '/products',
                    { product_ids: fullList },
                );
                await selectDay(state.selectedDate);
                showSuccess('U shtua produkti te posti.');
            } catch (err) {
                showError('Shtimi dështoi: ' + err.message);
            }
        });
    }

    // ── Post card v2 (#1323) — flat grid, stage-agnostic ────────
    function renderBoard(data) {
        const grid = document.getElementById('dbGrid');
        grid.textContent = '';

        const posts = flattenPosts(data);

        posts.forEach(post => grid.appendChild(buildPostCardV2(post)));
        grid.appendChild(buildEmptyPostCard(posts.length));

        // Register drop targets once — delegation handles future re-renders.
        wirePostDropTargets();

        // If the user had a product highlighted before this re-render, the
        // new post cards are missing the .highlighted class — re-apply.
        if (railState.highlightedProductId != null) {
            const pid = railState.highlightedProductId;
            document.querySelectorAll('.db-post[data-post-id]').forEach(card => {
                const ids = (card.dataset.productIds || '').split(',').map(s => parseInt(s, 10));
                if (ids.includes(pid)) card.classList.add('highlighted');
            });
        }
    }

    function flattenPosts(data) {
        const flat = [];
        (data.columns || []).forEach(col => (col.posts || []).forEach(p => flat.push(p)));
        flat.sort((a, b) => {
            const ao = a.sort_order ?? 0, bo = b.sort_order ?? 0;
            if (ao !== bo) return ao - bo;
            return a.id - b.id;
        });
        return flat;
    }

    // Stage-value → dot colour is CSS-driven via [data-stage]; this table is
    // only for the human-readable label next to the dot.
    const STAGE_SHORT = {
        planning: 'Planifikim',
        production: 'Prodhim',
        editing: 'Editim',
        scheduling: 'Skedulim',
        published: 'Publikuar',
    };

    // Deterministic hue from a free-text key — used for the 16px product
    // chip thumbs when a product has no image and we have to paint an
    // initial on a gradient. Keeps the same product looking the same every
    // render without shipping a colour table.
    function hueFor(key) {
        const s = String(key || '');
        let h = 0;
        for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
        return Math.abs(h) % 360;
    }

    function buildPostCardV2(post) {
        const card = document.createElement('div');
        card.className = 'db-post';
        card.dataset.postId = post.id;
        // Comma-separated — matches #1324 / #1326 click-to-highlight lookups.
        card.dataset.productIds = (post.products || []).map(p => p.item_group_id).join(',');
        if (post.id === state.selectedPostId) card.classList.add('selected');
        card.addEventListener('click', (e) => {
            if (e.target.closest('button, a, input, textarea, .db-post-chip, .db-post-chip-x')) return;
            selectPost(num(post.id));
        });

        card.appendChild(buildPostTop(post));
        card.appendChild(buildPostMat(post));
        card.appendChild(buildPostBody(post));
        return card;
    }

    function buildPostTop(post) {
        const top = document.createElement('div');
        top.className = 'db-post-top';

        const type = document.createElement('span');
        type.className = 'db-post-type';
        type.dataset.type = post.post_type || 'photo';
        type.textContent = post.post_type_label || (post.post_type || '').toUpperCase();
        top.appendChild(type);

        const numEl = document.createElement('span');
        numEl.className = 'db-post-num';
        numEl.textContent = post.title || ('Post #' + post.id);
        top.appendChild(numEl);

        const stage = document.createElement('span');
        stage.className = 'db-post-stage';
        const dot = document.createElement('span');
        dot.className = 'db-stage-dot';
        dot.dataset.stage = post.stage || 'planning';
        stage.appendChild(dot);
        stage.appendChild(document.createTextNode(STAGE_SHORT[post.stage] || post.stage_label || ''));
        top.appendChild(stage);

        // Delete button — removes the whole post after confirm. Visible on
        // hover only so it doesn't compete with the stage/type badges.
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'db-post-del';
        del.title = 'Hiq këtë post';
        del.textContent = '×';
        del.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!confirm('Të hiqet ky post? (' + (post.title || 'Post #' + post.id) + ')')) return;
            try {
                await apiDelete('/marketing/daily-basket/api/posts/' + num(post.id));
                if (state.selectedPostId === post.id) {
                    state.selectedPostId = null;
                    persistSelectedPostId(null);
                }
                await selectDay(state.selectedDate);
                showSuccess('Posti u hoq.');
            } catch (err) {
                showError('Heqja dështoi: ' + err.message);
            }
        });
        top.appendChild(del);

        return top;
    }

    function buildPostMat(post) {
        const mat = document.createElement('div');
        mat.className = 'db-mat';
        mat.dataset.type = post.post_type || 'photo';

        const all = Array.isArray(post.media) ? post.media : [];

        if (all.length === 0) {
            mat.appendChild(buildMatEmpty());
            return mat;
        }

        // Single slot that the render() function overwrites when the user
        // clicks dots / arrows. Kept in one place so we don't rebuild the
        // whole card on every navigation click.
        const slot = document.createElement('div');
        slot.className = 'db-mat-slot';
        mat.appendChild(slot);

        let idx = 0;
        const renderSlot = () => {
            slot.textContent = '';
            const m = all[idx];
            if (!m) return;
            if (m.is_video) {
                const v = document.createElement('video');
                v.src = m.url;
                v.muted = true;
                v.playsInline = true;
                v.preload = 'metadata';
                if (m.thumbnail_url) v.poster = m.thumbnail_url;
                slot.appendChild(v);
            } else {
                const img = document.createElement('img');
                img.src = m.thumbnail_url || m.url;
                img.alt = '';
                img.loading = 'lazy';
                img.onerror = () => { slot.textContent = ''; slot.appendChild(buildMatEmpty()); };
                slot.appendChild(img);
            }
        };
        renderSlot();

        // Carousel navigation only appears when there are 2+ items.
        // Dots are always visible (Instagram-style), arrows only on hover.
        // Clicks on either stop propagation so the card's selectPost
        // handler doesn't fire underneath.
        if (all.length > 1) {
            const dots = document.createElement('div');
            dots.className = 'db-mat-dots';
            const dotEls = all.map((_, i) => {
                const d = document.createElement('span');
                d.className = 'db-mat-dot' + (i === 0 ? ' active' : '');
                d.addEventListener('click', (e) => {
                    e.stopPropagation();
                    idx = i;
                    dotEls.forEach((x, k) => x.classList.toggle('active', k === idx));
                    renderSlot();
                });
                dots.appendChild(d);
                return d;
            });
            mat.appendChild(dots);

            const step = (delta) => (e) => {
                e.stopPropagation();
                idx = (idx + delta + all.length) % all.length;
                dotEls.forEach((x, k) => x.classList.toggle('active', k === idx));
                renderSlot();
            };
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'db-mat-arrow prev';
            prev.textContent = '‹';
            prev.title = 'Foto e mëparshme';
            prev.addEventListener('click', step(-1));

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'db-mat-arrow next';
            next.textContent = '›';
            next.title = 'Foto tjetër';
            next.addEventListener('click', step(+1));

            mat.append(prev, next);
        }

        return mat;
    }

    function buildMatEmpty() {
        const e = document.createElement('div');
        e.className = 'db-mat-empty';
        const ic = document.createElement('div');
        ic.className = 'icon';
        ic.textContent = '🖼️';
        const tx = document.createElement('div');
        tx.textContent = 'Pa material ende';
        e.append(ic, tx);
        return e;
    }

    function buildPostBody(post) {
        const body = document.createElement('div');
        body.className = 'db-post-body';

        // Product chips — always render the row with a trailing "+" button
        // so the user can attach products without opening the detail view.
        const chips = document.createElement('div');
        chips.className = 'db-post-chips';
        const products = post.products || [];
        products.slice(0, 4).forEach(p => chips.appendChild(buildPostChip(p)));
        if (products.length > 4) {
            const more = document.createElement('span');
            more.className = 'db-post-chip';
            more.style.paddingLeft = '7px';
            more.textContent = '+' + (products.length - 4);
            chips.appendChild(more);
        }

        const addProd = document.createElement('button');
        addProd.type = 'button';
        addProd.className = 'db-post-chip-add';
        addProd.textContent = products.length === 0 ? '+ Shto produkt' : '+';
        addProd.title = 'Shto produkt nga shporta';
        addProd.addEventListener('click', (e) => {
            e.stopPropagation();
            openPlanProductPicker(post, addProd);
        });
        chips.appendChild(addProd);
        body.appendChild(chips);

        // Reference chip (favicon + host) — renders only when there's a URL.
        if (post.reference_url) {
            const ref = document.createElement('a');
            ref.className = 'db-post-ref-compact';
            ref.href = post.reference_url;
            ref.target = '_blank';
            ref.rel = 'noopener noreferrer';
            ref.addEventListener('click', (e) => e.stopPropagation());

            const host = post.reference_host || (() => {
                try { return new URL(post.reference_url).hostname.replace(/^www\./, ''); }
                catch (_) { return 'link'; }
            })();

            const fav = document.createElement('span');
            fav.className = 'fav';
            fav.style.background = 'hsl(' + hueFor(host) + ', 55%, 45%)';
            fav.textContent = host.charAt(0).toUpperCase();
            // Use Google's favicon service for a real icon when available.
            const favImg = document.createElement('img');
            favImg.src = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(host) + '&sz=32';
            favImg.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:2px;';
            favImg.onload = () => { fav.textContent = ''; fav.style.background = '#fff'; fav.appendChild(favImg); };
            favImg.onerror = () => { /* keep initial fallback */ };
            ref.appendChild(fav);

            const hostSpan = document.createElement('span');
            hostSpan.className = 'host';
            hostSpan.textContent = host;
            ref.appendChild(hostSpan);

            const arrow = document.createElement('span');
            arrow.textContent = '↗';
            arrow.style.cssText = 'color:var(--db-text-3); font-size:11px;';
            ref.appendChild(arrow);

            body.appendChild(ref);
        } else {
            // No reference yet — render a compact "+" button that prompts
            // for a URL inline. Saves through savePostField → PUT /posts/{id}
            // and reloads the day so the compact chip replaces this button.
            const addRef = document.createElement('button');
            addRef.type = 'button';
            addRef.className = 'db-post-ref-add';
            addRef.textContent = '+ Shto reference';
            addRef.title = 'Shto URL referencë (Pinterest, Instagram, etj.)';
            addRef.addEventListener('click', async (e) => {
                e.stopPropagation();
                const url = prompt('URL e referencës (p.sh. https://pinterest.com/pin/…):', '');
                if (url == null) return;
                const trimmed = url.trim();
                if (trimmed === '') return;
                try {
                    await savePostField(post, { reference_url: trimmed });
                    await selectDay(state.selectedDate);
                } catch (err) {
                    showError('Ruajtja dështoi: ' + err.message);
                }
            });
            body.appendChild(addRef);
        }

        return body;
    }

    function buildPostChip(p) {
        const chip = document.createElement('span');
        chip.className = 'db-post-chip';
        chip.style.cursor = 'pointer';
        chip.title = 'Klik për preview · ' + (p.name || '');

        // Click → show product detail popover (photo + name + code + price).
        // stopPropagation so it doesn't also trigger selectPost on the card.
        chip.addEventListener('click', (e) => {
            e.stopPropagation();
            openProductPreview(p.item_group_id, chip);
        });

        const thumbHost = document.createElement('span');
        thumbHost.className = 'db-post-chip-thumb';
        if (p.image_url) {
            const img = document.createElement('img');
            img.src = p.image_url;
            img.alt = '';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
            img.onerror = () => {
                img.remove();
                thumbHost.style.background = 'hsl(' + hueFor(p.name || p.item_group_id) + ', 55%, 55%)';
                thumbHost.textContent = (p.name || '?').charAt(0).toUpperCase();
            };
            thumbHost.appendChild(img);
        } else {
            thumbHost.style.background = 'hsl(' + hueFor(p.name || p.item_group_id) + ', 55%, 55%)';
            thumbHost.textContent = (p.name || '?').charAt(0).toUpperCase();
        }
        chip.appendChild(thumbHost);

        const nameSpan = document.createElement('span');
        nameSpan.className = 'db-post-chip-name';
        nameSpan.textContent = p.name || '—';
        chip.appendChild(nameSpan);

        return chip;
    }

    // "+ Post i ri" tile at the end of the grid — click → inline type picker,
    // then POST /baskets/{id}/posts with the chosen type.
    function buildEmptyPostCard(existingCount) {
        const card = document.createElement('div');
        card.className = 'db-post db-post-empty';

        const plus = document.createElement('div');
        plus.className = 'plus';
        plus.textContent = '+';
        card.appendChild(plus);

        const lbl = document.createElement('div');
        lbl.className = 'lbl';
        lbl.textContent = 'Post i ri';
        card.appendChild(lbl);

        const sub = document.createElement('div');
        sub.className = 'sub';
        sub.textContent = 'Zgjidh tipin:';
        card.appendChild(sub);

        const row = document.createElement('div');
        row.className = 'type-row';
        const types = [
            { v: 'photo', l: 'Photo' },
            { v: 'reel', l: 'Reel' },
            { v: 'story', l: 'Story' },
            { v: 'carousel', l: 'Carousel' },
            { v: 'video', l: 'Video' },
        ];
        types.forEach(t => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'type-pick';
            b.textContent = t.l;
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                createQuickPost(t.v, existingCount + 1);
            });
            row.appendChild(b);
        });
        card.appendChild(row);

        // Also clicking the card body (not the buttons) opens the full
        // modal so the user can pick title + products up front.
        card.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            openNewPostModal();
        });

        return card;
    }

    async function createQuickPost(postType, slot) {
        const basketId = state.kanban?.basket?.id;
        if (!basketId) return;
        try {
            await apiPost('/marketing/daily-basket/api/baskets/' + num(basketId) + '/posts', {
                title: 'Post ' + slot,
                post_type: postType || 'photo',
                priority: 'normal',
            });
            await selectDay(state.selectedDate);
        } catch (e) {
            showError('Krijimi dështoi: ' + e.message);
        }
    }

    // Popover dismiss helper — close on outside click or Escape.
    function wirePopoverDismiss(pop, anchorEl) {
        const cleanup = () => {
            pop.remove();
            document.removeEventListener('click', onDoc);
            document.removeEventListener('keydown', onKey);
        };
        const onDoc = (ev) => {
            if (!pop.contains(ev.target) && ev.target !== anchorEl && !anchorEl.contains(ev.target)) {
                cleanup();
            }
        };
        const onKey = (ev) => { if (ev.key === 'Escape') cleanup(); };
        setTimeout(() => {
            document.addEventListener('click', onDoc);
            document.addEventListener('keydown', onKey);
        }, 0);
    }

    // Small 5-item picker for post_type — shown before creating a post.
    function openPostTypePicker(anchorEl, onPick) {
        document.querySelectorAll('.db-plan-pop').forEach(p => p.remove());
        const pop = document.createElement('div');
        pop.className = 'db-plan-pop';
        pop.style.width = '180px';
        pop.style.padding = '6px';
        const rect = anchorEl.getBoundingClientRect();
        pop.style.top = (window.scrollY + rect.bottom + 4) + 'px';
        pop.style.left = (window.scrollX + rect.left) + 'px';

        const hdr = document.createElement('div');
        hdr.style.cssText = 'font-size:10px; font-weight:600; color:var(--db-text-3); text-transform:uppercase; letter-spacing:0.05em; padding:4px 6px 6px;';
        hdr.textContent = 'Lloji i postit';
        pop.appendChild(hdr);

        const types = [
            { value: 'photo',    label: '📸 Photo' },
            { value: 'video',    label: '🎥 Video' },
            { value: 'reel',     label: '🎬 Reel' },
            { value: 'carousel', label: '🖼️ Carousel' },
            { value: 'story',    label: '✨ Story' },
        ];
        types.forEach(t => {
            const item = document.createElement('div');
            item.className = 'db-plan-pop-item';
            item.style.cssText = 'padding:7px 8px; font-size:12px; font-weight:500;';
            item.textContent = t.label;
            item.addEventListener('click', () => {
                pop.remove();
                onPick(t.value);
            });
            pop.appendChild(item);
        });

        document.body.appendChild(pop);
        wirePopoverDismiss(pop, anchorEl);
    }

    // Product detail preview popover — opens when clicking a chip (not its ×).
    // Fashion shots are portrait 3:4 / 2:3; object-fit:contain keeps the
    // whole frame visible. The popover auto-flips above the chip when it
    // would overflow the bottom of the viewport so the user doesn't have
    // to scroll to see the full photo.
    function openProductPreview(itemGroupId, anchorEl) {
        document.querySelectorAll('.db-plan-pop').forEach(p => p.remove());
        const full = (state.availableProducts || []).find(p => num(p.id) === num(itemGroupId));

        const pop = document.createElement('div');
        pop.className = 'db-plan-pop';
        pop.style.width = '320px';
        pop.style.maxHeight = '80vh';
        pop.style.overflowY = 'auto';
        pop.style.padding = '12px';
        pop.style.boxShadow = '0 20px 50px rgba(0,0,0,0.22)';

        if (!full) {
            const msg = document.createElement('div');
            msg.style.cssText = 'font-size:11px; color:var(--db-text-3); text-align:center; padding:8px;';
            msg.textContent = 'Te dhenat e produktit nuk u gjeten ne kolekcionin aktiv.';
            pop.appendChild(msg);
        } else {
            if (full.image_url) {
                const img = document.createElement('img');
                img.src = full.image_url;
                // max-height tied to viewport so the full photo + name + meta
                // stays inside 80vh; contain preserves the actual fashion
                // framing (no model heads/feet cut off).
                img.style.cssText = 'width:100%; max-height:60vh; object-fit:contain; border-radius:6px; background:#f4f4f5; display:block;';
                img.onerror = () => { img.style.display = 'none'; };
                pop.appendChild(img);
            }
            const name = document.createElement('div');
            name.style.cssText = 'font-size:13px; font-weight:600; margin-top:10px; color:var(--db-text);';
            name.textContent = full.name || '';
            pop.appendChild(name);

            const meta = document.createElement('div');
            meta.style.cssText = 'font-size:11px; color:var(--db-text-3); margin-top:4px; line-height:1.5;';
            const bits = [];
            if (full.code) bits.push(full.code);
            if (full.classification) bits.push(full.classification);
            if (full.avg_price != null) bits.push(Math.round(+full.avg_price).toLocaleString('sq-AL') + ' L');
            meta.textContent = bits.join(' · ');
            pop.appendChild(meta);
        }

        // Append first so we can measure the real popover size, then
        // compute placement that stays on-screen.
        document.body.appendChild(pop);

        const rect = anchorEl.getBoundingClientRect();
        const popRect = pop.getBoundingClientRect();
        const margin = 8;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        // Vertical: prefer below, flip above if it would overflow bottom.
        let top = rect.bottom + 6;
        if (top + popRect.height > vh - margin) {
            const aboveTop = rect.top - popRect.height - 6;
            top = aboveTop >= margin ? aboveTop : Math.max(margin, vh - popRect.height - margin);
        }

        // Horizontal: start aligned to chip left, clamp so it stays in view.
        let left = rect.left;
        if (left + popRect.width > vw - margin) {
            left = Math.max(margin, vw - popRect.width - margin);
        }
        if (left < margin) left = margin;

        pop.style.top = (top + window.scrollY) + 'px';
        pop.style.left = (left + window.scrollX) + 'px';

        wirePopoverDismiss(pop, anchorEl);
    }

    function buildPlanCellFilled(post, slotNumber) {
        const cell = document.createElement('div');
        cell.className = 'db-plan-cell';
        if (post.id === state.selectedPostId) cell.classList.add('selected');
        cell.addEventListener('click', (e) => {
            // Clicks inside inputs / buttons / chips shouldn't re-select.
            if (e.target.closest('input, textarea, button, a, .db-plan-pop, .db-plan-chip')) return;
            selectPost(num(post.id));
        });

        // Header: slot number + stage dot + delete
        const hdr = document.createElement('div');
        hdr.className = 'db-plan-cell-num';
        const left = document.createElement('span');
        left.textContent = 'Post ' + slotNumber + ' · ' + (post.post_type_label || post.post_type);

        const right = document.createElement('span');
        right.style.cssText = 'display:flex; align-items:center; gap:2px;';

        const dot = document.createElement('span');
        dot.className = 'db-plan-stage-dot';
        dot.dataset.stage = post.stage;
        dot.title = post.stage_label || post.stage;
        right.appendChild(dot);

        // Pencil: direct upload based on the post's format. Photo/carousel/
        // story open the image picker; reel/video open the video picker.
        // No modal — just native file picker → POST to the brief.
        const isVideoPost = ['video', 'reel'].includes(post.post_type);
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'db-plan-cell-edit';
        edit.title = isVideoPost ? 'Ngarko video' : 'Ngarko foto';
        edit.textContent = '✎';
        edit.addEventListener('click', (e) => {
            e.stopPropagation();
            triggerDirectUpload(post).catch((err) => showError('Upload dështoi: ' + err.message));
        });
        right.appendChild(edit);

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'db-plan-cell-del';
        del.title = 'Hiq postin';
        del.textContent = '×';
        del.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!confirm('Te hiqet ky post? (' + (post.title || '') + ')')) return;
            try {
                await apiDelete('/marketing/daily-basket/api/posts/' + num(post.id));
                if (state.selectedPostId === post.id) state.selectedPostId = null;
                await selectDay(state.selectedDate);
            } catch (err) { showError('Heqja deshtoi: ' + err.message); }
        });
        right.appendChild(del);

        hdr.append(left, right);
        cell.appendChild(hdr);

        // Media block
        cell.appendChild(buildPlanMediaBlock(post));

        // Reference URL — input + favicon preview
        const refField = document.createElement('div');
        refField.className = 'db-plan-field';
        const refLbl = document.createElement('span');
        refLbl.className = 'db-plan-field-lbl';
        refLbl.textContent = 'Reference URL';
        refField.appendChild(refLbl);

        const refPreview = document.createElement('div');
        refPreview.style.cssText = 'margin-top: 2px;';

        const renderRefPreview = (url) => {
            refPreview.textContent = '';
            if (!url) return;
            let host;
            try { host = new URL(url).hostname.replace(/^www\./, ''); } catch (_) { host = url; }
            const a = document.createElement('a');
            a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer';
            a.className = 'db-plan-ref'; a.title = url;
            const fav = document.createElement('img');
            fav.src = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(host) + '&sz=32';
            fav.onerror = () => { fav.style.display = 'none'; };
            const nm = document.createElement('span');
            nm.textContent = host;
            a.append(fav, nm);
            refPreview.appendChild(a);
        };

        const refInput = document.createElement('input');
        refInput.type = 'url';
        refInput.className = 'db-plan-input';
        refInput.placeholder = 'https://…';
        refInput.value = post.reference_url || '';
        const saveRef = async () => {
            const v = refInput.value.trim() || null;
            if (v === (post.reference_url || null)) return;
            try {
                await savePostField(post, { reference_url: v });
                flashSaved(refInput);
                renderRefPreview(v);
            } catch (e) {
                showError('Ruajtja deshtoi: ' + e.message);
                refInput.value = post.reference_url || '';
            }
        };
        refInput.addEventListener('blur', saveRef);
        refInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); refInput.blur(); }
            if (e.key === 'Escape') { refInput.value = post.reference_url || ''; refInput.blur(); }
        });
        refField.append(refInput, refPreview);
        renderRefPreview(post.reference_url);
        cell.appendChild(refField);

        // Products chips
        cell.appendChild(buildPlanProductsBlock(post));

        // Note (= reference_notes)
        const noteField = document.createElement('div');
        noteField.className = 'db-plan-field';
        const noteLbl = document.createElement('span');
        noteLbl.className = 'db-plan-field-lbl';
        noteLbl.textContent = 'Note';
        const noteTa = document.createElement('textarea');
        noteTa.className = 'db-plan-textarea';
        noteTa.rows = 2;
        noteTa.placeholder = 'Mood, location, model…';
        noteTa.value = post.reference_notes || '';
        const saveNote = async () => {
            const v = noteTa.value.trim() || null;
            if (v === (post.reference_notes || null)) return;
            try {
                await savePostField(post, { reference_notes: v });
                flashSaved(noteTa);
            } catch (e) {
                showError('Ruajtja deshtoi: ' + e.message);
                noteTa.value = post.reference_notes || '';
            }
        };
        noteTa.addEventListener('blur', saveNote);
        noteTa.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { noteTa.value = post.reference_notes || ''; noteTa.blur(); }
        });
        noteField.append(noteLbl, noteTa);
        cell.appendChild(noteField);

        return cell;
    }

    // Products block — chips + "+ Shto" opens a compact popover.
    function buildPlanProductsBlock(post) {
        const wrap = document.createElement('div');
        wrap.className = 'db-plan-field';
        const lbl = document.createElement('span');
        lbl.className = 'db-plan-field-lbl';
        lbl.textContent = 'Produktet';
        wrap.appendChild(lbl);

        const chips = document.createElement('div');
        chips.className = 'db-plan-chips';
        (post.products || []).forEach(p => chips.appendChild(buildProductChip(post, p)));

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'db-plan-chip-add';
        addBtn.textContent = '+ Shto';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openPlanProductPicker(post, addBtn);
        });
        chips.appendChild(addBtn);

        wrap.appendChild(chips);
        return wrap;
    }

    function buildProductChip(post, p) {
        const chip = document.createElement('span');
        chip.className = 'db-plan-chip';
        chip.style.cursor = 'pointer';
        chip.title = 'Klik per preview · ' + (p.name || '');

        // Click on the chip (not its ×) opens a preview popover.
        chip.addEventListener('click', (e) => {
            if (e.target.closest('.db-plan-chip-del')) return;
            e.stopPropagation();
            openProductPreview(p.item_group_id, chip);
        });

        if (p.image_url) {
            const img = document.createElement('img');
            img.className = 'db-plan-chip-thumb';
            img.src = p.image_url;
            img.alt = '';
            img.onerror = () => { img.replaceWith(document.createElement('span')); };
            chip.appendChild(img);
        } else {
            const th = document.createElement('span');
            th.className = 'db-plan-chip-thumb';
            chip.appendChild(th);
        }
        const nm = document.createElement('span');
        nm.className = 'db-plan-chip-name';
        nm.textContent = p.name || ('#' + num(p.item_group_id));
        chip.appendChild(nm);

        const del = document.createElement('span');
        del.className = 'db-plan-chip-del';
        del.textContent = '×';
        del.title = 'Hiq';
        del.addEventListener('click', async (e) => {
            e.stopPropagation();
            const nextIds = (post.products || [])
                .filter(x => num(x.item_group_id) !== num(p.item_group_id))
                .map(x => num(x.item_group_id));
            try {
                await apiPutJson('/marketing/daily-basket/api/posts/' + num(post.id) + '/products', {
                    product_ids: nextIds,
                    hero_product_id: nextIds[0] || null,
                });
                await selectDay(state.selectedDate);
            } catch (err) { showError('Heqja deshtoi: ' + err.message); }
        });
        chip.appendChild(del);
        return chip;
    }

    function openPlanProductPicker(post, triggerEl) {
        // Close any existing popover first.
        document.querySelectorAll('.db-plan-pop').forEach(p => p.remove());

        const pop = document.createElement('div');
        pop.className = 'db-plan-pop';
        const rect = triggerEl.getBoundingClientRect();
        pop.style.top = (window.scrollY + rect.bottom + 4) + 'px';
        pop.style.left = (window.scrollX + rect.left) + 'px';

        const search = document.createElement('input');
        search.className = 'db-plan-pop-search';
        search.type = 'text';
        search.placeholder = 'Kerko produkt…';
        pop.appendChild(search);

        const listHost = document.createElement('div');
        pop.appendChild(listHost);

        const assignedIds = new Set((post.products || []).map(p => num(p.item_group_id)));
        const renderList = (query) => {
            listHost.textContent = '';
            const q = (query || '').toLowerCase().trim();
            const filtered = (state.availableProducts || []).filter(p => {
                if (!q) return true;
                return (p.name || '').toLowerCase().includes(q)
                    || (p.code || '').toLowerCase().includes(q);
            });
            if (filtered.length === 0) {
                const e = document.createElement('div');
                e.className = 'db-plan-pop-empty';
                e.textContent = 'Pa rezultate';
                listHost.appendChild(e);
                return;
            }
            filtered.slice(0, 40).forEach(p => {
                const item = document.createElement('div');
                item.className = 'db-plan-pop-item' + (assignedIds.has(num(p.id)) ? ' selected' : '');

                if (p.image_url) {
                    const img = document.createElement('img');
                    img.className = 'db-plan-pop-thumb';
                    img.src = p.image_url;
                    img.onerror = () => { img.replaceWith(document.createElement('div')); };
                    item.appendChild(img);
                } else {
                    item.appendChild(document.createElement('div'));
                }

                const info = document.createElement('div');
                info.style.cssText = 'flex:1; min-width:0;';
                const nm = document.createElement('div');
                nm.className = 'db-plan-pop-name';
                nm.textContent = p.name;
                const sub = document.createElement('div');
                sub.className = 'db-plan-pop-sub';
                sub.textContent = [p.code, p.classification].filter(Boolean).join(' · ');
                info.append(nm, sub);
                item.appendChild(info);

                item.addEventListener('click', async () => {
                    const existing = (post.products || []).map(x => num(x.item_group_id));
                    const nextIds = assignedIds.has(num(p.id))
                        ? existing.filter(x => x !== num(p.id))
                        : [...existing, num(p.id)];
                    try {
                        await apiPutJson('/marketing/daily-basket/api/posts/' + num(post.id) + '/products', {
                            product_ids: nextIds,
                            hero_product_id: nextIds[0] || null,
                        });
                        pop.remove();
                        await selectDay(state.selectedDate);
                    } catch (err) { showError('Ndryshimi deshtoi: ' + err.message); }
                });
                listHost.appendChild(item);
            });
        };
        renderList('');
        search.addEventListener('input', () => renderList(search.value));

        document.body.appendChild(pop);
        setTimeout(() => search.focus(), 10);

        // Close on outside click or Escape
        const onDoc = (ev) => {
            if (!pop.contains(ev.target) && ev.target !== triggerEl) {
                pop.remove();
                document.removeEventListener('click', onDoc);
                document.removeEventListener('keydown', onKey);
            }
        };
        const onKey = (ev) => {
            if (ev.key === 'Escape') {
                pop.remove();
                document.removeEventListener('click', onDoc);
                document.removeEventListener('keydown', onKey);
            }
        };
        setTimeout(() => {
            document.addEventListener('click', onDoc);
            document.addEventListener('keydown', onKey);
        }, 0);
    }

    // Aspect ratio + hint shown under the media — matches the platform
    // format each post_type targets.
    function mediaAspectFor(postType) {
        switch (postType) {
            case 'reel':
            case 'story':
                return { cls: 'aspect-916', hint: '9:16 · vertikale' };
            case 'carousel':
                return { cls: 'aspect-45', hint: '4:5 · carousel (disa foto)' };
            case 'video':
                return { cls: 'aspect-45', hint: '4:5 · video' };
            case 'photo':
            default:
                return { cls: 'aspect-45', hint: '4:5 · foto' };
        }
    }

    // Compact media block inside a plan cell. Aspect ratio follows the
    // post_type so the user sees the publishing format before uploading.
    function buildPlanMediaBlock(post) {
        const container = document.createElement('div');

        const wrap = document.createElement('div');
        const aspect = mediaAspectFor(post.post_type);
        wrap.className = 'db-plan-media ' + aspect.cls;

        const media = Array.isArray(post.media) ? post.media : [];
        const first = media[0];

        if (first) {
            wrap.classList.add('has-media');
            if (first.is_video) {
                const v = document.createElement('video');
                v.src = first.url;
                v.muted = true;
                v.playsInline = true;
                v.preload = 'metadata';
                wrap.appendChild(v);
            } else {
                const img = document.createElement('img');
                img.src = first.thumbnail_url || first.url;
                wrap.appendChild(img);
            }

            if (media.length > 1) {
                const cnt = document.createElement('div');
                cnt.className = 'db-plan-media-count';
                cnt.textContent = '+' + (media.length - 1);
                wrap.appendChild(cnt);
            }

            const del = document.createElement('button');
            del.className = 'db-plan-media-del';
            del.type = 'button';
            del.title = 'Hiq';
            del.textContent = '×';
            del.addEventListener('click', async (e) => {
                e.stopPropagation();
                if (!confirm('Te hiqet kjo media?')) return;
                try {
                    await apiDelete('/marketing/daily-basket/api/posts/' + num(post.id) + '/media/' + num(first.id));
                    await selectDay(state.selectedDate);
                } catch (err) { showError('Fshirja deshtoi: ' + err.message); }
            });
            wrap.appendChild(del);
        } else {
            // Empty state — click/drop uploads.
            const ph = document.createElement('div');
            ph.className = 'db-plan-media-empty';
            const icon = document.createElement('strong');
            icon.textContent = '⬆';
            ph.appendChild(icon);
            const tx = document.createElement('div');
            tx.textContent = 'Kliko ose terhiq material';
            ph.appendChild(tx);
            wrap.appendChild(ph);

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = acceptFor(post.post_type);
            input.style.display = 'none';
            wrap.appendChild(input);

            wrap.addEventListener('click', () => input.click());
            wrap.addEventListener('dragover', (e) => { e.preventDefault(); wrap.classList.add('is-dragover'); });
            wrap.addEventListener('dragleave', () => wrap.classList.remove('is-dragover'));
            wrap.addEventListener('drop', (e) => {
                e.preventDefault();
                wrap.classList.remove('is-dragover');
                if (e.dataTransfer?.files?.length) {
                    uploadToPlanCell(post, e.dataTransfer.files[0]);
                }
            });
            input.addEventListener('change', () => {
                if (input.files?.length) uploadToPlanCell(post, input.files[0]);
            });
        }

        container.appendChild(wrap);
        const hint = document.createElement('div');
        hint.className = 'db-plan-media-hint';
        hint.textContent = aspect.hint;
        container.appendChild(hint);
        return container;
    }

    async function uploadToPlanCell(post, file) {
        try {
            await apiUploadFile('/marketing/daily-basket/api/posts/' + num(post.id) + '/media', file);
            await selectDay(state.selectedDate);
        } catch (e) {
            showError('Ngarkimi deshtoi: ' + e.message);
        }
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

        // .db-post selector (v2 cards) — .db-card was the legacy kanban card
        // removed in #1323 but kept here for safety in case of stale markup.
        document.querySelectorAll('.db-post[data-post-id], .db-card').forEach(el =>
            el.classList.toggle('selected', parseInt(el.dataset.postId, 10) === postId)
        );

        renderSheet(post);
        persistSelectedPostId(postId);

        // Scroll the newly selected post card into view on touch-primary
        // contexts. Desktop users already saw the click, so this is a
        // no-op there (the card was already in viewport).
        const card = document.querySelector('.db-post[data-post-id="' + postId + '"]');
        if (card && typeof card.scrollIntoView === 'function') {
            const rect = card.getBoundingClientRect();
            if (rect.top < 80 || rect.bottom > window.innerHeight - 40) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    // localStorage key is per-basket so one basket's open post doesn't
    // bleed into another. Set/cleared automatically; best-effort — a
    // storage exception (private mode) just no-ops.
    function persistSelectedPostId(postId) {
        const basketId = state.kanban?.basket?.id;
        if (!basketId) return;
        try {
            const key = 'dbSelectedPostId_' + basketId;
            if (postId == null) localStorage.removeItem(key);
            else localStorage.setItem(key, String(postId));
        } catch (_) { /* storage disabled — fine */ }
    }

    function readPersistedSelectedPostId(basketId) {
        if (!basketId) return null;
        try {
            const v = parseInt(localStorage.getItem('dbSelectedPostId_' + basketId), 10);
            return Number.isFinite(v) ? v : null;
        } catch (_) { return null; }
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

        // Head — title + close button ("Mbyll detajin" — deselects without deleting).
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

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'db-sheet-close';
        closeBtn.textContent = 'Mbyll detajin';
        closeBtn.title = 'Mbyll (nuk fshin postin)';
        closeBtn.addEventListener('click', () => {
            state.selectedPostId = null;
            document.querySelectorAll('.db-post.selected').forEach(c => c.classList.remove('selected'));
            renderSheet(null);
        });
        head.appendChild(closeBtn);

        sheet.appendChild(head);

        // Stage bar — 5 clickable stage columns. Forward/back one step at a
        // time (API enforces the same rule). `done` state is fast-jump-back,
        // `future` is fast-forward — both go through the existing `transition`
        // helper so guards (caption required, etc.) still fire.
        const track = document.createElement('div');
        track.className = 'db-track';
        STAGE_ORDER.forEach((s, i) => {
            const step = document.createElement('div');
            const cls = i < currentIdx ? 'done' : (i === currentIdx ? 'current' : 'todo');
            step.className = 'db-track-step ' + cls;
            step.tabIndex = 0;
            step.title = 'Kalo te: ' + STAGE_LABELS[s];
            const delta = i - currentIdx;
            if (delta === 0) {
                step.style.cursor = 'default';
            } else {
                step.style.cursor = 'pointer';
                step.addEventListener('click', () => {
                    if (Math.abs(delta) !== 1) {
                        showError('Vetëm një hap në një kah — kalo nëpër stage-t një nga një.');
                        return;
                    }
                    transition(post, s);
                });
                step.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); step.click(); }
                });
            }
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

        col1.appendChild(labeledField('Produktet', buildDetailProductsEditor(post)));

        col1.appendChild(buildReferenceUrlBlock(post));

        col1.appendChild(labeledField('Reference notes', inlineTextarea({
            value: post.reference_notes,
            rows: 3,
            placeholder: 'Mood, shënime shtesë…',
            save: (v) => savePostField(post, { reference_notes: v }),
        })));

        // Shporta v2 fields (#1321): structured context that used to live
        // loose in reference_notes. Kept in Burimi because they describe
        // the real-world setup of the shoot/capture.
        col1.appendChild(labeledField('Lokacioni', inlineInput({
            value: post.lokacioni,
            placeholder: 'P.sh. Dyqani Tirana Center',
            save: (v) => savePostField(post, { lokacioni: v }),
        })));

        col1.appendChild(labeledField('Modelet', inlineInput({
            value: post.modelet,
            placeholder: 'P.sh. Era, Bora',
            save: (v) => savePostField(post, { modelet: v }),
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

        col2.appendChild(labeledField('Audienca', inlineInput({
            value: post.audienca,
            placeholder: 'P.sh. gra 25-34, urbane',
            save: (v) => savePostField(post, { audienca: v }),
        })));

        body.appendChild(col2);

        // ── Col 3: Kontenti (media + caption + hashtags) ──
        const col3 = colGroup('✍️', 'Kontenti');

        col3.appendChild(labeledField(mediaLabelFor(post.post_type), buildMediaWidget(post)));

        col3.appendChild(labeledField('Caption', captionWithPolish(post)));

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
            // Re-render the grid so the thumbnail appears on the post card
            // immediately — without this the card keeps showing "Pa material
            // ende" until the next selectDay / day switch.
            if (state.kanban) renderBoard(state.kanban);
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

    // Editable product chips for the detail view — list current products as
    // chips with ×, plus a "+ Shto" button that opens the picker popover
    // (openPlanProductPicker, reused from the previous plan view).
    function buildDetailProductsEditor(post) {
        const wrap = document.createElement('div');
        wrap.className = 'db-plan-chips';
        wrap.style.marginTop = '4px';

        (post.products || []).forEach(p => wrap.appendChild(buildProductChip(post, p)));

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'db-plan-chip-add';
        addBtn.textContent = '+ Shto nga shporta';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openPlanProductPicker(post, addBtn);
        });
        wrap.appendChild(addBtn);

        return wrap;
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
            const label = STAGE_LABELS[targetStage] || targetStage;
            showSuccess('U zhvendos te ' + label);
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

    // ─── Studio editor modal (iframe) ────────────────────────────────
    // Hosts /marketing/studio/{brief}?embedded=1 inside the daily-basket
    // page so content edits (Canva, CapCut, AI caption) never force a
    // full navigation. Auto-creates a creative_brief row on first open so
    // the iframe URL is always valid (#1247).

    const studioModalState = { open: false, postId: null };

    async function openStudioModal(post) {
        if (studioModalState.open) return;
        studioModalState.open = true;
        studioModalState.postId = num(post.id);

        let briefId = num(post.creative_brief_id || 0);
        if (!briefId) {
            try {
                const created = await apiPost('/marketing/api/creative-briefs', {
                    daily_basket_post_id: studioModalState.postId,
                    post_type: post.post_type,
                });
                briefId = num(created && created.creative_brief && created.creative_brief.id);
            } catch (e) {
                studioModalState.open = false;
                throw e;
            }
        }

        if (!briefId) {
            studioModalState.open = false;
            throw new Error('Brief id mungon pas krijimit.');
        }

        const iframe = document.getElementById('dbStudioModalIframe');
        const openFull = document.getElementById('dbStudioModalOpenFull');
        const title = document.getElementById('dbStudioModalTitle');
        const backdrop = document.getElementById('dbStudioModal');

        const embedUrl = '/marketing/studio/' + briefId + '?embedded=1';
        const fullUrl  = '/marketing/studio/' + briefId;

        iframe.src = embedUrl;
        openFull.href = fullUrl;
        title.textContent = (post.title || 'Post ' + studioModalState.postId) + ' · Brief #' + briefId;

        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
    }

    async function closeStudioModal() {
        if (!studioModalState.open) return;
        studioModalState.open = false;

        const backdrop = document.getElementById('dbStudioModal');
        const iframe = document.getElementById('dbStudioModalIframe');

        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        // Unload the iframe so auto-save timers stop immediately and a
        // reopen shows a fresh load instead of cached state.
        iframe.src = 'about:blank';

        // Refresh the plan grid so any new media / caption reflects the
        // user's work without needing a manual refresh.
        try {
            await selectDay(state.selectedDate);
        } catch (_) { /* non-fatal */ }
    }

    function wireStudioModal() {
        document.getElementById('dbStudioModalClose').addEventListener('click', () => {
            closeStudioModal();
        });
        document.getElementById('dbStudioModal').addEventListener('click', (e) => {
            if (e.target.id === 'dbStudioModal') closeStudioModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && studioModalState.open) closeStudioModal();
        });
    }

    // ────────────────────────────────────────────────────────────────
    // Direct upload from the pencil button — no modal. Creates a brief
    // if one doesn't exist yet, then pops the native file picker for
    // either image or video depending on post.post_type.
    // ────────────────────────────────────────────────────────────────

    async function triggerDirectUpload(post) {
        const isVideo = ['video', 'reel'].includes(post.post_type);

        let briefId = num(post.creative_brief_id || 0);
        if (!briefId) {
            const created = await apiPost('/marketing/api/creative-briefs', {
                daily_basket_post_id: num(post.id),
                post_type: post.post_type,
            });
            briefId = num(created && created.creative_brief && created.creative_brief.id);
        }
        if (!briefId) throw new Error('Brief id mungon pas krijimit.');

        await new Promise((resolve) => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = isVideo
                ? 'video/mp4,video/quicktime,video/x-m4v,video/webm'
                : 'image/jpeg,image/png,image/webp';
            input.style.display = 'none';
            input.addEventListener('change', async () => {
                const file = input.files && input.files[0];
                if (!file) { resolve(); return; }

                const endpoint = isVideo
                    ? '/marketing/api/creative-briefs/' + briefId + '/upload-video'
                    : '/marketing/api/creative-briefs/' + briefId + '/upload-photo';

                const form = new FormData();
                form.append('file', file);

                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: form,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        showError('Upload dështoi: ' + (data.message || ('HTTP ' + res.status)));
                    } else {
                        await selectDay(state.selectedDate);
                    }
                } catch (e) {
                    showError('Upload dështoi: ' + (e.message || 'rrjeti'));
                } finally {
                    if (input.parentNode) input.parentNode.removeChild(input);
                    resolve();
                }
            });
            document.body.appendChild(input);
            input.click();
        });
    }

    // ────────────────────────────────────────────────────────────────
    // Caption helper — inline textarea + single "Rregullo me AI" button.
    // Sends the creator's rough Albanian text to Sonnet 4.6 which
    // returns a cleaned, grammatically correct version. We only use
    // the cleaned_sq field; per-platform formatting is skipped because
    // staff copies the same cleaned text into IG/FB/TikTok themselves.
    // ────────────────────────────────────────────────────────────────

    function captionWithPolish(post) {
        const wrap = document.createElement('div');
        wrap.className = 'db-caption-wrap';

        const ta = inlineTextarea({
            value: post.caption,
            rows: 4,
            placeholder: 'Shkruaj tekstin e postit…',
            save: (v) => savePostField(post, { caption: v }),
        });
        wrap.appendChild(ta);

        const row = document.createElement('div');
        row.className = 'db-caption-ai-row';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'db-caption-ai-btn';
        btn.textContent = '✨ Përgatit për publikim';
        btn.title = 'AI rregullon gramatikën, shton emoji + strukturë + hashtags';

        const status = document.createElement('span');
        status.className = 'db-caption-ai-status';

        btn.addEventListener('click', async () => {
            const raw = (ta.value || '').trim();
            if (!raw) {
                status.classList.add('err');
                status.textContent = 'Shkruaj fillimisht tekstin.';
                return;
            }
            btn.disabled = true;
            status.classList.remove('err');
            status.textContent = 'Po përgatit…';
            try {
                const data = await apiPost('/marketing/api/ai/polish-caption', {
                    text: raw,
                    mode: 'craft',
                });
                const crafted = (data && data.cleaned_sq) ? data.cleaned_sq : raw;
                ta.value = crafted;
                await savePostField(post, { caption: crafted });
                status.textContent = '✓ Gati për publikim';
            } catch (e) {
                status.classList.add('err');
                status.textContent = 'AI dështoi: ' + (e.message || '');
            } finally {
                btn.disabled = false;
            }
        });

        row.appendChild(btn);
        row.appendChild(status);
        wrap.appendChild(row);

        return wrap;
    }


    function wireModalOnce() {
        document.getElementById('dbBtnNewPost').addEventListener('click', openNewPostModal);
        document.getElementById('dbModalClose').addEventListener('click', closeNewPostModal);
        document.getElementById('dbModalCancel').addEventListener('click', closeNewPostModal);
        document.getElementById('dbModalSubmit').addEventListener('click', submitModal);
        wireStudioModal();

        // Rail filter — re-renders immediately; doesn't refetch coverage.
        document.getElementById('dbRailFilter').addEventListener('change', (e) => {
            railState.filter = e.target.value;
            if (state.coverage) renderRail(state.coverage);
        });

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

        // Keyboard shortcuts:
        //   Esc — close create-post modal → else close detail sheet → else
        //         clear product highlight. Ordered by user expectation:
        //         whatever feels "most foreground" dismisses first.
        //   Arrow keys in rail — jump selection up/down without the mouse.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (document.getElementById('dbModal').classList.contains('open')) {
                    closeNewPostModal();
                    return;
                }
                if (state.selectedPostId != null) {
                    state.selectedPostId = null;
                    persistSelectedPostId(null);
                    document.querySelectorAll('.db-post.selected').forEach(c => c.classList.remove('selected'));
                    renderSheet(null);
                    return;
                }
                if (railState.highlightedProductId != null) {
                    toggleProductHighlight(railState.highlightedProductId);
                    return;
                }
            }

            // ↑/↓ inside the rail moves the highlight. Ignored when focus is
            // in an input so typing in the detail panel isn't hijacked.
            const railEl = document.getElementById('dbRail');
            const focusedInRail = railEl && railEl.contains(document.activeElement);
            const inInput = /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName || '');
            if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && focusedInRail && !inInput) {
                e.preventDefault();
                const cards = Array.from(document.querySelectorAll('#dbRailList .db-p-card'));
                if (cards.length === 0) return;
                const currentIdx = cards.findIndex(c => num(c.dataset.productId) === railState.highlightedProductId);
                const delta = e.key === 'ArrowDown' ? 1 : -1;
                const nextIdx = currentIdx < 0
                    ? (delta === 1 ? 0 : cards.length - 1)
                    : Math.max(0, Math.min(cards.length - 1, currentIdx + delta));
                const id = num(cards[nextIdx].dataset.productId);
                if (id !== railState.highlightedProductId) {
                    if (railState.highlightedProductId != null) toggleProductHighlight(railState.highlightedProductId); // clear old
                    toggleProductHighlight(id); // set new
                }
                cards[nextIdx].scrollIntoView({ block: 'nearest' });
            }
        });

        // Click outside rail + grid clears any product highlight. Keeps the
        // rail feeling like a "mode" rather than a sticky state.
        document.addEventListener('click', (e) => {
            if (railState.highlightedProductId == null) return;
            const inRail = e.target.closest('#dbRail');
            const inGrid = e.target.closest('#dbGrid .db-post');
            if (!inRail && !inGrid) {
                toggleProductHighlight(railState.highlightedProductId);
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
