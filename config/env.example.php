<?php

declare(strict_types=1);

return [
    'environment' => 'local',

    'allowed_origins' => [
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'https://inaya-survey.vercel.app',
    ],

    'database' => [
        'host' => 'localhost',
        'name' => 'inaya_survey',
        'username' => 'root',
        'password' => '',
    ],
];