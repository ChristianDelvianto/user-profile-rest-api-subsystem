<?php

return [
    'username' => [
        'max_length' => (int) env('USER_USERNAME_MAX_LENGTH', 24),

        'min_length' => (int) env('USER_USERNAME_MIN_LENGTH', 2)
    ],

    'name' => [
        'max_length' => (int) env('USER_NAME_MAX_LENGTH', 50),

        'min_length' => (int) env('USER_NAME_MIN_LENGTH', 2)
    ],

    'about' => [
        'max_length' => (int) env('USER_ABOUT_MAX_LENGTH', 210)
    ]
];
