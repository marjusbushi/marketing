<?php

namespace App\Http\Middleware;

use App\Enums\MarketingPermissionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission check.
 *
 * Usage in routes:
 *   ->middleware('marketing.permission:content_planner.view')
 *   ->middleware('marketing.permission:analytics.view,analytics.manage') // any of
 */
class CheckMarketingPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Nuk jeni i identifikuar.');
        }

        // If any of the given permissions match, allow through
        foreach ($permissions as $permission) {
            if ($user->hasMarketingPermission($permission)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Nuk keni lejen e nevojshme.',
            ], 403);
        }

        abort(403, 'Nuk keni lejen e nevojshme.');
    }
}
