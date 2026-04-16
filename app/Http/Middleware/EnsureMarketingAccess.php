<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks if the authenticated user has access
 * to the Marketing application via the shared app-scoped ACL.
 */
class EnsureMarketingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasMarketingAccess()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Nuk keni akses ne Marketing.'], 403);
            }

            abort(403, 'Nuk keni akses ne Marketing.');
        }

        return $next($request);
    }
}
