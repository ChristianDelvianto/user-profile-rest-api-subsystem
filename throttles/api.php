<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

// Global
RateLimiter::for(
    config('custom.throttle.api.global.throttle_name'),
    function (Request $request) {
        $decayHours = config('custom.throttle.api.global.decay_hours');
        $maxAttempts = config('custom.throttle.api.global.max_attempts');

        $identifier = $request->user()?->id ?? $request->ip();

        // Per-endpoint limit, so heavy usage of one resource doesn't block other API resources.
        $key = $identifier . '|' . $request->url();

        return Limit::perHour($maxAttempts, $decayHours)->by($key);
    }
);

/**
 * You can add more for global api below, and kindly to use config variables (see config/custom/throttle.php)
 */

require __DIR__ . '/api/v1.php';
