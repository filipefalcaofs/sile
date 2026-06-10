<?php

return [
    'security' => [
        'password' => [
            'min_length' => 8,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => false,
        ],
        'login' => ['max_attempts' => 5],
        'password_reset_expire' => 60,
    ],
    'ui' => [
        'access_history' => ['per_page' => 15],
    ],
];
