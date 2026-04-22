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

// Meta Instagram DM webhook — public endpoint (auth via X-Hub-Signature-256).
// CSRF is excluded for this path in bootstrap/app.php.
Route::get('/webhooks/meta/instagram', [MetaInstagramWebhookController::class, 'verify']);
Route::post('/webhooks/meta/instagram', [MetaInstagramWebhookController::class, 'receive']);

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
