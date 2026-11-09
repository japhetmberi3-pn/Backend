<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        /*
         * Routes d'authentification :
         * - 5 essais par minute pour un même email + IP
         * - 20 requêtes par minute pour une même IP
         */
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            $limits = [
                Limit::perMinute(20)->by('auth-ip:' . $request->ip()),
            ];

            if ($email !== '') {
                $limits[] = Limit::perMinute(5)
                    ->by('auth-email:' . $email . '|' . $request->ip());
            }

            return $limits;
        });
    }
}
