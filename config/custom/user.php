<?php

return [
    'name' => [
        'max_length' => (int) env('USER_NAME_MAX_LENGTH', 50),

        'min_length' => (int) env('USER_NAME_MIN_LENGTH', 2)
    ]
];
