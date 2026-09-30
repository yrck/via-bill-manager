<?php

namespace App\Providers;

use App\Access\BillingAccess;
use App\Access\ManagerAccess;
use App\Http\Middleware\RequireLocalDemo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // Coolify terminates TLS before the request reaches PHP-FPM.
        if ($this->app->environment('production') && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        Livewire::addPersistentMiddleware([RequireLocalDemo::class]);
        Gate::define('manage-customers', fn (User $user) => ManagerAccess::allowed($user));
        Gate::define('view-bill', fn (User $user, int $organizationId, int $expectedBillId) => app(BillingAccess::class)->canViewBill($user, $organizationId, $expectedBillId)
        );
        Gate::define('review-bill', fn (User $user, int $organizationId, int $expectedBillId) => app(BillingAccess::class)->canReviewBill($user, $organizationId, $expectedBillId)
        );
    }
}
