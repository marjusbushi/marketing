<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('marketing')
                ->as('marketing.')
                ->group(base_path('routes/marketing.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'marketing.access' => \App\Http\Middleware\EnsureMarketingAccess::class,
            'marketing.permission' => \App\Http\Middleware\CheckMarketingPermission::class,
        ]);

        // Redirect authenticated users away from login page
        $middleware->redirectUsersTo('/');

        // Redirect unauthenticated users to login page (instead of returning 401)
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Meta signs webhook POSTs with X-Hub-Signature-256; we verify HMAC
        // inside the controller. CSRF tokens do not apply to server-to-server
        // calls from Meta, so exclude the entire webhook namespace.
        $middleware->validateCsrfTokens(except: [
            'webhooks/meta/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
