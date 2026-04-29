# Production Phone View — Design Spec

**Date**: 2026-04-29
**Owner**: Marjus Bushi
**Status**: Approved (sections), pending spec review
**AntTech**: TBD task on approval

## Problem

The production team — photographers who shoot daily content sets — has no
dedicated UI to manage their work in the field. They currently rely on the
desktop Shporta Ditore page, which is built for marketing managers (calendars,
product pickers, captions, etc.) and is unusable on a phone in the middle of a
shoot.

The team needs a phone-first surface that:

- Surfaces only posts in `stage='production'` (no calendar, no products picker).
- Lets a photographer **claim** a shoot (so two people don't shoot the same
  thing), do it, upload the media, and **advance** the post to `stage='editing'`.
- Shows the reference, the products to wear, the location (with Maps deep-link)
  and the model contacts in a layout sized for one-handed use.

## Goals (in scope)

1. Mobile-first route under the existing marketing app — no separate domain or
   new auth stack.
2. A shared queue with soft-lock semantics: any photographer can claim, claim
   is recorded on the post, others see who took it.
3. Detail view that mirrors the approved v2 mockup: reference preview, product
   chips, location with Maps link, model chips, notes, native upload picker.
4. Sticky stage track + buttons; one-tap **"Dërgo në Editim"** to advance.
5. PWA "Add to Home Screen" support — photographers install the icon and use it
   like an app, no service worker / offline support in v1.

## Non-goals (v1)

- Push notifications when a new shoot lands. (Auto-poll every 30s instead.)
- Offline mode / queued uploads when network drops. (LocalStorage retry is a
  v2 nice-to-have.)
- In-app messaging between photographers and marketing.
- Auto-release of stale claims (manual clear by marketing for now).
- Real-time websocket updates for "Era just claimed this" events.
- Native iOS/Android apps.

## Design choices (decided during brainstorming)

| Decision | Choice | Why |
|---|---|---|
| Queue model | Shared, pure (no `assigned_to`) | Small team self-organizes; lower admin overhead. |
| Auth | Existing Laravel auth + 30d remember-me | No new infra; reuses MarketingPermissionEnum + middleware. |
| Stage advance trigger | Manual button "Dërgo në Editim" | Explicit confirmation; avoids accidental advances on upload. |
| Photo upload | Native `<input type="file" capture="environment" multiple>` | Zero JS framework; OS picker handles camera/library/files. |
| Compression | Client-side JPEG 90%, max 2400px wide | Saves mobile data + server disk; reuses existing media table. |
| Lock TTL | None in v1 | Manual clear by marketing if a photographer abandons a claim. |
| Reference media | Server-fetched `og:image` from `reference_url` | Marketing already pastes a Pinterest/IG link; we extract the preview. |
| Location | Phase 1: free-text → maps query string. Phase 2: Google Places autocomplete in Shporta planner so phone view can deep-link with lat/lng. | Phase 1 unblocks the phone view; phase 2 is a separate task on the planner side. |
| Models input | Phase 1: keep current free-text rendering as chips on detail. Phase 2: chip input in Shporta planner. | Same reasoning as location. |

## Architecture

### Routes (new)

All under `/marketing/production`, gated by:

- `auth` middleware (existing Laravel session + remember-me)
- `marketing.permission:production.view` (new permission constant)

```
GET  /marketing/production              ProductionController@queue
GET  /marketing/production/{post}       ProductionController@show
POST /marketing/production/{post}/claim ProductionController@claim
POST /marketing/production/{post}/release ProductionController@release
POST /marketing/production/{post}/media ProductionController@uploadMedia
POST /marketing/production/{post}/advance ProductionController@advance
```

`{post}` resolves to `DailyBasketPost` with route-model binding. Controller
authorizes the post is in `stage='production'` for write actions; `show` also
allows `stage='editing'` so a photographer can review what they sent (read-only).

### Permissions

Add to `app/Enums/MarketingPermissionEnum.php`:

```php
case PRODUCTION_VIEW    = 'production.view';
case PRODUCTION_ADVANCE = 'production.advance';
```

`adminPermissions()` already returns all values, so existing admins get both.
A new "Production" role for photographers gets only these two plus
`MODULE_MARKETING_ACCESS`.

### Data model changes

One migration adds two columns to `daily_basket_posts`:

```php
Schema::table('daily_basket_posts', function (Blueprint $t) {
    $t->foreignId('claimed_by_user_id')
      ->nullable()
      ->constrained('users')
      ->nullOnDelete();
    $t->timestamp('claimed_at')->nullable();
    $t->index(['stage', 'claimed_by_user_id']);
});
```

Semantics:

- `claimed_by_user_id IS NULL` → free, available in shared queue.
- `claimed_by_user_id = me` → in "Të miat" section.
- `claimed_by_user_id != me AND IS NOT NULL` → in "Të zëna" section (greyed,
  not openable for editing — read-only banner shown).
- When stage advances to `editing`, both fields are kept for analytics
  ("who shot this set").

No other tables change. Production media uploads go to the existing
`daily_basket_post_media` table.

### View layer

Two new Blade views, mobile-first inline CSS (matches existing
`daily-basket/index.blade.php` pattern — no Tailwind/Vite work):

- `resources/views/production/queue.blade.php` — list view
- `resources/views/production/detail.blade.php` — detail view

PWA manifest:

- `public/production-manifest.json` — name, short_name, theme_color, icons.
- `<link rel="manifest" href="...">` injected only on production pages so it
  doesn't pollute other routes.
- 192×192 + 512×512 PNG icons (camera silhouette on dark background, generated
  via the existing brand kit).

`<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">`
on production pages so accidental zoom doesn't break the layout.

## Page 1 — Queue list

Ordered top-to-bottom:

1. **Header**: "📷 Prodhimi" + user name + current time. Filter icon top-right.
2. **Të miat** section (count): posts where `claimed_by_user_id = me AND stage = 'production'`.
3. **Të lira** section (count): `claimed_by_user_id IS NULL AND stage = 'production'`.
4. **Të zëna** section (count, collapsed by default): `claimed_by_user_id != me AND IS NOT NULL`.

Each card shows: type-coded thumbnail (gradient placeholder if no media yet),
title, post type + location, priority/deadline tag.

Sort within each section: priority desc → scheduled_for asc.

Filter modal (icon → bottom sheet): post type checkboxes (Reel/Story/Photo/Carousel),
location text search.

Auto-refresh every 30s via `fetch('/marketing/production?json=1')` to pick up
new posts and lock changes. Pull-to-refresh works natively (browser default).

Empty state: "🎉 Asnjë shoot për sot. Pushim i merituar."

## Page 2 — Post detail

Approved layout from `post-detail-v2.html` mockup. Sections in order:

1. **Header**: ← back, "Set N / M për sot" (counts within today's basket),
   stage pill "Prodhim".
2. **Tipi i postit**: pill with the type colour (Reel/Story/Photo/Carousel/Video).
3. **Referenca**: 4:5 preview card.
   - Server-side: on first request, fetch `og:image` from `reference_url`
     (with 5s timeout, cached 24h) and store it as `reference_thumbnail_url`
     on the post. Re-fetched only if `reference_url` changes.
   - If `og:video` (or `twitter:player`) exists, show a ▶ play overlay; tap
     opens the URL externally (the OS plays the video in the source app).
   - "↗ pinterest.com" badge top-right.
   - Tap → `window.open(reference_url)` (external).
4. **Produktet**: chips with R2-proxied thumbnails (existing `proxyCdnUrl`
   helper from daily-basket).
5. **Lokacioni**: card with mini-map background SVG and address text.
   - Phase 1: tap → `https://www.google.com/maps/search/?api=1&query={URL-encoded location}`.
   - Phase 2 (after Places autocomplete in planner): use `lat,lng` for an
     exact pin.
6. **Modelet**: chips with avatar + initial. Tap, in v1, opens nothing — but
   if `users` table has the model linked with a phone, opens
   `whatsapp://send?phone=...`. Otherwise the chip is purely informational.
7. **Mood / shënime**: yellow-highlighted box with `reference_notes`.
8. **Materiali yt**: upload zone — see Upload flow below.
9. **Sticky bottom**: [Ruaj] [Dërgo në Editim →]. The "Ruaj" button is a
   no-op confirmation that the data is saved (auto-save on blur already
   handles persistence; the button gives a visible "saved" pulse for users
   who don't trust auto-save).

Above all of this, a **claim banner** appears conditionally:

- Not claimed → "Ky post është pa marrë. [📷 Po e marr unë]"
- Claimed by me → "Ti e ke marrë 9:41. [✗ Largoja]"
- Claimed by someone else → "Era e mori 2 min më parë" (read-only — no upload
  buttons, no advance, no claim)

## Soft-lock workflow

### Claim

Endpoint: `POST /marketing/production/{post}/claim`

Atomic SQL:

```sql
UPDATE daily_basket_posts
SET    claimed_by_user_id = :me, claimed_at = NOW()
WHERE  id = :post AND claimed_by_user_id IS NULL AND stage = 'production'
```

If `affectedRows = 0`, return 409 Conflict with the current claimer info. UI
re-fetches and shows "X e mori Y sek më parë."

### Release

Endpoint: `POST /marketing/production/{post}/release`

Allowed only by the current claimer or marketing admin. Sets both fields to
NULL.

### Advance

Endpoint: `POST /marketing/production/{post}/advance`

Validation:

- Post must be in `stage='production'`.
- Post must be claimed by the current user (or admin override).
- Must have at least 1 row in `daily_basket_post_media` (server-side check).
  If zero, return 422 with `{"warning": "S'ke ngarkuar foto…"}` — UI shows
  a confirm dialog and re-POSTs with `force=1` if the user accepts.

On success:

- `stage` set to `'editing'`.
- `claimed_by_user_id` and `claimed_at` retained (historical record).
- Returns the updated post; UI navigates back to the queue.

## Upload flow

UI: a single dashed-border zone whose tap target is a hidden
`<input type="file" accept="image/*,video/*" capture="environment" multiple>`.
For carousel posts, multiple files are accepted; for others, only the first is
kept (server-side enforcement).

Client-side compression (images only) before upload:

- Resize to max 2400px wide, preserving aspect ratio.
- Re-encode JPEG 0.9 quality.
- Implementation: vanilla `Canvas`/`createImageBitmap` (no library; ~40 lines).
- Videos uploaded as-is (no client-side transcoding — too heavy).

Upload endpoint: reuses
`POST /marketing/daily-basket/api/posts/{post}/media` (existing). The new
production routes are thin wrappers that authorize role first, then forward.

UI shows a per-file progress bar (XHR `upload.onprogress`). Failed upload
exposes a Retry button. Successful upload appends a thumbnail tile under the
upload zone with an [×] delete affordance (reusing the existing
`DailyBasketPostMedia` delete endpoint).

## Notifications & polling

- Queue list: poll `/marketing/production?json=1` every 30s when the tab is
  visible (`document.visibilityState === 'visible'`); pause when hidden.
- Detail view: same 30s poll for claim status, in case marketing released the
  post or another user claimed first.
- No push notifications in v1. The 30s cadence is fast enough that a
  photographer arriving at the studio will see fresh shoots within half a
  minute.

## Failure modes & error handling

| Scenario | Handling |
|---|---|
| Network drops mid-upload | Retry button; the file blob is held in JS memory until success. No automatic background retry in v1. |
| Two photographers claim simultaneously | Atomic SQL above resolves it; the loser sees "X e mori 5 sek më parë" and the page re-fetches. |
| Photographer hits "Dërgo" with zero media | 422 + confirm dialog. They can force the advance (rare — sometimes shoot is cancelled and we still need to move it forward). |
| Reference URL fails to fetch og:image | Card falls back to favicon + title only (current style). |
| User session expires during upload | XHR returns 419; UI shows "Të dalësh dhe rikthehesh" with a tap-to-relogin link that preserves the file blob. |
| User opens a post not in production stage | Read-only view with banner "Ky post është te {stage_label}, nuk mund të editohet këtu." |

## Testing

- **Unit**: claim() atomicity test (concurrent updates → only one succeeds);
  advance() guards (no-media warning, wrong-claimer 403).
- **Feature**: HTTP test for queue endpoint returning correct sections;
  detail endpoint returning the right banner per claim state.
- **Manual mobile QA**: sanity check on iOS Safari + Android Chrome — camera
  capture, multi-file picker, upload progress, Add to Home Screen, Maps
  deep-link.

## Out-of-scope follow-up tasks (track in AntTech separately)

1. **Shporta planner — chip input for Modelet**: replace free-text with chip
   input that supports inline edit and links to the `users` table where a model
   is also a system user.
2. **Shporta planner — Google Places autocomplete for Lokacioni**: store
   `name` + `place_id` + `lat` + `lng`. Phone view then uses lat/lng for an
   exact Maps pin.
3. **Auto-release of stale claims**: cron `production:release-stale` if a post
   has been claimed for >24h without media uploads; notify the claimer.
4. **Push notifications**: web push when a new post lands in production stage,
   so photographers don't have to poll.
5. **Offline upload queue**: LocalStorage-backed retry when connectivity
   drops mid-shoot.
