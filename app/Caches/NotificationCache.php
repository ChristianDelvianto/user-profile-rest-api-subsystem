<?php

namespace App\Caches;

class NotificationCache extends BaseCache
{
    protected string $configKey = 'notification'; 

    public function getKey(string $statName, string $notificationUuid): string
    {
        return $this->buildKey(
            patternName: $statName,
            keyIdentifier: $notificationUuid
        );
    }
}
