# Production Phone View Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a mobile-first surface at `/marketing/production` for photographers to claim, shoot and advance daily-basket posts in `stage='production'`.

**Architecture:** Reuses Laravel auth, the existing `marketing.permission` middleware, and the existing `daily_basket_post_media` upload endpoint. Adds two columns to `daily_basket_posts` for soft-lock, two permission constants, one controller, six routes, two Blade views and a PWA manifest. No new packages.

**Tech Stack:** Laravel 11, Blade, vanilla JS (no Vite/Tailwind for the new views — same inline-CSS pattern as `daily-basket/index.blade.php`), MySQL.

**Spec:** `docs/superpowers/specs/2026-04-29-production-phone-view-design.md`

---

## File Structure

**Create:**
- `database/migrations/2026_04_29_120000_add_production_claim_fields_to_daily_basket_posts.php`
- `app/Http/Controllers/Marketing/ProductionController.php`
- `resources/views/production/queue.blade.php`
- `resources/views/production/_sections.blade.php`
- `resources/views/production/detail.blade.php`
- `public/production-manifest.json`
- `tests/Feature/Marketing/ProductionClaimTest.php`

**Modify:**
- `app/Enums/MarketingPermissionEnum.php` — add 2 cases
- `app/Models/DailyBasketPost.php` — add 2 fillable + 1 cast + claimer relation
- `routes/marketing.php` — add 6 routes

---

## Task 1: Migration & model fields

Adds `claimed_by_user_id` + `claimed_at` to `daily_basket_posts`, plus the
`claimer()` relationship on the model.

- [ ] Step 1.1: Write the migration as specified in the spec
- [ ] Step 1.2: Run `php artisan migrate`
- [ ] Step 1.3: Update `DailyBasketPost` model — append `claimed_by_user_id`, `claimed_at` to `$fillable`; add `'claimed_at' => 'datetime'` cast; add `claimer()` BelongsTo to `User` on `claimed_by_user_id`
- [ ] Step 1.4: Commit `feat(production): add claim fields to daily_basket_posts for soft-lock`

---

## Task 2: Permission constants

- [ ] Step 2.1: Edit `app/Enums/MarketingPermissionEnum.php` — add `PRODUCTION_VIEW = 'production.view'` and `PRODUCTION_ADVANCE = 'production.advance'` after the Content Planner block. `adminPermissions()` already returns all values, so admins inherit both.
- [ ] Step 2.2: Verify with `php artisan tinker --execute='dump(App\\Enums\\MarketingPermissionEnum::PRODUCTION_VIEW->value);'`
- [ ] Step 2.3: Commit `feat(production): add PRODUCTION_VIEW + PRODUCTION_ADVANCE permissions`

---

## Task 3: Controller scaffold + routes

- [ ] Step 3.1: Create `app/Http/Controllers/Marketing/ProductionController.php` with empty methods `queue()`, `show()`, `claim()`, `release()`, `uploadMedia()`, `advance()` (each returns a placeholder response).
- [ ] Step 3.2: Edit `routes/marketing.php` — append a new group nested under the marketing prefix:

```php
Route::prefix('production')
    ->as('production.')
    ->middleware('marketing.permission:' . P::PRODUCTION_VIEW->value)
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Marketing\ProductionController::class, 'queue'])->name('queue');
        Route::get('/{post}', [\App\Http\Controllers\Marketing\ProductionController::class, 'show'])->whereNumber('post')->name('show');
        Route::post('/{post}/claim',   [\App\Http\Controllers\Marketing\ProductionController::class, 'claim'])->whereNumber('post')->name('claim');
        Route::post('/{post}/release', [\App\Http\Controllers\Marketing\ProductionController::class, 'release'])->whereNumber('post')->name('release');
        Route::post('/{post}/advance', [\App\Http\Controllers\Marketing\ProductionController::class, 'advance'])
            ->whereNumber('post')
            ->middleware('marketing.permission:' . P::PRODUCTION_ADVANCE->value)
            ->name('advance');
    });
```

- [ ] Step 3.3: Verify `php artisan route:list --path=production` shows 5 routes.
- [ ] Step 3.4: Commit `feat(production): controller scaffold + routes under /marketing/production`

---

## Task 4: Queue list endpoint

- [ ] Step 4.1: Implement `queue()` — scope posts where `stage='production'`, eager-load `basket`, `media` (limit 1), and `claimer:id,name`. Split into 3 collections: `$mine` (claimed by me), `$free` (null), `$taken` (claimed by other). Order by `priority DESC, scheduled_for ASC`.
- [ ] Step 4.2: When `$request->wantsJson() || $request->boolean('json')`, return a JSON shape `{ mine: [...], free: [...], taken: [...] }` where each post is serialized with `id, title, post_type, post_type_label, priority, scheduled_for, lokacioni, thumbnail_url, claimed_by, claimed_at`.
- [ ] Step 4.3: Otherwise return the Blade view.
- [ ] Step 4.4: Create `resources/views/production/queue.blade.php` extending `_layouts.app`. Inline CSS like `daily-basket/index.blade.php`. Body has a header (`📷 Prodhimi`, user name, clock) and a `<div id="prSections">` that includes `production._sections`.
- [ ] Step 4.5: Create `resources/views/production/_sections.blade.php` rendering three sections (`Të miat`, `Të lira`, `Të zëna nga të tjerët`) each with their cards. Each card: thumbnail (image if media exists, else colored initial), title, post-type · location, priority pill. Section "Të zëna" cards get class `pr-card.taken` (greyed, no click).
- [ ] Step 4.6: Add a small inline `<script>` that:
  - Updates a clock element every 30s.
  - Polls `?json=1` every 30s when `document.visibilityState === 'visible'`. On poll, clear `prSections` children with a while loop and rebuild sections via `document.createElement`/`textContent`/`appendChild` (no innerHTML).
- [ ] Step 4.7: Open `/marketing/production` in browser to verify renders without errors.
- [ ] Step 4.8: Commit `feat(production): queue list view with three sections + 30s auto-refresh`

---

## Task 5: Detail endpoint + view

- [ ] Step 5.1: Implement `show(DailyBasketPost $post)`:
  - Load relations: `basket`, `media`, `itemGroups`, `claimer:id,name`.
  - Compute `$position` of the post within today's `production` queue and `$totalToday`.
  - Call private helper `referencePreview($post)` that fetches `og:image` + detects video via `Http::timeout(5)` cached 24h on `production:reference:{id}:{md5(url)}`. Returns `['image' => string|null, 'is_video' => bool]`. Wrap in try/catch returning nulls on failure.
  - Compute `$claimState` ∈ `{free, mine, taken}`.
  - Return `view('production.detail', compact('post', 'position', 'totalToday', 'referencePreview', 'claimState'))`.
- [ ] Step 5.2: Create `resources/views/production/detail.blade.php` extending `_layouts.app` with sticky nav (`← back`, "Set N / M për sot", stage pill), claim banner per `$claimState`, then in order: type pill, reference card (4:5 with og:image, ▶ if video, badge ↗ host), products (chips with `proxyCdnUrl`'d thumbnails — use `route('marketing.cdn-image')?url=...` directly in src), location card (Maps deep-link via `https://www.google.com/maps/search/?api=1&query={urlencode lokacioni}`), model chips (avatar + initial), notes box, upload zone.
- [ ] Step 5.3: Sticky bottom row with `[← Lista]` and `[✓ Dërgo në Editim →]` (latter disabled if `$claimState !== 'mine'`).
- [ ] Step 5.4: Inline `<script>` at the bottom — capture POST_ID + CSRF token. Wire:
  - `prClaimBtn` click → POST `/claim` → reload on 200 or alert+reload on 409.
  - `prReleaseBtn` click → confirm → POST `/release` → reload.
  - `prAdvanceBtn` click → POST `/advance`. On 422 with `code === 'no_media'`, show confirm and re-POST with `{force: true}`. On success, navigate to queue.
  - File input `change` → for each file, compress images via `createImageBitmap` + canvas.toBlob (JPEG 0.9, max 2400px wide). Upload via `XMLHttpRequest` to `/marketing/daily-basket/api/posts/{id}/media` with progress bar. Append uploaded tile to grid via `document.createElement`/`appendChild` (NO innerHTML — build the `<img>`/`<video>` and the `×` button as separate created nodes).
  - `window.prDeleteMedia(mediaId)` → confirm → DELETE existing endpoint → remove tile from DOM.
- [ ] Step 5.5: Manually open a production post detail page; verify banner + reference + products + location + models render.
- [ ] Step 5.6: Commit `feat(production): post detail view with reference, products, location, models, upload`

---

## Task 6: Claim + release endpoints (atomic)

- [ ] Step 6.1: Implement `claim(DailyBasketPost $post)`:
  - 422 if `stage != production`.
  - Use `DB::table('daily_basket_posts')->where('id', $id)->whereNull('claimed_by_user_id')->update([...])`.
  - If `affected == 0`: refresh + load claimer, return 409 with `message`, `claimed_by`, `claimed_at_human`.
  - Else: return 200 with `claimed_at`.
- [ ] Step 6.2: Implement `release(DailyBasketPost $post)`:
  - Allow if `claimed_by_user_id` is `null`, equals current user, OR current user has `PRODUCTION_ADVANCE` permission.
  - Else 403.
  - On success, set both fields to `null`, return 200.
- [ ] Step 6.3: Create `tests/Feature/Marketing/ProductionClaimTest.php` with 3 tests:
  1. `test_claim_succeeds_when_post_is_free`
  2. `test_concurrent_claims_resolve_one_winner` (claim twice with different users; second gets 409)
  3. `test_release_is_only_allowed_by_claimer`
  Use the project's existing `User`/role/permission factory pattern. If the role schema doesn't allow easy granting, attach an admin role that includes all permissions.
- [ ] Step 6.4: Run `php artisan test --filter=ProductionClaimTest` until all pass.
- [ ] Step 6.5: Commit `feat(production): atomic claim + release with concurrent-safe SQL`

---

## Task 7: Advance endpoint

- [ ] Step 7.1: Implement `advance(Request $request, DailyBasketPost $post)`:
  - 422 if not in production stage.
  - 403 if claimed by someone else (admins bypass via the route's middleware).
  - If `$post->media()->exists() === false` AND `! $request->boolean('force')`: 422 with `{warning, code: 'no_media'}`.
  - Else: `$post->update(['stage' => DailyBasketPostStage::EDITING->value])`.
  - 200 with new stage value.
- [ ] Step 7.2: Smoke test in browser — post with no media → confirm dialog; post with media → moves to editing.
- [ ] Step 7.3: Commit `feat(production): advance endpoint with media-required guard`

---

## Task 8: PWA manifest

- [ ] Step 8.1: Create `public/production-manifest.json`:

```json
{
  "name": "Prodhimi · Zero Absolute",
  "short_name": "Prodhimi",
  "start_url": "/marketing/production",
  "scope": "/marketing/production",
  "display": "standalone",
  "orientation": "portrait",
  "theme_color": "#1f2937",
  "background_color": "#fafafa",
  "icons": [
    { "src": "/favicon.ico", "sizes": "any", "type": "image/x-icon" }
  ]
}
```

> Icons: v1 reuses `/favicon.ico` to unblock launch. A follow-up adds proper 192/512 PNGs once brand assets ship.

- [ ] Step 8.2: Reference it from both views via `<link rel="manifest" href="{{ asset('production-manifest.json') }}">` and `<meta name="theme-color" content="#1f2937">` in the `@push('head')` block.
- [ ] Step 8.3: Commit `feat(production): minimal PWA manifest for Add to Home Screen`

---

## Task 9: Final QA + push

- [ ] Step 9.1: `php artisan route:list --path=production` → 5 routes registered.
- [ ] Step 9.2: Browser smoke test: queue → click post → claim → upload photo → advance → returns to queue with post gone.
- [ ] Step 9.3: `git push origin main` (single push for all commits if not already pushed task-by-task).
- [ ] Step 9.4: `git status` shows up-to-date with origin.

---

## Out-of-scope (track separately)

- Chip input for Modelet in Shporta planning side
- Google Places autocomplete for Lokacioni
- Auto-release of stale claims after 24h
- Push notifications when new posts land in production
- Offline upload queue using LocalStorage
- Proper 192/512 PNG PWA icons
