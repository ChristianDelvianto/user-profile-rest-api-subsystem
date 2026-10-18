<?php

namespace App\Http\RateLimiters\v1;

use Illuminate\Http\UploadedFile;
use Illuminate\Redis\Limiters\DurationLimiter;
use Illuminate\Support\Facades\Redis;

final class LoginRateLimiter
{
    protected function createDurationLimiterInstance(string $name): DurationLimiter
    {
        $decayMinutes = config('custom.throttle.api.v1.login.decay_minutes');
        $maxAttempts = config('custom.throttle.api.v1.login.max_attempts');

        return new DurationLimiter(
            Redis::connection(),
            $name,
            $maxAttempts,
            ($decayMinutes * 60)
        );
    }

    protected function transformEmailInputToString(mixed $email): string
    {
        if (is_array($email)) {
            return 'array';
        }
        
        if (is_null($email)) {
            return 'null';
        }

        if ($email instanceof UploadedFile) {
            return 'file';
        }

        if (is_object($email)) {
            return 'object';
        }

        return mb_strtolower(trim((string) $email), 'UTF-8');
    }

    public function clear(string $ip, mixed $email): void
    {
        $name = $this->hashedKey($ip, $email);

        $this->createDurationLimiterInstance($name)->clear();
    }
    
    public function hashedKey(string $ip, mixed $email): string
    {
        $throttleName = config('custom.throttle.api.v1.login.throttle_name');

        $email = $this->transformEmailInputToString($email);

        /**
         * If Illuminate\Routing\Middleware\ThrottleRequests::shouldHashKeys() has value true (default is true),
         * we need to write the key same way as the doc (key must match the key Laravel generates for the named limiter)
         * 
         * Ref:
         * 1. https://github.com/laravel/framework/blob/12.x/src/Illuminate/Routing/Middleware/ThrottleRequests.php#L349
         * 2. https://github.com/laravel/framework/blob/12.x/src/Illuminate/Routing/Middleware/ThrottleRequests.php#L134
         */
        return md5($throttleName . $this->key($ip, $email));
    }

    public function hit(string $ip, mixed $email): void
    {
        $name = $this->hashedKey($ip, $email);

        $this->createDurationLimiterInstance($name)->acquire();
    }

    public function isBlocked(string $ip, mixed $email): bool
    {
        $name = $this->hashedKey($ip, $email);

        return $this->createDurationLimiterInstance($name)->tooManyAttempts();
    }

    public function key(string $ip, mixed $email): string
    {
        return $ip . '|' . $this->transformEmailInputToString($email);
    }
}
