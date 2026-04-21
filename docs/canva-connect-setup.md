# Canva Connect Setup

Post Decision #14 the photo/carousel/story flow uses Canva Connect instead of an
embedded editor. Before the "Open in Canva" button becomes usable in Visual
Studio, three out-of-band steps have to happen once per environment.

## 1. Register a Canva developer app

1. Go to https://www.canva.com/developers/ and create a new **Integration**.
2. In **Authentication**, add one redirect URI per environment:
   - Local dev (HTTPS tunnel): `https://<your-tunnel-host>/marketing/canva/callback`
   - Staging: `https://stage.zeroabsolute.dev/marketing/canva/callback`
   - Production: `https://<prod-host>/marketing/canva/callback`
3. Under **Scopes**, enable everything listed in
   [config/canva.php](../config/canva.php) → `oauth.scopes`:
   - `design:content:read`, `design:content:write`, `design:meta:read`
   - `brandtemplate:content:read`, `brandtemplate:meta:read`
   - `asset:read`, `asset:write`
   - `profile:read`
4. Copy the **Client ID** and **Client Secret** — you'll paste them in `.env`.

## 2. Wire the credentials

Add to the app's `.env` (and to the secret store for staging / prod):

```env
CANVA_CLIENT_ID=...
CANVA_CLIENT_SECRET=...
CANVA_REDIRECT_URI=/marketing/canva/callback
CANVA_FEATURE_CONNECT=true
```

The feature flag stays `false` until both client credentials and the redirect
URI are registered. With the flag off, `/marketing/canva/*` responds `404` and
the button never renders — deployments are safe with no Canva config set.

## 3. Local HTTPS tunnel

Canva rejects `http://localhost` redirect URIs. Pick one of:

- **Cloudflare Tunnel** (preferred — stable URL, free, 10 min setup):
  ```sh
  cloudflared tunnel --url http://localhost:8000
  ```
  Add the resulting `https://<random>.trycloudflare.com/marketing/canva/callback`
  to the Canva app's redirect URIs.
- **ngrok** (quick, URL changes per run):
  ```sh
  ngrok http 8000
  ```
- **Valet share** / Herd / similar.

Then set `APP_URL=https://<your-tunnel-host>` in `.env` so the redirect URI
computed by [CanvaAuthController](../app/Http/Controllers/Marketing/CanvaAuthController.php)
matches what you registered.

## 4. Link a marketing template to a Canva brand template

The "Open in Canva" button autofills a Canva brand template. To enable it:

1. In Canva, create a brand template and copy its **brand template id** (visible
   in the URL or via the Canva API).
2. Update the relevant row in `marketing_templates`:
   ```sql
   UPDATE marketing_templates
   SET canva_brand_template_id = 'BRAND_TEMPLATE_ID_FROM_CANVA'
   WHERE slug = 'drop-reel';
   ```
3. Refresh Visual Studio. The selector in the editor header only lists templates
   that have a `canva_brand_template_id` — templates without it are invisible
   to the Canva flow (they belong to the old Polotno path being phased out).

## 5. One-time brand kit sync

After `CANVA_FEATURE_CONNECT=true` the Brand Kit page grows a
**Sinkronizo me Canva** button. Click it once to push colors + logos into the
authenticated user's Canva brand kit. The sync is one-way — the marketing app
stays the source of truth. Re-run the sync whenever the brand kit changes.

## Flow (end-to-end)

```
User clicks "Hap në Canva"
    │
    ├─ Not connected? → /marketing/canva/authorize → Canva consent → /callback
    │                                                      │
    │                                                      ▼
    │                                         tokens stored (encrypted)
    │
    ├─ Connected → POST /api/canva/designs (autofill brand template)
    │    ▼
    │  Canva returns design id + edit_url
    │    ▼
    ├─ User edits inside Canva (new tab)
    │
    ├─ User clicks "Unë mbarova — merre në shportë" in our app
    │    ▼
    │  POST /api/canva/designs/{id}/export → poll /api/canva/exports/{jobId}
    │    ▼
    │  Exported asset URL attached to creative_briefs.state.canva and
    │  mirrored into creative_briefs.media_slots
    │
    └─ Downstream: when the daily_basket_post transitions to stage=scheduling,
       the media from creative_briefs.media_slots feeds content_posts.
```

## Operational notes

- **No webhooks** — Canva Connect does not broadcast design or export events.
  The SPA polls `/api/canva/exports/{jobId}` with backoff (defaults in
  `config/canva.php → polling`, about a 90s budget).
- **Token encryption** — `access_token` and `refresh_token` use the Laravel
  `encrypted` cast (AES-256-CBC via `APP_KEY`). Rotating `APP_KEY`
  invalidates stored tokens; users re-auth on next action.
- **Token refresh** — `CanvaConnectService::getValidAccessToken()` refreshes
  transparently when the access token is expired or expiring within 5 min.
  Failures bubble up as `RuntimeException` → HTTP 502 to the SPA.
- **Disconnect** — `POST /marketing/canva/disconnect` revokes the refresh token
  at Canva and clears the encrypted columns. Safe to call even when no
  connection exists (returns `status: no_connection`).
