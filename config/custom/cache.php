<?php

return [
    'notification' => [
        'ttl_duration' => (60 * 60) * 24, // In days

        'pattern' => [
            'groups_count' => 'notification:*:groups_count',
        ]
    ],
    'user_stat' => [
        'ttl_duration' => (60 * 60) * 24, // In days

        'pattern' => [
            'followers_count' => 'user_stat:*:followers_count',

            'following_count' => 'user_stat:*:following_count',

            'notifications_count' => 'user_stat:*:notifications_count',
        ]
    ]
];
