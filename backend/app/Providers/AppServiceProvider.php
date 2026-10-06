<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use App\Services\SiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admin passes every permission check. Record-level policies still run for everyone else.
        Gate::before(fn (User $user) => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('login')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('website-forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Data every public website page needs (header, footer, forms).
        View::composer('website.*', function ($view) {
            $view->with([
                'site' => app(SiteSettings::class)->all(),
                'publicBranches' => Branch::where('is_active', true)->where('show_on_website', true)->orderBy('sort_order')->orderBy('name')->get(),
                'footerServices' => Service::onWebsite()->where('category', '!=', 'training')->limit(6)->get(['name', 'slug']),
            ]);
        });
    }
}
