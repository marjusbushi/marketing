<?php

namespace App\Providers;

use App\Enums\MarketingPermissionEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMarketingGates();
    }

    /**
     * Register Laravel gates for every marketing permission.
     *
     * This allows using Gate::allows('content_planner.view') or
     * @can('content_planner.view') in Blade, and $this->authorize()
     * in controllers.
     *
     * Each gate checks the user's marketing permissions via the
     * shared ACL (HasMarketingRole trait → DIS database).
     */
    protected function registerMarketingGates(): void
    {
        foreach (MarketingPermissionEnum::cases() as $permission) {
            Gate::define($permission->value, function ($user) use ($permission) {
                return $user->hasMarketingPermission($permission->value);
            });
        }
    }
}
