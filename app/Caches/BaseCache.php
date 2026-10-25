<?php

namespace App\Caches;

class BaseCache
{
    protected string $configKey;

    protected function buildKey(int|string $patternName, int|string $keyIdentifier): string
    {
        $patternName = config("custom.cache.{$this->configKey}.pattern.{$patternName}");

        return str_replace(
            search: '*',
            replace: $keyIdentifier,
            subject: $patternName ?? ''
        );
    }
}
