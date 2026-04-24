<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Webhooks\MetaInstagramWebhookController;
use App\Http\Middleware\EnsureMarketingAccess;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Auth + Marketing Dashboard
|--------------------------------------------------------------------------
*/

// Meta DM webhook — public endpoint (auth via X-Hub-Signature-256). Handles
// BOTH Instagram DMs and Facebook Messenger events; ProcessMetaIgWebhookEventJob
// detects platform from the top-level `object` field of each payload. /page is
// an alias of /instagram so the Meta App Dashboard can use whichever URL reads
// clearest when subscribing the Page product. CSRF is excluded for both paths
// in bootstrap/app.php.
Route::get('/webhooks/meta/instagram', [MetaInstagramWebhookController::class, 'verify']);
Route::post('/webhooks/meta/instagram', [MetaInstagramWebhookController::class, 'receive']);
Route::get('/webhooks/meta/page', [MetaInstagramWebhookController::class, 'verify']);
Route::post('/webhooks/meta/page', [MetaInstagramWebhookController::class, 'receive']);

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

// Authenticated + marketing access
Route::middleware(['auth', EnsureMarketingAccess::class])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('marketing.analytics.index');
    })->name('dashboard');
});
