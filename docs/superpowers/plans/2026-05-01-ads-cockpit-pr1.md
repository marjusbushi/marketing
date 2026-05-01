# Ads Cockpit PR 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Status + Search filters (default Active), filtered totals footer, and 5 visual polish items on `/marketing/analytics/ads` only. Backend untouched.

**Architecture:** Single-file change to `resources/views/meta-marketing/ads.blade.php`. All filtering is client-side over the existing `campaignsData[]` array. URL state via `history.replaceState`. The existing render-via-template-string pattern is preserved (campaign names are already escaped through `escHtml()` for the only user-controlled field; data comes from a trusted internal endpoint).

**Tech Stack:** Blade + vanilla JS + Tailwind utilities + Chart.js v4 (already on the page).

**Spec:** [docs/superpowers/specs/2026-05-01-ads-cockpit-pr1-design.md](../specs/2026-05-01-ads-cockpit-pr1-design.md)

---

## File Structure

**Single file affected:**

| Path | Responsibility |
|------|----------------|
| `resources/views/meta-marketing/ads.blade.php` | Ads Report page (UI + page-local JS) |

No other files. No controller / service / endpoint changes. No npm install. No migration. No config changes.

---

## Pre-flight

```bash
php artisan serve --port=8002 &
npm run dev &
```

Open `http://localhost:8002/marketing/analytics/ads` in a browser tab kept side-by-side. Tail the log to catch any boot-time failures:

```bash
tail -f storage/logs/laravel.log
```

---

## Task 1: Polish #1 — Hide empty "Metrikë e re" subtitle

**File:** `resources/views/meta-marketing/ads.blade.php` lines 570-585 inside `loadKPIs()`.

- [ ] **Step 1: Read the current `loadKPIs` change-text branch**

Use Read to confirm the exact lines. The block sets `changeEl.textContent = 'Metrikë e re — pa informacion'` when `info.change` is null/undefined.

- [ ] **Step 2: Replace the placeholder text with empty string**

Use Edit. In the branch `if (info.change === null || info.change === undefined)`:

- old_string: the line `changeEl.textContent = 'Metrikë e re — pa informacion';`
- new_string: `changeEl.textContent = '';` followed by a one-line comment: `// PR 1: empty until PR 3 fills with delta vs previous period`

Leave the className line and the rest of the if/else chain untouched.

- [ ] **Step 3: Verify in browser**

Reload `/marketing/analytics/ads`. KPI cards show only icon + label + value — no grey subtitle line. The empty change `<div>` still occupies vertical space (intentional — PR 3 fills it).

- [ ] **Step 4: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
ui(ads): hide empty 'Metrikë e re — pa informacion' subtitle on KPI cards

The placeholder fired whenever the change delta API field was null,
which is every metric in PR 1. Reserve the slot (do not remove the DOM
node) so PR 3 can populate real period-over-period deltas without
needing layout changes.
EOF
)"
```

---

## Task 2: Polish #2 — Cap KPI grid at 4 cols + bump value font

**File:** `resources/views/meta-marketing/ads.blade.php` lines 117 (grid container) and 137 (value div).

- [ ] **Step 1: Edit the KPI grid container classes**

- old_string: `<div id="kpiCards" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-8 gap-3">`
- new_string: `<div id="kpiCards" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">`

Removes the `xl:grid-cols-8` step and switches mobile from 2 cols to 1 so values like `€10.081,69` don't truncate.

- [ ] **Step 2: Bump value font from text-xl to text-2xl**

Use Edit with `replace_all: true` on this exact substring inside the @foreach:

- old_string: `class="text-xl font-bold text-slate-900 tabular-nums"`
- new_string: `class="text-2xl font-bold text-slate-900 tabular-nums"`

(There is one occurrence inside the @foreach loop; `replace_all` is defensive.)

- [ ] **Step 3: Verify**

Reload. KPI cards reflow:
- Desktop ≥768px → 4 cols × 2 rows.
- Tablet 640-767px → 2 cols × 4 rows.
- Mobile <640px → 1 col × 8 rows.
- Values feel comfortable (no longer wrapping mid-number).

- [ ] **Step 4: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
ui(ads): cap KPI strip at 4 cols, bump value font to text-2xl

xl:grid-cols-8 squeezed wide values like €10.081,69 into ~140px columns.
Capping at 4 cols × 2 rows and bumping the font lets each KPI breathe.
EOF
)"
```

---

## Task 3: Polish #3 — Donut legend to bottom

**File:** `resources/views/meta-marketing/ads.blade.php` line 869 (`createBreakdownChart` options) + lines 207, 220 (canvas heights).

- [ ] **Step 1: Update Chart.js legend position**

Use Edit. The current line 869:

- old_string: `legend: { position: 'right', labels: { font: { size: 11 }, padding: 8 } },`
- new_string:
  ```
  legend: {
                          position: 'bottom',
                          labels: { font: { size: 11 }, padding: 10, boxWidth: 12, boxHeight: 12 },
                      },
  ```

Match the indentation of the surrounding `plugins:` block (24 spaces leading the `legend:` key).

- [ ] **Step 2: Bump both donut canvas heights from 260px to 320px**

There are two occurrences of `<div class="relative w-full h-[260px]">` directly preceding `<canvas id="platformChart">` and `<canvas id="placementChart">` (lines 207 and 220). Use Edit with `replace_all: true`:

- old_string: `class="relative w-full h-[260px]">
                    <canvas id="platformChart"`
- new_string: `class="relative w-full h-[260px]">
                    <canvas id="platformChart"`

Wait — that's a no-op. Instead, do two separate Edits, one per chart, or use a unique enough fragment. Use:

For platformChart:
- old_string: `<div class="relative w-full h-[260px]">
                    <canvas id="platformChart"></canvas>`
- new_string: `<div class="relative w-full h-[320px]">
                    <canvas id="platformChart"></canvas>`

For placementChart:
- old_string: `<div class="relative w-full h-[260px]">
                    <canvas id="placementChart"></canvas>`
- new_string: `<div class="relative w-full h-[320px]">
                    <canvas id="placementChart"></canvas>`

The other `h-[260px]` heights elsewhere on the page (age/gender breakdown bar charts) stay at 260px because they don't have a relocated legend.

- [ ] **Step 3: Verify**

Reload. "Spend by Platform" and "Spend by Placement" donuts show:
- Donut centered at the top of the card body.
- Multi-row legend below with square-ish color swatches.
- Tooltips on hover still show `${label}: ${€spend} (${pct}%)` (separate plugin, untouched).

- [ ] **Step 4: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
ui(ads): move donut chart legends to bottom, bump canvas height

Right-side legend ate ~50% of the card width, leaving the donut as a
tiny circle. Bottom legend lets the chart breathe. Canvas height
260→320px to fit the legend without clipping.
EOF
)"
```

---

## Task 4: Filter bar markup (HTML only, no JS yet)

**File:** `resources/views/meta-marketing/ads.blade.php` lines 228-247 (Campaigns card header).

- [ ] **Step 1: Replace the existing card header block**

The current header has the title on the left and Expand/Collapse buttons on the right. Replace it with a title-only header and a new filter bar row beneath it.

Use Edit. The unique opening fragment is the comment `{{-- Campaign Table --}}`. Identify the entire header `<div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">` … `</div>` block and replace.

- old_string (the existing 14-ish lines from `<div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">` to the closing `</div>` of that block, including the two buttons):

```html
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <iconify-icon icon="heroicons-outline:megaphone" width="18" class="text-slate-400"></iconify-icon>
                <h3 class="text-sm font-semibold text-slate-800">Campaigns Performance</h3>
            </div>
            <div class="flex gap-2">
                <button onclick="expandAll()" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:chevron-double-down" width="14"></iconify-icon> Expand All
                </button>
                <button onclick="collapseAll()" class="inline-flex items-center gap-1 h-[30px] px-2.5 text-xs font-medium rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:chevron-double-up" width="14"></iconify-icon> Collapse All
                </button>
            </div>
        </div>
```

- new_string:

```html
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:megaphone" width="18" class="text-slate-400"></iconify-icon>
            <h3 class="text-sm font-semibold text-slate-800">Campaigns Performance</h3>
        </div>

        {{-- Filter Bar --}}
        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/40 flex flex-wrap items-center gap-3">
            <div id="statusPills" class="inline-flex rounded-md border border-slate-200 bg-white overflow-hidden text-xs">
                <button data-status="ALL" class="status-pill px-3 py-1.5 font-medium border-r border-slate-200 hover:bg-slate-50 transition-colors">
                    All <span class="ml-1" data-pill-count="ALL">0</span>
                </button>
                <button data-status="ACTIVE" class="status-pill px-3 py-1.5 font-medium border-r border-slate-200 hover:bg-slate-50 transition-colors">
                    Active <span class="ml-1" data-pill-count="ACTIVE">0</span>
                </button>
                <button data-status="PAUSED" class="status-pill px-3 py-1.5 font-medium border-r border-slate-200 hover:bg-slate-50 transition-colors">
                    Paused <span class="ml-1" data-pill-count="PAUSED">0</span>
                </button>
                <button data-status="ARCHIVED" class="status-pill px-3 py-1.5 font-medium hover:bg-slate-50 transition-colors">
                    Archived <span class="ml-1" data-pill-count="ARCHIVED">0</span>
                </button>
            </div>

            <div class="relative flex-1 min-w-[220px] max-w-[320px]">
                <iconify-icon icon="heroicons-outline:magnifying-glass" width="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></iconify-icon>
                <input id="campaignSearch" type="text" placeholder="Kërko campaign..." class="w-full h-[30px] pl-8 pr-8 text-xs rounded-md border border-slate-200 bg-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none" autocomplete="off" />
                <button id="campaignSearchClear" type="button" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <iconify-icon icon="heroicons-outline:x-mark" width="14"></iconify-icon>
                </button>
            </div>

            <div class="ml-auto flex gap-1.5">
                <button onclick="expandAll()" title="Expand all campaigns" class="inline-flex items-center justify-center h-[30px] w-[30px] rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:chevron-double-down" width="14"></iconify-icon>
                </button>
                <button onclick="collapseAll()" title="Collapse all campaigns" class="inline-flex items-center justify-center h-[30px] w-[30px] rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <iconify-icon icon="heroicons-outline:chevron-double-up" width="14"></iconify-icon>
                </button>
            </div>

            <div class="basis-full text-[11px] text-slate-500">
                Po shfaqen <span id="campaignVisibleCount">0</span> nga <span id="campaignTotalCount">0</span> campaign-e
            </div>
        </div>
```

- [ ] **Step 2: Verify**

Reload. Above the campaigns table you should see:
- Pills row: `All 0 | Active 0 | Paused 0 | Archived 0` (counts are 0 — JS not wired yet).
- Search input with placeholder.
- Right side: 2 small icon-only buttons (expand/collapse).
- Below: "Po shfaqen 0 nga 0 campaign-e".
- Card header is now title-only.
- Pills do nothing yet. Search does nothing yet. Expand/collapse still work (the JS handlers `expandAll()` / `collapseAll()` already exist).

- [ ] **Step 3: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
ui(ads): scaffold filter bar markup above campaigns table

Status pills (All/Active/Paused/Archived) with count slots, search
input with clear button, and Expand/Collapse moved to the right of the
bar as icon-only buttons. JS wiring lands in the next commit.
EOF
)"
```

---

## Task 5: Filter state, URL sync, and filtering inside renderCampaigns

**File:** `resources/views/meta-marketing/ads.blade.php` — JS inside the `@section('scripts')` block.

This task adds state vars, helper functions, and modifies `renderCampaigns` and `loadCampaigns` to flow through filtering. The render template (the row HTML inside the loop) is **unchanged**.

- [ ] **Step 1: Add 3 state vars next to the existing `campaignsData/sortField/sortAsc` declarations**

Use Edit. Find the block at lines 308-310:

- old_string:
  ```
      let campaignsData = [];
      let sortField = 'spend';
      let sortAsc = false;
  ```

- new_string:
  ```
      let campaignsData = [];
      let sortField = 'spend';
      let sortAsc = false;
      let filterStatus = 'ACTIVE';   // ALL | ACTIVE | PAUSED | ARCHIVED
      let filterQ = '';              // case-insensitive substring on campaign name
      let searchDebounceTimer = null;
  ```

- [ ] **Step 2: Add helper functions immediately after the new state vars**

Use Edit. Append a block right after the line `let searchDebounceTimer = null;`. Place these helpers as a new block. Match the surrounding 4-space indentation. Keep them grouped together for readability:

```javascript

    // === Filter helpers ============================================
    const VALID_STATUSES = ['ALL', 'ACTIVE', 'PAUSED', 'ARCHIVED'];

    function readFiltersFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const s = (params.get('status') || '').toUpperCase();
        if (VALID_STATUSES.includes(s)) filterStatus = s;
        filterQ = params.get('q') || '';
    }

    function writeFiltersToUrl() {
        const params = new URLSearchParams(window.location.search);
        if (filterStatus === 'ACTIVE') params.delete('status'); else params.set('status', filterStatus);
        if (!filterQ) params.delete('q'); else params.set('q', filterQ);
        const qs = params.toString();
        const url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
        window.history.replaceState(null, '', url);
    }

    // Map raw campaign status to one of the filterable buckets. Anything
    // that isn't ACTIVE or PAUSED falls into ARCHIVED so unknown values
    // (DELETED, WITH_ISSUES, etc.) stay reachable via that pill.
    function normalizeStatus(raw) {
        const s = (raw || '').toUpperCase();
        if (s === 'ACTIVE') return 'ACTIVE';
        if (s === 'PAUSED') return 'PAUSED';
        return 'ARCHIVED';
    }

    function getFilteredCampaigns() {
        const q = filterQ.toLowerCase();
        return campaignsData.filter(c => {
            if (filterStatus !== 'ALL' && normalizeStatus(c.status) !== filterStatus) return false;
            if (q && !(c.name || '').toLowerCase().includes(q)) return false;
            return true;
        });
    }

    function refreshPillCounts() {
        const counts = { ALL: campaignsData.length, ACTIVE: 0, PAUSED: 0, ARCHIVED: 0 };
        campaignsData.forEach(c => counts[normalizeStatus(c.status)]++);
        document.querySelectorAll('[data-pill-count]').forEach(el => {
            el.textContent = counts[el.getAttribute('data-pill-count')] ?? 0;
        });
    }

    function refreshPillActiveStyle() {
        document.querySelectorAll('.status-pill').forEach(btn => {
            const isActive = btn.getAttribute('data-status') === filterStatus;
            btn.classList.toggle('bg-primary-600', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('text-slate-700', !isActive);
        });
    }

    function refreshSearchClearButton() {
        const clear = document.getElementById('campaignSearchClear');
        if (!clear) return;
        clear.classList.toggle('hidden', !filterQ);
    }

    function clearAllFilters() {
        filterStatus = 'ACTIVE';
        filterQ = '';
        const input = document.getElementById('campaignSearch');
        if (input) input.value = '';
        writeFiltersToUrl();
        refreshPillActiveStyle();
        refreshSearchClearButton();
        renderCampaigns();
    }
```

- [ ] **Step 3: Modify `renderCampaigns` to filter before sort and update count line**

Use Edit on the existing function. Two surgical edits:

**Edit 3a:** at the very start of `renderCampaigns`, after `const tbody = document.getElementById('campaignTableBody');`, add lines that read the count elements and set the total. Find:

- old_string: `const tbody = document.getElementById('campaignTableBody');`
- new_string:
  ```
  const tbody = document.getElementById('campaignTableBody');
          const totalCountEl = document.getElementById('campaignTotalCount');
          const visibleCountEl = document.getElementById('campaignVisibleCount');
          if (totalCountEl) totalCountEl.textContent = campaignsData.length;
  ```

**Edit 3b:** replace the empty-data branch and the sort source. Find the block:

- old_string:
  ```
          if (!campaignsData.length) {
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nuk ka të dhëna për këtë periudhë</td></tr>';
              return;
          }

          const sorted = [...campaignsData].sort((a, b) => {
  ```
- new_string:
  ```
          if (!campaignsData.length) {
              if (visibleCountEl) visibleCountEl.textContent = 0;
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nuk ka të dhëna për këtë periudhë</td></tr>';
              return;
          }

          const filtered = getFilteredCampaigns();
          if (visibleCountEl) visibleCountEl.textContent = filtered.length;

          if (!filtered.length) {
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Asnjë campaign nuk përputhet me filtrin. <button onclick="clearAllFilters()" class="ml-2 text-primary-600 hover:underline font-medium">Pastro filtrat</button></td></tr>';
              return;
          }

          const sorted = [...filtered].sort((a, b) => {
  ```

This swaps the sort source from `campaignsData` to the filtered array, adds the empty-filter state, and updates the visible count.

- [ ] **Step 4: Make `loadCampaigns` refresh pill counts after fetch**

Use Edit. Find:

- old_string:
  ```
      async function loadCampaigns(from, to, platform, extra = {}, gen = null) {
          const { data } = await fetchApi('ads-campaigns', { from, to, platform, ...extra });
          if (gen !== null && gen !== loadGeneration) return;
          campaignsData = data;
          renderCampaigns();
      }
  ```
- new_string:
  ```
      async function loadCampaigns(from, to, platform, extra = {}, gen = null) {
          const { data } = await fetchApi('ads-campaigns', { from, to, platform, ...extra });
          if (gen !== null && gen !== loadGeneration) return;
          campaignsData = data;
          refreshPillCounts();
          renderCampaigns();
      }
  ```

- [ ] **Step 5: Wire pill clicks + search input + initial state at end of `<script>`**

Find a good insertion point — just before the closing `</script>` tag in the `@section('scripts')` block. Use Edit to find a unique nearby anchor. Look for the existing `loadAll` invocation or the final closing brace of the IIFE/script.

Append (with leading 4-space indentation for consistency with surrounding):

```javascript

    // === Filter wiring ============================================
    function bindFilterControls() {
        document.querySelectorAll('.status-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                filterStatus = btn.getAttribute('data-status');
                writeFiltersToUrl();
                refreshPillActiveStyle();
                renderCampaigns();
            });
        });

        const input = document.getElementById('campaignSearch');
        const clear = document.getElementById('campaignSearchClear');
        if (input) {
            input.value = filterQ;
            input.addEventListener('input', () => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    filterQ = input.value.trim();
                    writeFiltersToUrl();
                    refreshSearchClearButton();
                    renderCampaigns();
                }, 200);
            });
        }
        if (clear) {
            clear.addEventListener('click', () => {
                if (input) input.value = '';
                filterQ = '';
                writeFiltersToUrl();
                refreshSearchClearButton();
                renderCampaigns();
            });
        }

        refreshPillActiveStyle();
        refreshSearchClearButton();
    }

    readFiltersFromUrl();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindFilterControls);
    } else {
        bindFilterControls();
    }
```

- [ ] **Step 6: Verify in browser**

Reload `/marketing/analytics/ads` (clear URL params first).

1. Pills show real counts (e.g. `All 24 | Active 12 | Paused 8 | Archived 4`).
2. The "Active" pill is highlighted.
3. The campaign table shows only Active campaigns (~12 rows).
4. "Po shfaqen 12 nga 24 campaign-e" appears.
5. Click "All" → table fills out; count updates to 24/24.
6. Click "Paused" → only paused; URL shows `?status=PAUSED`.
7. Type "tof" in search → after 200ms, table filters; clear (×) appears.
8. Click clear → input empties, URL drops `q=`.
9. Reload page with `?status=PAUSED&q=sales` → state restored.
10. Empty-filter result shows "Pastro filtrat" link → click it → returns to default Active.

- [ ] **Step 7: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
feat(ads): wire status pills + search filter with URL persistence

Client-side filter over campaignsData[]. Default status=ACTIVE. Search
is a 200ms-debounced substring match on campaign name. State lives in
the query string (?status=PAUSED&q=tof) via history.replaceState so
refresh and shareable URLs both work.

Status normalization buckets unknown values (DELETED, WITH_ISSUES) into
'Archived' so nothing becomes unreachable. Counts on the pills come
from the unfiltered data; the row count line reflects post-filter.
EOF
)"
```

---

## Task 6: Filtered totals footer

**File:** `resources/views/meta-marketing/ads.blade.php` — `<tfoot>` markup + `renderFilteredTotals` JS.

- [ ] **Step 1: Insert `<tfoot>` between the closing `</tbody>` and `</table>`**

Use Edit. The existing `<tbody>` block at lines 271-273:

- old_string:
  ```
                  <tbody id="campaignTableBody">
                      <tr><td colspan="13" class="text-center py-8 text-slate-400">Duke ngarkuar...</td></tr>
                  </tbody>
              </table>
  ```
- new_string:
  ```
                  <tbody id="campaignTableBody">
                      <tr><td colspan="13" class="text-center py-8 text-slate-400">Duke ngarkuar...</td></tr>
                  </tbody>
                  <tfoot id="campaignTotalsFoot" class="hidden bg-slate-50/80 border-t-2 border-slate-200 text-xs">
                      <tr>
                          <td class="text-left px-4 py-3 font-semibold text-slate-700" colspan="3">Filtered Total</td>
                          <td class="text-right px-3 py-3 font-bold text-slate-900 tabular-nums" id="totalSpend">—</td>
                          <td class="text-right px-3 py-3 font-semibold text-slate-700 tabular-nums" id="totalImpressions">—</td>
                          <td class="text-right px-3 py-3 font-semibold text-slate-700 tabular-nums" id="totalReach">—</td>
                          <td class="text-right px-3 py-3 font-semibold text-slate-700 tabular-nums" id="totalClicks">—</td>
                          <td class="text-right px-3 py-3 text-slate-400">—</td>
                          <td class="text-right px-3 py-3 font-semibold text-slate-700 tabular-nums" id="totalPurchases">—</td>
                          <td class="text-right px-3 py-3 font-bold text-slate-900 tabular-nums" id="totalRevenue">—</td>
                          <td class="text-right px-3 py-3 font-bold tabular-nums" id="totalRoas">—</td>
                          <td class="text-right px-3 py-3 text-slate-400">—</td>
                          <td class="text-right px-3 py-3 text-slate-400">—</td>
                      </tr>
                  </tfoot>
              </table>
  ```

CTR (column 8), CPC (column 12), and CPM (column 13) intentionally show `—` (averaging averages misleads).

- [ ] **Step 2: Add `renderFilteredTotals` near the other filter helpers from Task 5**

Use Edit to append below `clearAllFilters` (added in Task 5):

```javascript

    function renderFilteredTotals(filtered) {
        const foot = document.getElementById('campaignTotalsFoot');
        if (!foot) return;
        if (!filtered.length) {
            foot.classList.add('hidden');
            return;
        }
        const sum = filtered.reduce((acc, c) => {
            acc.spend += Number(c.spend) || 0;
            acc.impressions += Number(c.impressions) || 0;
            acc.reach += Number(c.reach) || 0;
            acc.link_clicks += Number(c.link_clicks) || 0;
            acc.purchases += Number(c.purchases) || 0;
            acc.revenue += Number(c.revenue) || 0;
            return acc;
        }, { spend: 0, impressions: 0, reach: 0, link_clicks: 0, purchases: 0, revenue: 0 });

        const roas = sum.spend > 0 ? sum.revenue / sum.spend : 0;
        document.getElementById('totalSpend').textContent = fmtEur(sum.spend);
        document.getElementById('totalImpressions').textContent = fmtNum(sum.impressions);
        document.getElementById('totalReach').textContent = fmtNum(sum.reach);
        document.getElementById('totalClicks').textContent = fmtNum(sum.link_clicks);
        document.getElementById('totalPurchases').textContent = fmtNum(sum.purchases);
        document.getElementById('totalRevenue').textContent = fmtEur(sum.revenue);
        const roasEl = document.getElementById('totalRoas');
        if (roasEl) {
            roasEl.textContent = fmtRoas(roas);
            roasEl.style.color = roasColor(roas);
        }
        foot.classList.remove('hidden');
    }
```

- [ ] **Step 3: Call `renderFilteredTotals` from the 3 branches of `renderCampaigns`**

Three insertion points:

**3a.** Inside the `if (!campaignsData.length)` branch, before the existing `tbody.innerHTML = ...`. Use Edit:

- old_string:
  ```
          if (!campaignsData.length) {
              if (visibleCountEl) visibleCountEl.textContent = 0;
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nuk ka të dhëna për këtë periudhë</td></tr>';
              return;
          }
  ```
- new_string:
  ```
          if (!campaignsData.length) {
              if (visibleCountEl) visibleCountEl.textContent = 0;
              renderFilteredTotals([]);
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nuk ka të dhëna për këtë periudhë</td></tr>';
              return;
          }
  ```

**3b.** Inside the `if (!filtered.length)` branch:

- old_string:
  ```
          if (!filtered.length) {
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Asnjë campaign nuk përputhet me filtrin.
  ```
- new_string:
  ```
          if (!filtered.length) {
              renderFilteredTotals([]);
              tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Asnjë campaign nuk përputhet me filtrin.
  ```

**3c.** In the success path, after `if (visibleCountEl) visibleCountEl.textContent = filtered.length;`:

- old_string:
  ```
          const filtered = getFilteredCampaigns();
          if (visibleCountEl) visibleCountEl.textContent = filtered.length;

          if (!filtered.length) {
  ```
- new_string:
  ```
          const filtered = getFilteredCampaigns();
          if (visibleCountEl) visibleCountEl.textContent = filtered.length;
          renderFilteredTotals(filtered);

          if (!filtered.length) {
  ```

(Note: the call before the empty-state check is fine — `renderFilteredTotals([])` will just hide the foot if the filter returns 0; but `renderFilteredTotals(filtered)` for filtered>0 will show it. Both cases are covered.)

- [ ] **Step 4: Verify**

Reload. A `<tfoot>` row appears below campaign rows showing absolute totals. CTR/CPC/CPM cells stay `—` on purpose. Totals update as you switch pills or type in search. Sort doesn't change totals (sums don't depend on order).

- [ ] **Step 5: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
feat(ads): add filtered totals footer to campaigns table

<tfoot> reduces over the post-filter rows. Spend, Impressions, Reach,
Link Clicks, Purchases, Conv. Value totals are absolute sums; ROAS is
sum(revenue)/sum(spend). CTR, CPC, CPM are intentionally blank — the
average of averages would be misleading without weighting.

Footer hides when the filter produces zero rows so the empty-state
message reads cleanly.
EOF
)"
```

---

## Task 7: Polish #4 — Sticky Campaign column

**File:** `resources/views/meta-marketing/ads.blade.php` — `<style>` block at line 283 + `sticky-col` class on first cell of head, body rows, and tfoot.

- [ ] **Step 1: Append sticky-column CSS to the existing `<style>` block**

Use Edit. The current style block ends with the `.expand-icon.rotated` rule and `</style>`. Find:

- old_string:
  ```
      .expand-icon { transition: transform 0.2s; display: inline-block; }
      .expand-icon.rotated { transform: rotate(90deg); }
  </style>
  ```
- new_string:
  ```
      .expand-icon { transition: transform 0.2s; display: inline-block; }
      .expand-icon.rotated { transform: rotate(90deg); }
      @media (min-width: 768px) {
          #campaignsTable th.sticky-col,
          #campaignsTable td.sticky-col {
              position: sticky;
              left: 0;
              z-index: 5;
              background: #ffffff;
              box-shadow: 4px 0 6px -4px rgba(15, 23, 42, 0.08);
          }
          #campaignsTable thead th.sticky-col,
          #campaignsTable tfoot td.sticky-col,
          #campaignsTable .adset-row td.sticky-col {
              background: #F8FAFC;
          }
          #campaignsTable .campaign-row:hover td.sticky-col {
              background: #FAFAFA;
          }
      }
  </style>
  ```

- [ ] **Step 2: Add `sticky-col` to the Campaign `<th>`**

Use Edit. The current header:

- old_string: `<th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[22%] cursor-pointer" onclick="sortTable('name')">`
- new_string: `<th class="sticky-col text-left px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-[22%] cursor-pointer" onclick="sortTable('name')">`

- [ ] **Step 3: Add `sticky-col` to the campaign-row first cell template inside `renderCampaigns`**

Use Edit on the JS template. Find:

- old_string: `<tr class="campaign-row border-b border-slate-50 hover:bg-slate-50/50" onclick="${hasAdSets ? \`toggleAdSets(${idx})\` : ''}" data-campaign="${idx}">
                    <td class="text-left px-4 py-3">`
- new_string: `<tr class="campaign-row border-b border-slate-50 hover:bg-slate-50/50" onclick="${hasAdSets ? \`toggleAdSets(${idx})\` : ''}" data-campaign="${idx}">
                    <td class="sticky-col text-left px-4 py-3">`

- [ ] **Step 4: Add `sticky-col` to the adset-row first cell template**

- old_string: `<tr class="adset-row hidden border-b border-slate-50" data-parent="${idx}">
                            <td class="text-left pl-10 pr-4 py-2.5">`
- new_string: `<tr class="adset-row hidden border-b border-slate-50" data-parent="${idx}">
                            <td class="sticky-col text-left pl-10 pr-4 py-2.5">`

- [ ] **Step 5: Add `sticky-col` to the tfoot Filtered Total cell (added in Task 6)**

Use Edit. From the new tfoot block:

- old_string: `<td class="text-left px-4 py-3 font-semibold text-slate-700" colspan="3">Filtered Total</td>`
- new_string: `<td class="sticky-col text-left px-4 py-3 font-semibold text-slate-700" colspan="3">Filtered Total</td>`

- [ ] **Step 6: Verify**

Reload at desktop width (>768px). Resize the window narrow enough that the table needs horizontal scroll. Scroll right.

- The first column (Campaign with chevron + name) stays pinned to the left.
- The "Filtered Total" cell stays pinned in the footer.
- A subtle right-edge shadow indicates content scrolled past the sticky col.
- Hover a row → background changes consistently for both sticky and scrolling cells.
- Resize narrower than 768px → sticky disables; the table scrolls as one block.

- [ ] **Step 7: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
ui(ads): pin Campaign column when scrolling horizontally

13 columns + 11px font means horizontal scroll on most laptop widths.
Pinning the first column keeps the name in view as you scan
Spend/ROAS/etc. Sticky behavior is gated to ≥md (768px); below that
the whole table scrolls as one block.

Same sticky class applies to the tfoot 'Filtered Total' cell so totals
stay aligned with the values they sum.
EOF
)"
```

---

## Task 8: Polish #5 — Make existing chevron more visible

**File:** `resources/views/meta-marketing/ads.blade.php` — single class swap inside `renderCampaigns` template.

The chevron icon already exists in `renderCampaigns` (this was discovered when reading the current code). The actual problem is `text-slate-400` is too faint against white row background. Bump to `text-slate-500`.

- [ ] **Step 1: Edit the chevron color class in the campaign-row template**

Use Edit. Find the chevron template fragment:

- old_string: `<iconify-icon icon="heroicons-outline:chevron-right" width="14" class="expand-icon text-slate-400" id="expand-${idx}"></iconify-icon>`
- new_string: `<iconify-icon icon="heroicons-outline:chevron-right" width="14" class="expand-icon text-slate-500" id="expand-${idx}"></iconify-icon>`

- [ ] **Step 2: Verify**

Reload. Campaign rows that have ad sets show a slightly darker chevron next to the name. Click a row → chevron rotates to point down (existing behavior). The icon now reads as "interactive" without competing with the campaign name.

- [ ] **Step 3: Commit**

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "$(cat <<'EOF'
ui(ads): bump campaign-row chevron color from slate-400 to slate-500

The expand chevron exists and rotates correctly, but slate-400 against
white was barely visible — users discovered the expand behavior via
cursor change instead of the icon. slate-500 reads as 'interactive'.
EOF
)"
```

---

## Task 9: End-to-end manual verification + PR

No automated tests exist for this page. This task is the verification gate.

- [ ] **Step 1: Run the spec checklist**

Open `/marketing/analytics/ads` in a fresh tab (no URL params). Confirm each:

1. "Active" pill highlighted; only ACTIVE campaigns shown; counter "Po shfaqen X nga Y" correct.
2. Click "All" → all rows; counter updates.
3. Click "Paused" → URL `?status=PAUSED`; only paused.
4. Type "tof" → 200ms debounce, filters; clear (×) appears.
5. Click clear → input empties; URL drops `q=`.
6. Hard reload with `?status=PAUSED&q=sales` → state restored.
7. Change date preset → fetch fires; pill counts update; current filter preserved.
8. Change platform → fetch fires; counts and rows update; filter preserved.
9. Sort by Spend → totals row unchanged.
10. Resize ~600px → KPI grid 1-2 cols; table scrolls; sticky drops below md.
11. Resize ~1000px → KPI 4 cols × 2 rows; sticky Campaign visible.
12. Expand campaign with ad sets → chevron rotates; ad-set rows render.
13. Click "Pastro filtrat" inside empty state → returns to default Active.
14. Donut charts — legend below, multi-row.
15. KPI cards — no "Metrikë e re — pa informacion" subtitle.

- [ ] **Step 2: DevTools console check**

Reload. Confirm zero new errors / warnings.

- [ ] **Step 3: Pint format pass (if configured)**

```bash
./vendor/bin/pint resources/views/meta-marketing/ads.blade.php 2>/dev/null || true
git diff --stat resources/views/meta-marketing/ads.blade.php
```

If Pint reformatted something, commit as a `style:` commit:

```bash
git add resources/views/meta-marketing/ads.blade.php
git commit -m "style(ads): pint formatting after PR 1 changes"
```

- [ ] **Step 4: Push branch + open PR**

Replace `<feature-branch>` with the actual branch name (e.g. `ads-cockpit-pr1`):

```bash
git push -u origin <feature-branch>
gh pr create --title "feat(ads): Cockpit PR 1 — filters + campaigns polish" --body "$(cat <<'EOF'
## Summary
- Status pills (default Active) + Search filter for the campaigns table
- URL persistence via ?status=&q=
- Filtered totals footer
- 5 visual polish items: hide empty change subtitle, cap KPI grid at 4 cols, donut legend below chart, sticky Campaign column, more visible chevron

## Test plan
- [ ] Load /marketing/analytics/ads — Active pill highlighted, only Active campaigns, count line correct
- [ ] Click each pill — counts and rows update; URL reflects state
- [ ] Type in search — debounces, filters by name; clear (×) resets
- [ ] Reload with ?status=PAUSED&q=sales — state restored
- [ ] Change date / platform — fetch fires; pill counts refresh; current filter preserved
- [ ] Sort numeric columns — totals row stays correct
- [ ] Resize narrow — sticky column drops at <md; layout reflows
- [ ] Expand a campaign — chevron rotates; ad sets render
- [ ] Empty filter — "Pastro filtrat" link works
- [ ] Console — zero new errors

## Spec
docs/superpowers/specs/2026-05-01-ads-cockpit-pr1-design.md

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review notes

- **Spec coverage:** every spec section maps to ≥1 task. Filter bar (spec §1) → Tasks 4-5. Polish #1-5 (spec §2) → Tasks 1, 2, 3, 7, 8. Filtered totals (spec §3) → Task 6. URL state (spec §4) → Task 5.
- **The chevron was already in the code.** Task 8 was scoped down to a color bump. Acknowledged inline in the task.
- **Render template stays exactly as-is** in the existing pattern (escape via `escHtml(c.name)`; data from trusted internal endpoint). No new HTML is generated from user input that wasn't already escaped.
- **TikTok / IG / FB / Total are not in any task** — confirms narrowed spec scope.
- Each task ends in a single commit so reverts are surgical.
- All Edit operations have unique `old_string` anchors. If any matches multiple times in the live file (e.g., due to drift), the implementer must read first and pick a longer unique fragment.
