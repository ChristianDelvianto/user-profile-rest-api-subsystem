<?php

namespace App\Caches;

class UserStatCache extends BaseCache
{
    protected string $configKey = 'user_stat'; 

    public function getKey(string $statName, int $userId): string
    {
        return $this->buildKey(
            patternName: $statName,
            keyIdentifier: $userId
        );
    }
}
