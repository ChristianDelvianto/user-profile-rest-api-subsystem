<?php

use App\Http\RateLimiters\v1\LoginRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

// Login throttle
RateLimiter::for(
    config('custom.throttle.api.v1.login.throttle_name'),
    function (Request $request) {
        $decayMinutes = config('custom.throttle.api.v1.login.decay_minutes');
        $maxAttempts = config('custom.throttle.api.v1.login.max_attempts');

        $requestIp = $request->ip();
        $email = $request->input('email', null);

        $key = app(abstract: LoginRateLimiter::class)->key(ip: $requestIp, email: $email);

        return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($key)
                // Only if response status code equal to 422
                ->after(fn (Response $response) => $response->getStatusCode() === 422);
    }
);

/**
 * You can add more for api v1 below, and kindly to use config variables (see config/custom/throttle.php)
 */
