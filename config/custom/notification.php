<?php

use App\Enums\NotificationType;

return [
    'group_gap_interval' => (int) env('NOTIFICATION_GROUP_GAP_INTERVAL', 10), // In minutes

    'grouped_types' => [
        NotificationType::AccountFollower,
    ]
];
