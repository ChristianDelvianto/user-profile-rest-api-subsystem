<?php

return [
    'notification' => [
        /**
         * The last notification of grouped notification
         * 
         * Use case:
         * 1. Job / Queue
         */
        'grouped_notification' => [
            'pattern' => [
                /** Replace * with user id */
                'account_follower' => 'user:notification:*:account_follower',
            ]
        ],

        'ttl_duration' => (60 * 60) * 24, // In seconds

        /**
         * Use case:
         * 1. Job / Queue
         * 2. Cron
         * 3. Cache keys
          */
        'pattern' => [
            /** Replace * with notification uuid */
            'groups_count' => 'notification:*:groups_count',
        ]
    ],

    'user_stat' => [
        'ttl_duration' => (60 * 60) * 24, // In seconds

        /**
         * Use case:
         * 1. Job / Queue
         * 2. Cron
         * 3. Cache keys
         */
        'pattern' => [
            /** Replace * with user id */
            'followers_count' => 'user_stat:*:followers_count',

            'following_count' => 'user_stat:*:following_count',

            'notifications_count' => 'user_stat:*:notifications_count',
        ]
    ]
];
