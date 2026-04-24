<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync IG + FB posts (with media carousels + video download) every hour.
// Kjo mban meta_post_media te fresket pa nevojen e klikimit te butonit "Sync"
// ne Content Planner. withoutOverlapping() garanton qe nese nje run zgjat
// me shume se 1h, nuk fillon nje i dyte paralel (zero API rate-limit risk).
// runInBackground() liron scheduler-in shpejt — nese meta:sync deshton,
// cron-i tjeter vjen ne oren tjeter.
Schedule::command('meta:sync', ['--type' => 'posts'])
    ->hourly()
    ->withoutOverlapping(60) // 60-min lock TTL
    ->runInBackground();

// Sync messaging stats (Messenger + IG DMs) hourly.
// Root cause of prod "0 organic DMs" bug (Apr 2026): scheduler previously only
// ran --type=posts, so meta_messaging_stats never got a row for today/yesterday
// unless user hit "Rifresko". When META_IG_WEBHOOK_START_DATE is unset the
// dashboard falls back to this table — empty table ⇒ zero organic. Runs 5 min
// past hour so we stagger behind posts + ads and never lock the API for longer
// than needed. For IG-webhook-era dates the dashboard still prefers
// meta_ig_dm_events (exact); this sync provides the pre-webhook + Messenger
// baseline and a safety net if webhook delivery hiccups.
Schedule::command('meta:sync', ['--type' => 'messaging'])
    ->hourlyAt(5)
    ->withoutOverlapping(60)
    ->runInBackground();

// Auto-import IG/FB posts as ContentPost records (planner layer) every hour
// staggered 15 min after meta:sync to reuse fresh token cache. Without this,
// published posts appear only in analytics but not as planner posts.
Schedule::command('content-planner:import-feed')
    ->hourlyAt(15)
    ->withoutOverlapping(60)
    ->runInBackground();

// Repair IG posts whose media_url is null, points to an expired raw CDN URL,
// or references a missing local file. meta:sync downloads media during the
// initial post fetch, but IG CDN 403s, transient timeouts, or hosts under load
// all silently leave media_url null — leading to empty thumbnails on the
// dashboard and Content Planner grid. Staggered 25 min past hour so it runs
// after meta:sync (:00) and content-planner:import-feed (:15). --limit=50 caps
// per-run work so we never exhaust rate limits even after a long outage; the
// loop catches up over successive hours. Decision #24 already switched
// downloadMedia() to return null on failure instead of persisting a short-TTL
// CDN URL, so this self-heals going forward without leaking bad rows.
Schedule::command('meta:backfill-ig-media', ['--limit' => 50])
    ->hourlyAt(25)
    ->withoutOverlapping(50)
    ->runInBackground();

// Keep the IG DM webhook subscription healthy. Meta drops subscriptions
// silently (token rotation, app permission review, Meta-side transient). When
// that happens meta_ig_dm_events stops receiving rows and the dashboard's
// organic count quietly decays to zero. Symptom that prompted this (2026-04-24):
// webhook activated 04-22, last event 04-22 11:46 UTC, 48h of silence before
// anyone noticed. Hourly at :35 — staggered behind posts (:00), messaging
// (:05), import-feed (:15), backfill (:25). Idempotent POST to
// /PAGE_ID/subscribed_apps, so no harm if run while subscription is already OK.
Schedule::command('meta:ig-webhook-heal')
    ->hourlyAt(35)
    ->withoutOverlapping(50)
    ->runInBackground();
