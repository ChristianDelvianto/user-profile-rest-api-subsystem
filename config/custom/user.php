<?php

return [
    'name' => [
        'max_length' => (int) env('USER_MAX_NAME_LENGTH', 50),

        'min_length' => (int) env('USER_MIN_NAME_LENGTH', 2)
    ]
];
