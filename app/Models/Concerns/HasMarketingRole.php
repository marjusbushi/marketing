<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait HasMarketingRole
 *
 * Manages marketing access via the shared app-scoped ACL in the DIS database.
 * No legacy `marketing_role_id` — all access flows through `apps` / `app_roles` / `user_app_roles`.
 *
 * Tables live in the DIS database:
 *   apps, app_roles, app_role_permissions, user_app_roles
 */
trait HasMarketingRole
{
    /**
     * Check if user has access to the Marketing application.
     *
     * Looks for an active app_role under the `marketing` app slug.
     */
    public function hasMarketingAccess(): bool
    {
        return $this->hasSharedMarketingAppRole();
    }

    /**
     * Get all marketing-scoped permissions for this user.
     *
     * Cached for 24 hours — cache is invalidated by DIS when
     * app_roles / app_role_permissions / user_app_roles change.
     */
    public function marketingPermissions(): array
    {
        if (! $this->hasMarketingAccess()) {
            return [];
        }

        return Cache::remember("marketing_permissions:user:{$this->id}", 86400, function () {
            return $this->sharedMarketingPermissions();
        });
    }

    /**
     * Check if user has a specific marketing permission.
     */
    public function hasMarketingPermission(string $permission): bool
    {
        return in_array($permission, $this->marketingPermissions(), true);
    }

    /**
     * Check if user has any of the given marketing permissions.
     */
    public function hasAnyMarketingPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasMarketingPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear cached marketing permissions for this user.
     */
    public function clearMarketingPermissionCache(): void
    {
        Cache::forget("marketing_permissions:user:{$this->id}");
    }

    /**
     * Does this user have an active app_role under the `marketing` app?
     */
    protected function hasSharedMarketingAppRole(): bool
    {
        return DB::connection('dis')
            ->table('user_app_roles')
            ->join('app_roles', 'app_roles.id', '=', 'user_app_roles.app_role_id')
            ->join('apps', 'apps.id', '=', 'app_roles.app_id')
            ->where('user_app_roles.user_id', $this->id)
            ->where('apps.slug', 'marketing')
            ->where('apps.status', true)
            ->where('app_roles.status', true)
            ->exists();
    }

    /**
     * Fetch distinct marketing permissions from the shared ACL.
     */
    protected function sharedMarketingPermissions(): array
    {
        return DB::connection('dis')
            ->table('app_role_permissions')
            ->join('app_roles', 'app_roles.id', '=', 'app_role_permissions.app_role_id')
            ->join('apps', 'apps.id', '=', 'app_roles.app_id')
            ->join('user_app_roles', 'user_app_roles.app_role_id', '=', 'app_roles.id')
            ->where('user_app_roles.user_id', $this->id)
            ->where('apps.slug', 'marketing')
            ->where('apps.status', true)
            ->where('app_roles.status', true)
            ->distinct()
            ->pluck('app_role_permissions.permission')
            ->values()
            ->all();
    }
}
