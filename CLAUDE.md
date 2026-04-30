# Flare — ZA Marketing

Marketing operations app i Zero Absolute. Laravel 11 + Inertia/Vue 3 + Blade,
MySQL me cross-DB connection `dis` te DIS ERP. Repo: `github.com/marjusbushi/marketing`.

AntTech project ID: **18** ("ZA Marketing (Flare)"). Module aktiv: **108**
("Content Planner Publishing"). Përdor `mcp__anttech__get_handoff` me project=18
në fillim të çdo sesioni.

## Skedulim FB+IG (Content Planner Publishing) — gjendja aktuale

**Plani:** Skedulim real FB+IG nga Flare (publish pipeline) — 11 dev tasks.
Wave 1-5 (10 task-e) PËRFUNDUAR. Wave 6 (E2E test #1765) i bllokuar nga
hapat manualë të user-it (R2 + tunnel).

**Arkitektura:**
- Dispatch-with-delay: `PublishContentPostJob::dispatch($post)->delay(scheduled_at)`
  thirret nga `ContentPostService::dispatchScheduledJob()` te createPost /
  updatePost / changeStatus / reschedule.
- Atomic claim te job: `UPDATE WHERE status='scheduled' AND scheduled_at_version=X`
  → `status='publishing'`. Race-safe + reschedule-safe.
- Tries=1 + try/catch në handle() — failure flip-on `publishing → failed` me
  `MetaErrorSanitizer::redact()` për error_message. Tries=3 do të linte rreshta
  ngecur në `publishing` 22 min (claim s'mund të ribëhet).
- Approval gate te `ensureApprovalReady()`: `approval_type='none'` ose
  `approved_at IS NOT NULL` për të kaluar te scheduled.
- Safety-net: `php artisan content-planner:publish-due-safety-net` çdo 30 min
  (cron i regjistruar te routes/console.php).

**Vendimet kryesore arkitekturore** (logged në AntTech):
- #53: MetaTokenResolver, jo config('services.facebook.*') direkt
- #54: dispatch-with-delay + safety-net cron, jo cron-poll
- #55: Cloudflare R2 me bucket `flare-social` (egress falas për video Meta)
- #56: Approval gate me approval_type + approved_at

## IG content_publish — FB-bounce CDN workaround ✅

**Issue:** Që nga 2026-03-13, Meta IG Graph API content_publish silently
refuzon ÇDO URL nga CDN-të e jashtme: Cloudflare R2 (custom domain, Worker
proxy, r2.dev), AWS S3, CloudFront, Azure, GCP. Error code 9004 +
subcode 2207052: "Media URI doesn't meet our requirements" — edhe pse
curl + Meta sharing debugger arrijnë URL-në saktë. Forum thread:
[1593241148449585](https://developers.facebook.com/community/threads/1593241148449585/).

**Workaround i implementuar:** ngarkojmë mediën te FB Page si
`published=false`, marrim `fbcdn.net` URL nga response, dhe e japim atë
URL te IG. IG e pranon URL-në e vet të Meta-s. Pas IG publish (ose dështim),
fshijmë stub-in e fshehur FB. Implementuar te
[FbCdnHelper.php](app/Services/ContentPlanner/Publishing/FbCdnHelper.php)
dhe i përdorur nga
[InstagramPublishService.php](app/Services/ContentPlanner/Publishing/InstagramPublishService.php)
(see comments).

**Provuar realisht 2026-04-30:** foto IG + Reels video — të dyja punojnë.
Vonesa shtesë: ~3-5 sek për foto, ~5-15 sek për video (FB transcoding +
ngarkim te IG container).

**Kufizim API-së:** IG content_publish API NUK lejon DELETE të post-eve të
publikuar. Test post-et duhet të fshihen manualisht nga IG app/web.

## Cloudflare R2 — bucket `flare-social` ✅ provisioned

**Account ID:** `1b5c25eea4f903f6c1add30f53904e3c`
**Account email:** Renatobanushaj@zeroabsolute.com
**Bucket:** `flare-social` (location WEUR, krijuar 2026-04-30)
**Public URL:** `https://pub-0d379a88f55a4637a52cdcf95e86df8a.r2.dev`

⚠️ **r2.dev URL nuk arrihet nga Meta server-at** — verifikuar 2026-04-30 me real
IG API call që ktheu `error_subcode 2207052: Media download has failed`.
Cloudflare e thotë në docs që r2.dev është "for dev only". Para se IG publish të
punojë në prod, **duhet custom domain** (psh `media-flare.zeroabsolute.com`):
1. Cloudflare dashboard → R2 → bucket flare-social → Settings → Custom Domains
2. Add domain (Cloudflare auto-create-on certifikatën)
3. Update R2_PUBLIC_URL te .env me domain-in e ri
4. Image URL i ri do duket: https://media-flare.zeroabsolute.com/<path>

FB publish punon edhe me r2.dev — IG API është më strikt me URL-të.
**S3 endpoint:** `https://1b5c25eea4f903f6c1add30f53904e3c.r2.cloudflarestorage.com`

### Credentials — të gjitha në .env (gitignored)

`.env` përmban:
- `CLOUDFLARE_API_TOKEN` — Workers R2 Storage:Edit scope, vlefshëm forever.
  Përdor për management operations (krijim bucket, list, enable r2.dev).
- `CLOUDFLARE_ACCOUNT_ID` — public, nuk është sekret.
- `R2_ACCESS_KEY_ID` + `R2_SECRET_ACCESS_KEY` — S3-compatible credentials,
  scope: Object Read & Write për bucket `flare-social`.
- `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL`, `CONTENT_PLANNER_MEDIA_DISK=r2_social`.

**Mos i kopjo në CLAUDE.md, Slack, screenshot, ose git commit.** .env është
gitignored — kushdo që ka nevojë lexon nga atje me `env('R2_*')` ose
`config('filesystems.disks.r2_social.*')`.

### Cloudflare API endpoints (për management automatik)

Kërkojnë `Authorization: Bearer ${CLOUDFLARE_API_TOKEN}`:
- `POST /accounts/{ID}/r2/buckets` — krijo bucket
- `PUT  /accounts/{ID}/r2/buckets/{bucket}/domains/managed` — toggle r2.dev URL
- `GET  /accounts/{ID}/r2/buckets` — lista buckets
- S3 access keys: **NUK ekspozohen** përmes REST API. Krijohen vetëm te
  dashboard (`R2 → Manage R2 API Tokens`). Nëse rrotullohen, update .env.

### Verifikim

```bash
php artisan tinker
>>> Storage::disk('r2_social')->put('test.txt', 'hello');
>>> echo Storage::disk('r2_social')->url('test.txt');
# duhet të kthej pub-0d379a88...r2.dev/test.txt — fetchuar nga browser punon
>>> Storage::disk('r2_social')->delete('test.txt');
```

Verifikuar 2026-04-30: upload OK, public URL HTTP 200, Meta-arritshme.

## Meta tokens

`config('meta.*')` hidratohet nga `MetaTokenResolver::hydrateConfig()` te
`AppServiceProvider`. Burimet, sipas prioritetit:
1. `meta_tokens` (OAuth flow në vetë Flare)
2. `hrms_meta_credentials` (DIS DB, Crypt::encryptString'd me HRMS APP_KEY —
   set `HRMS_APP_KEY` te .env nëse përdoret)

Publish services lexojnë `config('meta.page_token')` / `meta.page_id` /
`meta.ig_account_id` / `meta.api_version` — JO `services.facebook.*` (s'ekziston).

## Zhvillimi lokal

- TZ: `Europe/Tirane` (config/app.php). Carbon::now() kthen `+02:00` (CEST).
- APP_URL: aktualisht `localhost:8002` — për E2E test me Meta duhet tunnel
  (Cloudflare Tunnel: `cloudflared tunnel --url http://localhost:8002`).
- Queue: `database` driver. Worker për publish: `php artisan queue:work --queue=content-publish`.
- ffmpeg: 8.1 i instaluar (`/opt/homebrew/bin/ffmpeg`). Përdoret nga ContentMediaService
  përmes Process::run([...]) për video thumbnails.

## Convention notes

- Mesazhe error-i për user në UI: shqip ("Posti kërkon aprovim para se të skedulohet…").
- Komente në kod: anglisht.
- Status `publishing` është intermediate — set vetëm nga atomic claim, jo nga UI/API.
- Çdo write që mund ta vendosë postin te `scheduled` DUHET të kaloj nga
  `ContentPostService` (jo `$post->update(...)` direkt) — përndryshe kapërcen
  approval gate + dispatch + version bump. Audit gjeti dy vende ku kjo mungonte
  (DailyBasketController + batchSchedule); të dyja u rregulluan.

## Files që preken më shpesh

- [ContentPostService.php](app/Services/ContentPlanner/ContentPostService.php) — keystone (gates, dispatch, retry, version)
- [PublishContentPostJob.php](app/Jobs/PublishContentPostJob.php) — atomic claim + failure recovery
- [ContentPublishManager.php](app/Services/ContentPlanner/Publishing/ContentPublishManager.php) — orchestrator
- [FacebookPublishService.php](app/Services/ContentPlanner/Publishing/FacebookPublishService.php), [InstagramPublishService.php](app/Services/ContentPlanner/Publishing/InstagramPublishService.php) — per-platform (lexojnë config('meta.*'))
- [MetaErrorSanitizer.php](app/Services/ContentPlanner/Publishing/MetaErrorSanitizer.php) — token redaction
- [ContentPlannerPublishDueSafetyNetCommand.php](app/Console/Commands/ContentPlannerPublishDueSafetyNetCommand.php) — cron 30min
