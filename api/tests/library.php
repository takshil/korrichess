<?php

declare(strict_types=1);

require_once __DIR__ . '/run_lib.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Auth\AuthService;
use Korrichess\Api\Domain\Chess\Board;
use Korrichess\Api\Domain\Games\GameRepository;
use Korrichess\Api\Domain\Library\LibraryService;

freshDb();
$auth = new AuthService();
$repo = new GameRepository();
$library = new LibraryService();
$register = $auth->register('biblio@example.com', 'pw123456', null, null);
$userId = $register['body']['user_id'];

$gameAlice = '[Event "Open de Rennes"]' . "\n" . '[White "alice"]' . "\n" . '[Black "carol"]' . "\n"
    . '[UTCDate "2025.04.10"]' . "\n" . '[TimeControl "300"]' . "\n" . '[ECO "C50"]' . "\n\n"
    . '1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5 *';
$gameBlitz = '[Event "Lichess blitz"]' . "\n" . '[White "dave"]' . "\n" . '[Black "alice"]' . "\n"
    . '[UTCDate "2025.04.12"]' . "\n" . '[TimeControl "60"]' . "\n" . '[ECO "B00"]' . "\n\n"
    . '1. d4 d5 2. c4 e6 *';
$gameRapid = '[Event "Lichess rapid"]' . "\n" . '[White "alice"]' . "\n" . '[Black "eve"]' . "\n"
    . '[UTCDate "2025.04.15"]' . "\n" . '[TimeControl "600"]' . "\n" . '[ECO "C50"]' . "\n\n"
    . '1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5 4. b4 Bxb4 *';

$g1 = $repo->upsertFromPgn($userId, 'tournoi', $gameAlice, 't1', ['opponent' => 'carol', 'color' => 'white', 'time_control' => '300', 'opening' => 'C50', 'tournament' => 'Open de Rennes', 'played_at' => '2025-04-10T18:00:00Z']);
$g2 = $repo->upsertFromPgn($userId, 'lichess', $gameBlitz, 't2', ['opponent' => 'dave', 'color' => 'black', 'time_control' => '60', 'opening' => 'B00', 'played_at' => '2025-04-12T20:00:00Z']);
$g3 = $repo->upsertFromPgn($userId, 'lichess', $gameRapid, 't3', ['opponent' => 'eve', 'color' => 'white', 'time_control' => '600', 'opening' => 'C50', 'played_at' => '2025-04-15T21:00:00Z']);
check('bibliotheque: 3 parties importees', $g1 && $g2 && $g3);

// B1 : toutes les parties avec source
$list = $library->list($userId);
check('B1: 3 parties listees', $list['status'] === 200 && $list['body']['total'] === 3);
$sources = array_column($list['body']['games'], 'source');
check('B1: source indiquee', in_array('tournoi', $sources, true) && in_array('lichess', $sources, true));

// B2 : filtres
$byOpponent = $library->list($userId, ['opponent' => 'dave']);
check('B2: filtre adversaire', $byOpponent['body']['total'] === 1 && $byOpponent['body']['games'][0]['opponent'] === 'dave');
$byOpening = $library->list($userId, ['opening' => 'C50']);
check('B2: filtre ouverture', $byOpening['body']['total'] === 2);
$byPeriod = $library->list($userId, ['period_start' => '2025-04-11T00:00:00Z']);
check('B2: filtre periode', $byPeriod['body']['total'] === 2);
$bySource = $library->list($userId, ['source' => 'lichess']);
check('B2: filtre source', $bySource['body']['total'] === 2);
$byTc = $library->list($userId, ['time_control' => '600']);
check('B2: filtre cadence', $byTc['body']['total'] === 1);
$byColor = $library->list($userId, ['color' => 'black']);
check('B2: filtre couleur', $byColor['body']['total'] === 1 && $byColor['body']['games'][0]['opponent'] === 'dave');
$sorted = $library->list($userId, [], sort: 'played_at', order: 'asc');
check('B2: tri ascendant', $sorted['body']['games'][0]['opponent'] === 'carol');
$page = $library->list($userId, [], limit: 2, offset: 1);
check('B2: pagination', count($page['body']['games']) === 2 && $page['body']['total'] === 3);

// B3 : recherche texte
$search = $library->list($userId, ['q' => 'rennes']);
check('B3: recherche texte tournoi', $search['body']['total'] === 1 && $search['body']['games'][0]['tournament'] === 'Open de Rennes');
$searchOpp = $library->list($userId, ['q' => 'eve']);
check('B3: recherche texte adversaire', $searchOpp['body']['total'] === 1);

// Detail
$detail = $library->detail($userId, 1);
check('detail: PGN inclus', $detail['status'] === 200 && str_contains($detail['body']['pgn'], 'Open de Rennes'));
$missing = $library->detail($userId, 999);
check('detail: partie inexistante 404', $missing['status'] === 404);

// B4 : notes, etiquettes, favoris
$ann = $library->annotate($userId, 1, ['personal_note' => 'Belle attaque du fou', 'tags' => ['favori', 'italienne'], 'favorite' => true]);
check('B4: annotation enregistree', $ann['status'] === 200 && $ann['body']['personal_note'] === 'Belle attaque du fou' && $ann['body']['favorite'] === true);
check('B4: etiquettes rangees', $ann['body']['tags'] === ['favori', 'italienne']);
$badNote = $library->annotate($userId, 1, ['personal_note' => str_repeat('x', 2001)]);
check('B4: note trop longue refusee', $badNote['status'] === 422);
$badTags = $library->annotate($userId, 1, ['tags' => 'pas-un-tableau']);
check('B4: etiquettes invalides refusees', $badTags['status'] === 422);
$favOnly = $library->list($userId, ['favorite' => true]);
check('B4: filtre favoris', $favOnly['body']['total'] === 1);
$byTag = $library->list($userId, ['tag' => 'italienne']);
check('B4: filtre etiquette', $byTag['body']['total'] === 1);
$none = $library->annotate($userId, 1, []);
check('B4: rien a mettre a jour 422', $none['status'] === 422);

// B5 : export PGN
$export = $library->exportPgn($userId, [1, 2]);
check('B5: export deux parties', $export['status'] === 200 && $export['body']['count'] === 2);
check('B5: export contient les PGN', substr_count($export['body']['pgn'], '[Event') === 2 && str_contains($export['body']['pgn'], 'KorriNote "Belle attaque du fou"'));
$empty = $library->exportPgn($userId, []);
check('B5: selection vide 422', $empty['status'] === 422);

// B6 : recherche par position
$board = Board::start();
$board->applySan('e4');
$board->applySan('e5');
$board->applySan('Nf3');
$board->applySan('Nc6');
$board->applySan('Bc4');
$fen = $board->fen();
$pos = $library->searchByPosition($userId, $fen);
check('B6: position trouvee dans 2 parties', $pos['status'] === 200 && $pos['body']['total'] === 2);
$plausible = array_column($pos['body']['games'], 'ply');
check('B6: demi-coup correct', in_array(5, $plausible, true));
$badFen = $library->searchByPosition($userId, 'pas un fen');
check('B6: FEN invalide 422', $badFen['status'] === 422);
$otherBoard = Board::start();
$otherBoard->applySan('d4');
$noPos = $library->searchByPosition($userId, $otherBoard->fen() . ' w');
check('B6: position absente 0 resultat', $noPos['body']['total'] === 0);

// Isolation entre utilisateurs
$other = $auth->register('autre@example.com', 'pw123456', null, null);
$otherId = $other['body']['user_id'];
$leak = $library->list($otherId);
check('isolation: bibliotheque vide pour un autre utilisateur', $leak['body']['total'] === 0);
$leakDetail = $library->detail($otherId, 1);
check('isolation: detail d\'un autre utilisateur 404', $leakDetail['status'] === 404);
$leakExport = $library->exportPgn($otherId, [1, 2]);
check('isolation: export d\'un autre utilisateur 404', $leakExport['status'] === 404);

// Routes
$router = new Router();
(require dirname(__DIR__) . '/routes/library.php')($router);
$token = $register['body']['token'];

$req = new Request('GET', '/api/v1/games', ['opponent' => 'dave'], [], ['authorization' => 'Bearer ' . $token]);
$resp = $router->route('GET', '/api/v1/games')($req);
check('route: GET /games filtre', $resp['status'] === 200 && $resp['body']['total'] === 1 && $resp['body']['games'][0]['opponent'] === 'dave');

$reqAnon = new Request('GET', '/api/v1/games', [], [], []);
$respAnon = $router->route('GET', '/api/v1/games')($reqAnon);
check('route: GET /games sans token 401', $respAnon['status'] === 401);

$reqDetail = new Request('GET', '/api/v1/games/1', [], [], ['authorization' => 'Bearer ' . $token]);
$respDetail = $router->route('GET', '/api/v1/games/1')($reqDetail);
check('route: GET /games/{id}', $respDetail['status'] === 200 && isset($respDetail['body']['pgn']));

$reqPatch = new Request('PATCH', '/api/v1/games/2', [], ['favorite' => true], ['authorization' => 'Bearer ' . $token]);
$respPatch = $router->route('PATCH', '/api/v1/games/2')($reqPatch);
check('route: PATCH /games/{id} favori', $respPatch['status'] === 200 && $respPatch['body']['favorite'] === true);

$reqExport = new Request('POST', '/api/v1/games/export', [], ['game_ids' => [1, 2, 3]], ['authorization' => 'Bearer ' . $token]);
$respExport = $router->route('POST', '/api/v1/games/export')($reqExport);
check('route: POST /games/export', $respExport['status'] === 200 && $respExport['body']['count'] === 3);

$reqPos = new Request('POST', '/api/v1/games/search-position', [], ['fen' => $fen], ['authorization' => 'Bearer ' . $token]);
$respPos = $router->route('POST', '/api/v1/games/search-position')($reqPos);
check('route: POST /games/search-position', $respPos['status'] === 200 && $respPos['body']['total'] === 2);

report();
