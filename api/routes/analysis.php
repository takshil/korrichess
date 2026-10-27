<?php

declare(strict_types=1);

use Korrichess\Api\Core\AuthMiddleware;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Analysis\AnalysisService;
use Korrichess\Api\Domain\Analysis\HeuristicEngine;
use Korrichess\Api\Domain\Analysis\UciEngine;

/** Moteur configure : Stockfish externe si disponible, sinon moteur de secours. */
if (!function_exists('engine')) {
function engine(): Korrichess\Api\Domain\Analysis\EngineClient
{
    $path = (string) (getenv('KORRI_STOCKFISH_PATH') ?: 'stockfish');
    try {
        return new UciEngine($path);
    } catch (InvalidArgumentException) {
        return new HeuristicEngine();
    }
}
}

return static function (Router $router): void {
    $engine = engine();
    $analysis = new AnalysisService($engine);

    // A1 : rejeu d'une partie
    $router->get('/api/v1/games/{id}/replay', static function (Request $request) use ($analysis): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return $analysis->replay(AuthMiddleware::userId($request), (int) $request->pathParams['id']);
    });

    // A10 : analyse a la demande (asynchrone via file de taches)
    $router->post('/api/v1/games/{id}/analyse', static function (Request $request) use ($analysis): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $depth = (int) ($request->input('depth') ?? 12);
        return $analysis->request(AuthMiddleware::userId($request), (int) $request->pathParams['id'], $depth);
    });

    // A2-A6, A9 : resultat de la derniere analyse
    $router->get('/api/v1/games/{id}/analyse', static function (Request $request) use ($analysis): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return $analysis->result(AuthMiddleware::userId($request), (int) $request->pathParams['id']);
    });

    // A8 : essayer un autre coup depuis une position
    $router->post('/api/v1/games/{id}/explore', static function (Request $request) use ($analysis): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $input = $request->all();
        $move = (string) ($input['move_uci'] ?? '');
        if (!preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $move)) {
            return ['status' => 422, 'body' => ['error' => 'coup_invalide']];
        }
        return $analysis->explore(
            AuthMiddleware::userId($request),
            (int) $request->pathParams['id'],
            max(0, (int) ($input['ply'] ?? 0)),
            $move,
        );
    });
};

/** Moteur configure : Stockfish externe si disponible, sinon moteur de secours. */
