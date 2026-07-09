<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Register the public-endpoint rate limiters. All are keyed by client IP.
     * Laravel 12 ships no default API throttle, so without these the endpoints
     * are unbounded — `/api/live-weather` in particular proxies OpenWeatherMap
     * with our key, and `/contact` writes a row + sends mail per hit.
     */
    public function boot(): void
    {
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('weather-api', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
