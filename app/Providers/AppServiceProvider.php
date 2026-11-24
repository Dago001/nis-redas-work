<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use App\Services\JwtService;
use App\Services\ReportAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Files\MalwareScanner::class, function () {
            if (config('files.scanner') === 'clamav' && filled(config('files.clamav_socket'))) {
                return new \App\Services\Files\ClamAvSocketScanner((string) config('files.clamav_socket'));
            }

            return new \App\Services\Files\NullMalwareScanner();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if ($this->app->environment('production') && ! $this->app->runningInConsole()) {
            JwtService::assertConfigured();
        }

        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::define('viewPrsDashboard', static fn (User $user): bool => ReportAccess::isPrsViewer($user));
        Gate::define('manageConsolidated', static fn (User $user): bool => ReportAccess::canManageConsolidated($user));

        // Rate limiters for API and database-bound requests.
        // Use a persistent cache driver in production (database, redis, memcached).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('database', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
