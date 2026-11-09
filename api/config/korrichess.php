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

    'analysis' => [
        // Chemin du binaire UCI (Stockfish) — jamais embarqué ni distribué
        // par le dépôt (ADR-002). En son absence, le moteur heuristique
        // déterministe prend le relais.
        'stockfish_path' => env('KORRI_STOCKFISH_PATH', 'stockfish'),
        // Profondeur par défaut des analyses (bornée 4–20).
        'default_depth' => (int) env('ANALYSIS_DEFAULT_DEPTH', 12),
        // Temps alloué par position au moteur UCI, en millisecondes.
        'engine_movetime_ms' => (int) env('ANALYSIS_ENGINE_MOVETIME_MS', 1000),
    ],
];
