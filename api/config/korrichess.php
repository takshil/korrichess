<?php

return [
    'routes' => [
        'prefix' => env('API_PREFIX', 'api'),
        'version' => 'v1',
    ],

    'auth' => [
        // Durée de vie des jetons d'API Sanctum, en jours (C1).
        'token_ttl_days' => (int) env('AUTH_TOKEN_TTL_DAYS', 30),
    ],
];
