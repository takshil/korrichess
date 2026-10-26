<?php

declare(strict_types=1);

require_once __DIR__ . '/run_lib.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Migration;
use Korrichess\Api\Core\Queue;
use Korrichess\Api\Domain\Auth\AuthService;
use Korrichess\Api\Domain\Games\GameRepository;
use Korrichess\Api\Domain\Sync\InMemoryPlatformClient;
use Korrichess\Api\Domain\Sync\RateLimiter;
use Korrichess\Api\Domain\Sync\SyncService;

freshDb();
$auth = new AuthService();
$auth->register('sync@example.com', 'pw123456', null, null);

$game1 = '[Event "Lichess blitz"]' . "\n" . '[White "bob"]' . "\n" . '[Black "alice"]' . "\n" . '[UTCDate "2025.03.10"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 *';
$game2 = '[Event "Lichess blitz"]' . "\n" . '[White "alice"]' . "\n" . '[Black "bob"]' . "\n" . '[UTCDate "2025.03.12"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. d4 d5 2. c4 e6 *';
$game3 = '[Event "Lichess rapid"]' . "\n" . '[TimeControl "600"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 *';

$client = new InMemoryPlatformClient([
    ['external_id' => 'g1', 'pgn' => $game1, 'played_at' => '2025-03-10T20:00:00Z'],
    ['external_id' => 'g2', 'pgn' => $game2, 'played_at' => '2025-03-12T21:00:00Z'],
    ['external_id' => 'g3', 'pgn' => $game3, 'played_at' => '2025-03-13T22:00:00Z'],
]);

$service = new SyncService(['lichess' => $client]);

// L1/L2 : premier sync complet
$r = $service->sync(1, 'lichess', 'alice');
check('sync: 3 parties importées', $r['status'] === 200 && $r['body']['imported'] === 3);

// L3 : sync incrémentale — rien de nouveau
$r2 = $service->sync(1, 'lichess', 'alice');
check('sync: incrémentale sans nouveauté', $r2['body']['imported'] === 0);
check('sync: état mémorisé', $service->state(1, 'lichess', 'alice')['last_game_at'] === '2025-03-13T22:00:00Z');

// L6 : doublons détectés et ignorés
$client->games[] = ['external_id' => 'g2', 'pgn' => $game2, 'played_at' => '2025-03-12T21:00:00Z'];
$r3 = $service->sync(1, 'lichess', 'alice');
check('sync: doublon ignoré', $r3['body']['duplicates_ignores'] === 1 && $r3['body']['imported'] === 0);

// Nouvelle partie importée
$newGame = '[Event "Lichess blitz"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. f4 e5 2. g4 Qh4# 0-1';
$client->games[] = ['external_id' => 'g4', 'pgn' => $newGame, 'played_at' => '2025-03-20T10:00:00Z'];
$r4 = $service->sync(1, 'lichess', 'alice');
check('sync: nouvelle partie importée', $r4['body']['imported'] === 1);
check('sync: curseur avancé', $service->state(1, 'lichess', 'alice')['last_game_at'] === '2025-03-20T10:00:00Z');

// L5 : filtres de cadence et période
$r5 = $service->sync(1, 'lichess', 'alice', ['time_controls' => ['600']]);
check('sync: filtre cadence sans résultat nouveau', $r5['body']['imported'] === 0);
$client->games[] = ['external_id' => 'g5', 'pgn' => $game3, 'played_at' => '2025-03-25T10:00:00Z'];
$r6 = $service->sync(1, 'lichess', 'alice', ['time_controls' => ['600']]);
check('sync: filtre cadence importe les parties correspondantes', $r6['body']['imported'] === 1);

// Plateforme inconnue
check('sync: plateforme inconnue 422', $service->sync(1, 'foo', 'alice')['status'] === 422);

// PGN invalide ignoré (aucune perte, mais pas de crash)
$client->games[] = ['external_id' => 'g6', 'pgn' => 'pas un pgn', 'played_at' => '2025-03-26T10:00:00Z'];
$r7 = $service->sync(1, 'lichess', 'alice');
check('sync: pgn invalide ignoré proprement', $r7['status'] === 200 && $r7['body']['imported'] === 0);

// L7 : sync automatique en file
$queue = new Queue();
$serviceQ = new SyncService(['lichess' => $client], $queue);
$serviceQ->scheduleAutoSync(1, 'lichess', 'alice');
$job = $queue->pop();
check('sync: job programmé en file', $job !== null && $job['type'] === 'sync' && json_decode($job['payload'], true)['username'] === 'alice');

// RateLimiter
$limiter = new RateLimiter(3);
$limiter->wait('lichess');
$limiter->wait('lichess');
$limiter->wait('lichess');
check('ratelimiter: 3 appels passés', true);

// Vérification de persistance des métadonnées
$game = Database::one("SELECT source, opponent, color FROM games WHERE external_id = 'g1'");
check('sync: métadonnées extraites du PGN', $game !== null && $game['source'] === 'lichess' && $game['opponent'] === 'bob');

report();
