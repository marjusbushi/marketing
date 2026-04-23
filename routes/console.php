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
