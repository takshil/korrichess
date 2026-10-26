<?php

declare(strict_types=1);

require_once __DIR__ . '/run_lib.php';

use Korrichess\Api\Domain\Chess\Board;
use Korrichess\Api\Domain\Chess\Pgn;

// Position de départ : 20 coups légaux
$b = Board::start();
check('plateau: 20 coups légaux au départ', count($b->legalMovesUci()) === 20);
check('plateau: FEN de départ', $b->fen() === 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');

// SAN -> UCI de base
check('san: e4', $b->sanToUci('e4') === 'e2e4');
check('san: Nf3', $b->sanToUci('Nf3') === 'g1f3');
check('san: coup illégal rejeté', $b->sanToUci('e5') === null);

// Mat du berger
$b = Board::start();
foreach (['e4', 'e5', 'Qh5', 'Nc6', 'Bc4', 'Nf6', 'Qxf7#'] as $san) {
    $ok = $b->applySan($san);
    if (!$ok) {
        check("berger: $san joué", false);
        break;
    }
}
check('berger: mat détecté', $b->isCheckmate('b'));
check('berger: pas de coups légaux après mat', $b->legalMovesUci() === []);

// Roques
$b = Board::fromFen('r3k2r/pppppppp/8/8/8/8/PPPPPPPP/R3K2R w KQkq - 0 1');
check('roque: petit roque blanc', $b->sanToUci('O-O') === 'e1g1');
check('roque: grand roque blanc', $b->sanToUci('O-O-O') === 'e1c1');
$b->applySan('O-O');
check('roque: tour déplacée en f1', $b->pieceAt('f1') === 'wR');
check('roque: roi en g1', $b->pieceAt('g1') === 'wK');
$b2 = Board::fromFen('r3k2r/pppppppp/8/8/8/8/PPPPPPPP/R3K2R w KQkq - 0 1');
$b2->applySan('O-O-O');
check('roque: grand roque, tour en d1', $b2->pieceAt('d1') === 'wR');

// Roque interdit quand une case est attaquée
$b3 = Board::fromFen('r3k2r/pppppppp/8/8/8/5n2/PPPPPPPP/R3K2R w KQkq - 0 1');
check('roque: refusé si case attaquée', $b3->sanToUci('O-O') === null);

// Prise en passant
$b4 = Board::start();
$b4->applySan('e4');
$b4->applySan('a6');
$b4->applySan('e5');
$b4->applySan('d5');
$ep = $b4->sanToUci('exd6');
check('passant: exd6 disponible', $ep === 'e5d6');
$b4->applySan('exd6');
check('passant: pion noir capturé', $b4->pieceAt('d5') === null);

// Promotion
$b5 = Board::fromFen('8/P7/8/8/8/8/7k/K7 w - - 0 1');
check('promotion: a8=Q', $b5->sanToUci('a8=Q') === 'a7a8q');
$b5->applySan('a8=N');
check('promotion: cavalier en a8', $b5->pieceAt('a8') === 'wN');

// Pat
$b6 = Board::fromFen('7k/5Q2/8/8/8/8/8/K7 b - - 0 1');
check('pat: stagnation détectée', $b6->isStalemate());

// FEN aller-retour
$fen = 'r1bqkbnr/pppp1ppp/2n5/4p3/2B1P3/5Q2/PPPP1PPP/RNB1K1NR w KQkq - 4 4';
check('fen: fromFen->fen idempotent', Board::fromFen($fen)->fen() === $fen);
try {
    Board::fromFen('FEN invalide');
    check('fen: invalide rejetée', false);
} catch (InvalidArgumentException) {
    check('fen: invalide rejetée', true);
}

// SAN ambiguïté
$b7 = Board::fromFen('1k6/8/8/8/8/8/1K6/R6R w - - 0 1');
check('san: ambiguïté résolue par colonne', $b7->sanToUci('Rad1') === 'a1d1');
check('san: ambiguïté résolue (h)', $b7->sanToUci('Rhd1') === 'h1d1');

// Perft position 2 (Kiwipete) profondeur 2 : 2039
$kiwi = Board::fromFen('r3k2r/p1ppqpb1/bn2pnp1/3PN3/1p2P3/2N2Q1p/PPPBBPPP/R3K2R w KQkq - 0 1');
function perft(Board $b, int $d): int
{
    if ($d === 0) {
        return 1;
    }
    $n = 0;
    foreach ($b->legalMovesUci() as $uci) {
        $clone = clone $b;
        $clone->applyUci($uci);
        $n += perft($clone, $d - 1);
    }
    return $n;
}
check('perft kiwipete d1 = 48', perft($kiwi, 1) === 48);
check('perft kiwipete d2 = 2039', perft($kiwi, 2) === 2039);

// PGN
$pgn = '[Event "Test"]' . "\n" . '[White "Alice"]' . "\n" . '[Black "Bob"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 *';
$parsed = Pgn::parse($pgn);
check('pgn: 6 coups extraits', count($parsed['moves']) === 6);
check('pgn: en-têtes lus', $parsed['headers']['White'] === 'Alice');
$uci = Pgn::movesToUci($pgn);
check('pgn: rejeu UCI valide', $uci !== null && $uci[0] === 'e2e4' && count($uci) === 6);
$badPgn = '[Event "Test"]' . "\n\n" . '1. e4 e5 2. Zzz9 *';
check('pgn: coup illégal => null', Pgn::movesToUci($badPgn) === null);
$built = Pgn::build(['Event' => 'Test'], ['e2e4', 'e7e5', 'g1f3'], '1-0');
check('pgn: construction SAN', str_contains($built, '1. e4 e5 2. Nf3') && str_ends_with(trim($built), '1-0'));
$rebuilt = Pgn::movesToUci($built);
check('pgn: aller-retour build->movesToUci', $rebuilt === ['e2e4', 'e7e5', 'g1f3']);

report();
