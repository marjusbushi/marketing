<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureMarketingAccess;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Auth + Marketing Dashboard
|--------------------------------------------------------------------------
*/

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
