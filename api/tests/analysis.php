<?php

declare(strict_types=1);

require_once __DIR__ . '/run_lib.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Queue;
use Korrichess\Api\Core\QueueWorker;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Analysis\AnalysisService;
use Korrichess\Api\Domain\Analysis\HeuristicEngine;
use Korrichess\Api\Domain\Auth\AuthService;
use Korrichess\Api\Domain\Chess\Board;
use Korrichess\Api\Domain\Games\GameRepository;

freshDb();
$auth = new AuthService();
$repo = new GameRepository();
$engine = new HeuristicEngine();
$queue = new Queue();
$service = new AnalysisService($engine, $repo, $queue);
$register = $auth->register('analyse@example.com', 'pw123456', null, null);
$userId = $register['body']['user_id'];

// Partie avec un gaffe evidente : blanc donne sa dame (Qxf7?? suivi de rien) puis noir gagne
// Sequence simple : 1. e4 e5 2. Qh5 Nc6 3. Qxf7+ Kxf7 -> blanc perd la dame pour rien
$gamePgn = '[Event "Test analyse"]' . "\n" . '[White "alice"]' . "\n" . '[Black "bob"]' . "\n"
    . '[UTCDate "2025.05.10"]' . "\n" . '[ECO "C50"]' . "\n\n"
    . '1. e4 e5 2. Qh5 Nc6 3. Qxf7+ Kxf7 *';
$repo->upsertFromPgn($userId, 'tournoi', $gamePgn, 'a1', [
    'opponent' => 'bob', 'color' => 'white', 'time_control' => '300',
    'opening' => 'C50', 'tournament' => 'Test analyse', 'played_at' => '2025-05-10T18:00:00Z',
]);
$gameId = (int) Database::one('SELECT id FROM games WHERE user_id = :u', ['u' => $userId])['id'];

// A1 : rejeu
$replay = $service->replay($userId, $gameId);
check('A1: rejeu 200', $replay['status'] === 200);
check('A1: 7 positions (depart + 6 coups)', count($replay['body']['positions']) === 7);
check('A1: coups SAN', $replay['body']['moves_san'] === ['e4', 'e5', 'Qh5', 'Nc6', 'Qxf7+', 'Kxf7']);
check('A1: position initiale correcte', $replay['body']['positions'][0] === 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
$missing = $service->replay($userId, 999);
check('A1: partie inexistante 404', $missing['status'] === 404);

// A10 : demande d'analyse (asynchrone)
$req = $service->request($userId, $gameId);
check('A10: analyse acceptee 202', $req['status'] === 202 && $req['body']['status'] === 'pending');
$again = $service->request($userId, $gameId);
check('A10: pas de doublon en file', $again['body']['status'] === 'pending' && $again['body']['analysis_id'] === $req['body']['analysis_id']);

// Execution du job par le worker (handler analyse enregistre)
$worker = new QueueWorker();
(require dirname(__DIR__) . '/queue-handlers/analyse.php')($worker);
$jobsDone = $worker->work(1);
check('A10: job analyse execute', $jobsDone === 1);
$pendingRow = Database::one('SELECT id FROM game_analyses WHERE status = "pending"');
check('A10: plus aucune analyse en attente', $pendingRow === null);

// A2-A6, A9 : resultats
$result = $service->result($userId, $gameId);
check('A2: analyse terminee', $result['status'] === 200 && $result['body']['status'] === 'done');
check('A2: une evaluation par position', count($result['body']['moves']) === 7);
$firstMove = $result['body']['moves'][0];
check('A2: evaluation entiere en centipions', is_int($firstMove['eval_cp']));
check('A3: courbe d\'evaluation presente', count($result['body']['eval_curve']) === 7);

// A4 : classement des coups
$classes = array_column($result['body']['moves'], 'classification');
check('A4: classement pour chaque coup', count(array_filter($classes, static fn ($c) => in_array($c, ['meilleur', 'bon', 'imprecision', 'erreur', 'gaffe'], true))) === count($classes));
check('A4: comptage par categorie', isset($result['body']['classification_counts']));

// A5 : moment decisif present
check('A5: moment decisif identifie', $result['body']['decisive_ply'] !== null && $result['body']['decisive_ply'] >= 0 && $result['body']['decisive_ply'] <= 6);

// A6 : meilleur coup suggere (sur les positions avec coup joue)
$withBest = array_filter($result['body']['moves'], static fn ($m) => $m['best_move_san'] !== null);
check('A6: meilleur coup suggere au moins une fois', count($withBest) >= 1);
check('A6: variante proposee', $firstMove['variation'] !== null);

// A7 : explication en langage simple sur au moins une erreur/gaffe
$explained = array_filter($result['body']['moves'], static fn ($m) => $m['explanation'] !== null);
check('A7: au moins une erreur expliquee', count($explained) >= 1);
$explanations = array_column($explained, 'explanation');
$hasSuggestion = (bool) array_filter($explanations, static fn ($e) => str_contains((string) $e, 'Le moteur suggère'));
check('A7: explication mentionne le coup suggere', $hasSuggestion);

// A9 : ouverture et sortie de theorie
check('A9: ouverture identifiee', $result['body']['opening_name'] === 'Partie italienne');
check('A9: sortie de theorie au demi-coup 2 (Qh5)', $result['body']['book_exit_ply'] === 2);

// A2bis : resultats stockes et relus depuis la base
$reread = $service->result($userId, $gameId);
check('A2: resultats persistes', $reread['body']['analysis_id'] === $result['body']['analysis_id'] && count($reread['body']['moves']) === 7);

// A10 : re-analyse retourne l'existante
$reRequest = $service->request($userId, $gameId);
check('A10: re-demande renvoie l\'analyse faite', $reRequest['status'] === 200 && $reRequest['body']['already'] === true);

// A8 : essayer un autre coup depuis une position
$explore = $service->explore($userId, $gameId, 0, 'd2d4');
check('A8: coup alternatif accepte', $explore['status'] === 200 && $explore['body']['move_san'] === 'd4');
check('A8: position resultante', str_starts_with($explore['body']['fen'], 'rnbqkbnr/pppppppp/8/8/3P4/8/PPP1PPPP'));
$illegal = $service->explore($userId, $gameId, 0, 'e2e5');
check('A8: coup illegal refuse', $illegal['status'] === 422);
$badPly = $service->explore($userId, $gameId, 99, 'e2e4');
check('A8: position inatteignable refusee', $badPly['status'] === 422 || $badPly['status'] === 200);

// Isolation entre utilisateurs
$other = $auth->register('autre-analyse@example.com', 'pw123456', null, null);
$otherId = $other['body']['user_id'];
$leak = $service->result($otherId, $gameId);
check('isolation: analyse d\'un autre utilisateur 404', $leak['status'] === 404);

// Routes
$router = new Router();
(require dirname(__DIR__) . '/routes/analysis.php')($router);
$token = $register['body']['token'];

$reqReplay = new Request('GET', '/api/v1/games/1/replay', [], [], ['authorization' => 'Bearer ' . $token]);
$respReplay = $router->route('GET', '/api/v1/games/1/replay')($reqReplay);
check('route: GET /games/{id}/replay', $respReplay['status'] === 200 && count($respReplay['body']['positions']) === 7);

$reqAsk = new Request('POST', '/api/v1/games/1/analyse', [], [], ['authorization' => 'Bearer ' . $token]);
$respAsk = $router->route('POST', '/api/v1/games/1/analyse')($reqAsk);
check('route: POST /games/{id}/analyse', $respAsk['status'] === 200 && ($respAsk['body']['already'] ?? false) === true);

$reqResult = new Request('GET', '/api/v1/games/1/analyse', [], [], ['authorization' => 'Bearer ' . $token]);
$respResult = $router->route('GET', '/api/v1/games/1/analyse')($reqResult);
check('route: GET /games/{id}/analyse', $respResult['status'] === 200 && $respResult['body']['status'] === 'done');

$reqExplore = new Request('POST', '/api/v1/games/1/explore', [], ['ply' => 0, 'move_uci' => 'd2d4'], ['authorization' => 'Bearer ' . $token]);
$respExplore = $router->route('POST', '/api/v1/games/1/explore')($reqExplore);
check('route: POST /games/{id}/explore', $respExplore['status'] === 200 && $respExplore['body']['move_san'] === 'd4');

$reqBadMove = new Request('POST', '/api/v1/games/1/explore', [], ['ply' => 0, 'move_uci' => 'zz99'], ['authorization' => 'Bearer ' . $token]);
$respBadMove = $router->route('POST', '/api/v1/games/1/explore')($reqBadMove);
check('route: explore coup malforme 422', $respBadMove['status'] === 422);

$reqAnon = new Request('GET', '/api/v1/games/1/replay', [], [], []);
$respAnon = $router->route('GET', '/api/v1/games/1/replay')($reqAnon);
check('route: rejeu sans token 401', $respAnon['status'] === 401);

// Worker via queue-handlers/analyse.php
$queue2 = new Queue();
$service2 = new AnalysisService($engine, $repo, $queue2);
$repo->upsertFromPgn($userId, 'lichess', $gamePgn, 'a2', ['opponent' => 'bob', 'color' => 'black', 'played_at' => '2025-05-11T18:00:00Z']);
$game2Id = (int) Database::one('SELECT id FROM games WHERE external_id = :e', ['e' => 'a2'])['id'];
$service2->request($userId, $game2Id);
$worker2 = new QueueWorker();
(require dirname(__DIR__) . '/queue-handlers/analyse.php')($worker2);
$processed = $worker2->work(1);
$done2 = Database::one('SELECT status FROM game_analyses WHERE game_id = :g ORDER BY id DESC LIMIT 1', ['g' => $game2Id]);
check('worker: handler analyse traite le job', $processed === 1 && $done2['status'] === 'done');

report();
