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
