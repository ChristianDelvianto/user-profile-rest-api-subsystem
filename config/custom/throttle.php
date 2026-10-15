<?php

return [
    'api' => [
        'global' => [
            'throttle_name' => 'api.global',

            'decay_hours' => (int) env('THROTTLE_API_GLOBAL_DECAY_HOURS', 1),

            'max_attempts' => (int) env('THROTTLE_API_GLOBAL_MAX_ATTEMPTS', 100)
        ],

        'v1' => [
            'login' => [
                'throttle_name' => 'v1.login',

                'decay_minutes' => (int) env('THROTTLE_V1_LOGIN_DECAY_MINUTES', 15),

                'max_attempts' => (int) env('THROTTLE_V1_LOGIN_MAX_ATTEMPTS', 5)
            ]
        ]
    ]
];
