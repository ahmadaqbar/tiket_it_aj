<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Provider AI dibuat dari konfigurasi; test mengganti binding ini dengan FakeAiProvider.
        $this->app->bind(AiProvider::class, fn ($app) => $app->make(AiManager::class)->makeProvider());
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        Paginator::useTailwind();
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Deteksi lazy loading (N+1) saat development/test.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Paksa HTTPS hanya bila APP_URL memakai https — instalasi awal sebelum SSL aktif tetap dapat dipakai.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::define('access-it', fn (User $user) => $user->isItStaff());
        Gate::define('access-admin', fn (User $user) => $user->isItAdmin());

        // Lapisan kedua rate limit login (per IP), selain limit per akun di LoginRequest.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        // Anti-spam pembuatan tiket.
        RateLimiter::for('tickets', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
