# Ads Cockpit — PR 1: Filters + Campaigns Polish

**Status:** Design approved 2026-05-01
**Implementation target:** ~5 dev tasks, single feature branch
**Affects:** `/marketing/analytics/ads` (and optionally `/instagram`, `/facebook`, `/tiktok`)

## Context

Marketing operations team uses `/marketing/analytics/ads` to monitor Facebook & Instagram ad performance pulled live from Meta Graph API v24. Today the page shows KPI cards, charts, and a campaigns table that mixes ACTIVE / PAUSED / ARCHIVED campaigns indiscriminately, with no way to filter or search. The team wants to start a "Decision Cockpit" — read-only but smarter analytics — incrementally. PR 1 ships filters + design polish; PR 2-4 build performance tiers, anomaly alerts, daily digest, and burn-rate.

This spec covers PR 1 only. PR 2-4 are referenced for context but designed separately.

## Goals

1. Let the operator narrow the campaigns table to only the campaigns that need attention (Active by default).
2. Make the ads page visually coherent — fix the placeholder subtitle smell, the cramped KPI strip, the oversized donut legends, and the dense campaigns table.
3. Surface filtered totals so the operator can answer "how much did I spend on Active campaigns this month?" without leaving the page.
4. Persist filter state in URL so refresh and shareable links work.

## Non-Goals (deferred to later PRs)

- Performance Tiers chips (Heroes / Watchlist / Underperformers) — **PR 2**
- Status summary chips that replace pills with a single combined widget — **PR 2**
- Anomaly Alerts banner — **PR 3**
- Period Comparison view — **PR 3**
- Daily Digest Email — **PR 4**
- Budget Burn-Rate widget — **PR 4**

## Design

### Section 1 — Filter Bar (Status + Search)

Filters live **inside the Campaigns Performance card** (not in the global filter card at the top of the page). Date and platform filters are global (they recompute KPIs and charts); status and search apply only to the campaigns table.

Markup added between the card header and the `<thead>`:

```
┌─ Campaigns Performance ─────────────── [chevron expand/collapse toolbar] ─┐
│  [All · Active 12 · Paused 8 · Archived 4]   [🔍 Kërko campaign...___]   │
│  Po shfaqen 12 nga 24 campaign-e                                          │
├──────────────────────────────────────────────────────────────────────────┤
│  thead → rows...                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

**Status pills** — single-select segmented control with 4 buttons. Counts (`Active 12`) come from the unfiltered campaign list and update only when the data is refetched (date or platform changes), not on every keystroke. Active pill highlighted with `bg-primary-600 text-white`; others `border border-slate-200 text-slate-600`. Default selected: **Active**.

**Search input** — single text input, placeholder "Kërko campaign…", debounce 200ms, substring match against `name` (case-insensitive). Clear button (`×`) on the right when input has content.

**Result count** — single line below the controls: "Po shfaqen X nga Y campaign-e". Updates instantly as filters change.

**URL persistence** — both filters reflect in query string: `?status=ACTIVE&q=tof`. On page load, parse query → set initial state. On filter change, replace state (no history pollution: use `history.replaceState` not `pushState`).

### Section 2 — Visual Polish

| # | Symptom | Fix |
|---|---------|-----|
| 1 | Each KPI card has subtitle `"Metrikë e re — pa informacion"` (placeholder text) | Remove the subtitle entirely. The slot is reserved for "delta vs previous period" in PR 3. |
| 2 | KPI strip cramps 8 cards into one row; large numbers feel squeezed | Responsive grid: 4 columns desktop (`md:grid-cols-4`), 2 tablet (`grid-cols-2`), 1 mobile with horizontal scroll fallback. Bump value font from `text-2xl` to `text-3xl`. Label stays `text-[11px] uppercase tracking-wider`. |
| 3 | Donut chart legends consume ~50% of the chart area | Move Chart.js `legend.position` from `'right'` to `'bottom'`. Legend gets a 2-column flex layout (`max-width: 100%; flex-wrap: wrap`). Chart canvas height grows from 260px to 280px to compensate. |
| 4 | Campaigns table has 13 columns, 11px font, no sticky first column when scrolling horizontally | (a) `Campaign` column becomes sticky: `position: sticky; left: 0; z-index: 5; box-shadow: 4px 0 6px -4px rgba(0,0,0,0.1)`. (b) Body cell font 11px → 12px. (c) Vertical padding 12px → 14px. (d) Numeric cells get `font-variant-numeric: tabular-nums` so digits align. |
| 5 | Rows are expandable but no chevron indicator next to campaign name; user has to discover it via cursor change | Add `<iconify-icon icon="heroicons-outline:chevron-right">` immediately before the campaign name. Class `expand-icon`; rotates 90° via existing `.rotated` class when row is expanded. Rows without ad sets get a 16px spacer (no chevron) for alignment. The "Expand All / Collapse All" buttons move from the card header to a small icon-button group inside the new filter bar (right-aligned). |

### Section 3 — Filtered Totals Footer

Add `<tfoot>` to `#campaignsTable`. Single row, sticky at bottom of the visible table area:

```
                   SPEND     IMPR     CLICKS  PURCH   CONV.VAL   ROAS
Filtered Total:    €2.405    1.2M     14.3k   192     €5.823     2.42x
```

Computed client-side from the currently visible (post-filter) rows. ROAS = sum(conv_value) / sum(spend); CTR and CPC omitted (averaging averages is misleading — keep the row to absolute totals only). Style: `bg-slate-50/80 font-semibold border-t-2 border-slate-200`. The label "Filtered Total" sits in the sticky `Campaign` column.

### Section 4 — Cross-page Polish

The 3 visual polish items (KPI subtitle removal #1, KPI strip responsive grid #2, donut legend reposition #3) apply to every analytics page that has the same patterns. The filter bar + sticky column + tfoot apply only where they make sense:

| Page | #1 KPI subtitle | #2 KPI grid | #3 donut legend | #4 sticky col | #5 chevron | Filters + tfoot |
|------|------------------|--------------|-------------------|----------------|-------------|-------------------|
| `ads.blade.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (full PR 1) |
| `tiktok.blade.php` | ✅ | ✅ | ✅ | ✅ | ❌ (no expand) | ❌ (different status vocabulary; deferred — see below) |
| `instagram.blade.php` | ✅ | ✅ | ✅ | n/a | n/a | ❌ (post-level, no campaigns) |
| `facebook.blade.php` | ✅ | ✅ | ✅ | n/a | n/a | ❌ (same) |
| `total.blade.php` | ✅ | ✅ | n/a | n/a | n/a | ❌ |

**Why TikTok is partial:** the TikTok renderCampaigns code at [tiktok.blade.php:742](resources/views/meta-marketing/tiktok.blade.php:742) checks `c.status === 'ENABLE' || c.status === 'ACTIVE'` — the TikTok Marketing API returns `ENABLE/DISABLE/DELETE` (operation_status), not Meta's `ACTIVE/PAUSED/ARCHIVED`. Status normalization to a unified vocabulary is a small but separate task; folding it into PR 1 widens scope. TikTok gets visual polish only; filters are queued for a follow-up (mini-PR 1.5 or rolled into PR 2 with the tiers work).

This keeps the visual language consistent without scope-creeping into per-page feature parity.

### Section 5 — URL State Encoding

Query-string keys (lowercase):

| Key | Values | Default |
|-----|--------|---------|
| `status` | `ACTIVE`, `PAUSED`, `ARCHIVED`, `ALL` | `ACTIVE` |
| `q` | URL-encoded substring | empty |

Read on page load, write on each filter change via `history.replaceState`. Existing global filters (date preset, platform) already use a different pattern in this codebase; PR 1 does not unify them — that's a future cleanup.

## Architecture

### Files affected

| File | Type of change | Approx. LoC delta |
|------|----------------|-------------------|
| `resources/views/meta-marketing/ads.blade.php` | UI markup + JS (filter state, URL sync, render filtering, totals computation, expand chevrons) + polish #1-5 | +180 / -40 |
| `resources/views/meta-marketing/tiktok.blade.php` | Polish #1, #2, #3, #4 (sticky Campaign col). NO filters, NO chevron, NO tfoot in PR 1. | +30 / -20 |
| `resources/views/meta-marketing/instagram.blade.php` | Polish only (#1, #2, #3) | +20 / -15 |
| `resources/views/meta-marketing/facebook.blade.php` | Polish only (#1, #2, #3) | +20 / -15 |
| `resources/views/meta-marketing/total.blade.php` | Polish only (#1, #2) | +15 / -10 |

No controller changes. No service changes. No new endpoints. The campaigns API already returns `status` for every campaign / adset / ad. Single Blade + JS change.

### Data flow

```
Meta Graph API v24
    ↓
MetaMarketingV2ChannelService::adsCampaigns()  [unchanged]
    ↓
GET /marketing/analytics/api/ads-campaigns      [unchanged endpoint]
    ↓
JS: campaignsData[] = [...{name, status, spend, ...}]
    ↓
NEW: filterAndRender()
       ├── apply status filter (skip if status === 'ALL')
       ├── apply search filter (skip if q === '')
       ├── apply existing sort
       ├── render rows
       └── compute + render <tfoot> totals
```

The filter pipeline lives entirely client-side. `campaignsData[]` (already in memory after fetch) is the source; filter is a `.filter()` call before the existing render loop.

### Filter state machine

```
state = {
  status: 'ACTIVE' | 'PAUSED' | 'ARCHIVED' | 'ALL',
  q: string,
  sortField: string,    // existing
  sortAsc: boolean,     // existing
}
```

State changes:
1. Page load → read URL → init state → render
2. Status pill click → `state.status = X` → write URL → render
3. Search input (debounced) → `state.q = X` → write URL → render
4. Sort header click → existing logic, but goes through `filterAndRender()` instead of `renderCampaigns()`
5. Date/platform change (parent fetch) → re-fetch → recompute pill counts → render

## Edge Cases

- **Empty result after filter** — render a single placeholder row: "Asnjë campaign nuk përputhet me filtrin." with a clear-filters link.
- **Search matches an ad set or ad name, not a campaign** — out of scope for PR 1; only campaign names are matched. We can extend in PR 2 if needed.
- **`status` value not in {ACTIVE, PAUSED, ARCHIVED}** (e.g., `DELETED`, `WITH_ISSUES`) — group all unknown values under "Archived" pill. Document this in code comment.
- **URL has invalid query params** (e.g., `?status=banana`) — fall through to default (`ACTIVE`).
- **No campaigns at all returned from API** — existing "Duke ngarkuar..." / empty state stays as-is. Filter bar still renders but counts show 0.
- **Sticky column on small screens** — when viewport < `md`, drop sticky behavior (the table becomes horizontally scrollable as a whole instead).

## Testing Strategy

Manual E2E checklist (no automated tests added in PR 1 — the page has none currently and adding a test harness is its own scope):

1. Load `/marketing/analytics/ads` → "Active" pill is active, table shows only ACTIVE campaigns, count line shows "Po shfaqen N nga M".
2. Click "All" → table shows everything, count updates.
3. Type "tof" in search → table filters live, count updates.
4. Click clear (`×`) → search empties, table re-renders.
5. Refresh page with `?status=PAUSED&q=sales` in URL → state restored.
6. Change date preset → fetch fires, pill counts update, current filter preserved.
7. Sort by Spend → totals row stays correct (spend totals don't depend on sort order, but verify visually).
8. Resize to tablet → KPI grid reflows to 2 cols.
9. Resize to mobile → KPI grid scrolls horizontally; sticky Campaign column drops.
10. Expand a campaign with ad sets → chevron rotates; ad sets render below; collapse rotates back.

## Risks and Open Questions

- **Pill counts depend on unfiltered data** — if a user expects them to reflect search results too, that's a UX question. Decision: pills represent the *whole* dataset's status distribution; search narrows further within. Document in tooltip if needed.
- **Filtered totals on a page where users expect "global" KPI cards** — the KPI cards at the top stay global (all campaigns); the `<tfoot>` shows filtered. Visual separation (different card vs table footer) should make this obvious. If users get confused, add a label like "Të gjitha" above the KPI strip in PR 2.
- **TikTok status vocabulary differs** (resolved): TikTok returns `ENABLE/DISABLE/DELETE` instead of Meta's `ACTIVE/PAUSED/ARCHIVED`. PR 1 scope reduced for TikTok to visual polish + sticky column only. Filter+tfoot for TikTok will be added in a small follow-up that normalizes the vocabulary in `MetaMarketingV2ChannelService::tiktokCampaigns` (or in JS) before applying the same component.

## Out of Scope Reminders

- No backend changes.
- No automated tests.
- No new Meta API calls.
- No write-side actions (pause, edit budget, etc.) — those start in Vision B (PR 5+).
- No AI features.
- No email / Slack notifications.
- No saved filter presets (could add later if useful).
