<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        // Root-relative asset URLs, so neither the Host header nor a TLS-terminating proxy affects them.
        Vite::createAssetPathsUsing(fn (string $path) => '/'.$path);

        // Each limit needs its own key, or they would share one counter. The
        // daily cap bounds how much Redis memory one client can fill with
        // week-long secrets.
        RateLimiter::for('secret-store', fn (Request $request) => [
            Limit::perMinute(20)->by('minute:'.self::client($request)),
            Limit::perDay(300)->by('day:'.self::client($request)),
        ]);
        RateLimiter::for('secret-reveal', fn (Request $request) => [
            Limit::perMinute(60)->by('minute:'.self::client($request)),
        ]);
    }

    /**
     * The rate limit bucket for a request. Anyone with IPv6 typically has a
     * whole /64 to rotate through, so a /64 counts as one client.
     */
    private static function client(Request $request): string
    {
        $ip = (string) $request->ip();

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return bin2hex(substr(inet_pton($ip), 0, 8)).'/64';
        }

        return $ip;
    }
}
