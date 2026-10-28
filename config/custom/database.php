<?php

return [
    'transaction' => [
        'attempts' => (int) env('DB_TRANSACTION_ATTEMPTS', 3)
    ],
];
