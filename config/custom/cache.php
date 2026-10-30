<?php

return [
    'notification' => [
        /**
         * When a user trigger an action that put them into grouped notification,
         * instead of relying with DB to fetch the notification everytime, we use cache.
         * 
         * Example action type:
         * 1. User following other user
         * 2. User liking other users post
         * 
         * Example usage:
         * 1. CreateAccountFollowerTypeOrAttachNewUserJob
         */
        'last_group' => [
            'pattern' => [
                /** Replace * with user's id */
                'account_follower' => 'user:*:notification:account_follower',

                /** Replace * with post's id */
                'post_like' => 'post:*:notification:post_like'
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
            /** Replace * with notification's uuid */
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
            /** Replace * with user's id */
            'followers_count' => 'user_stat:*:followers_count',

            /** Replace * with user's id */
            'following_count' => 'user_stat:*:following_count',

            /** Replace * with user's id */
            'notifications_count' => 'user_stat:*:notifications_count',
        ]
    ]
];
