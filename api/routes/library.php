<?php

declare(strict_types=1);

use Korrichess\Api\Core\AuthMiddleware;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Library\LibraryService;

return static function (Router $router): void {
    $library = new LibraryService();

    // B1 + B2 + B3 : liste, filtres, recherche texte
    $router->get('/api/v1/games', static function (Request $request) use ($library): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $userId = AuthMiddleware::userId($request);
        $q = $request->query;
        $filters = [];
        foreach (['source', 'opponent', 'opening', 'result', 'color', 'tournament', 'time_control', 'period_start', 'period_end', 'q', 'favorite', 'tag'] as $key) {
            if (isset($q[$key]) && $q[$key] !== '') {
                $filters[$key] = (string) $q[$key];
            }
        }
        return $library->list(
            $userId,
            $filters,
            (int) ($q['limit'] ?? 50),
            (int) ($q['offset'] ?? 0),
            (string) ($q['sort'] ?? 'played_at'),
            (string) ($q['order'] ?? 'desc'),
        );
    });

    // B1 : detail d'une partie
    $router->get('/api/v1/games/{id}', static function (Request $request) use ($library): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return $library->detail(AuthMiddleware::userId($request), (int) $request->pathParams['id']);
    });

    // B4 : notes, etiquettes, favori
    $router->patch('/api/v1/games/{id}', static function (Request $request) use ($library): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $input = $request->all();
        return $library->annotate(
            AuthMiddleware::userId($request),
            (int) $request->pathParams['id'],
            is_array($input) ? $input : [],
        );
    });

    // B5 : export PGN d'une selection
    $router->post('/api/v1/games/export', static function (Request $request) use ($library): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $input = $request->all();
        $ids = [];
        foreach (is_array($input['game_ids'] ?? null) ? $input['game_ids'] : [] as $id) {
            if (ctype_digit((string) $id)) {
                $ids[] = (int) $id;
            }
        }
        return $library->exportPgn(AuthMiddleware::userId($request), $ids);
    });

    // B6 : recherche par position
    $router->post('/api/v1/games/search-position', static function (Request $request) use ($library): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $fen = (string) $request->input('fen', '');
        return $library->searchByPosition(AuthMiddleware::userId($request), $fen);
    });
};
