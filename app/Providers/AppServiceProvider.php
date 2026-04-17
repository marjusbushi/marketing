<?php

namespace App\Providers;

use App\Enums\MarketingPermissionEnum;
use App\Models\Content\ContentPost;
use App\Models\DailyBasketPost;
use App\Observers\ContentPostObserver;
use App\Observers\DailyBasketPostObserver;
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
        $this->registerDailyBasketSync();
    }

    /**
     * Keep ContentPost and DailyBasketPost in sync via observers.
     * SyncGuard prevents infinite loops when one observer triggers the other.
     */
    protected function registerDailyBasketSync(): void
    {
        ContentPost::observe(ContentPostObserver::class);
        DailyBasketPost::observe(DailyBasketPostObserver::class);
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
