<?php

namespace App\Providers;

use App\Http\RateLimiters\v1\LoginRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

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
        RateLimiter::for(config('custom.throttle.api.global.throttle_name'), function (Request $request) {
            $decayHours = config('custom.throttle.api.global.decay_hours');
            $maxAttempts = config('custom.throttle.api.global.max_attempts');

            $identifier = $request->user()?->id ?? $request->ip();

            // Per-endpoint limit, so heavy usage of one resource doesn't block other API resources.
            $key = $identifier . '|' . $request->url();

            return Limit::perHour($maxAttempts, $decayHours)->by($key);
        });

        RateLimiter::for(config('custom.throttle.api.v1.login.throttle_name'), function (Request $request) {
            $decayMinutes = config('custom.throttle.api.v1.login.decay_minutes');
            $maxAttempts = config('custom.throttle.api.v1.login.max_attempts');

            $requestIp = $request->ip();
            $email = $request->input('email', null);

            $key = app(abstract: LoginRateLimiter::class)->key(ip: $requestIp, email: $email);

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                    ->by($key)
                    // Only if status code equal to 422
                    ->after(fn (Response $response) => $response->getStatusCode() === 422);
        });
    }
}
