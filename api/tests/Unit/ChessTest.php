<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Chess\Board;
use App\Domain\Chess\Pgn;
use PHPUnit\Framework\TestCase;

class ChessTest extends TestCase
{
    public function test_position_de_depart_offre_20_coups_legaux(): void
    {
        $this->assertCount(20, Board::start()->legalMovesUci());
    }

    public function test_fen_de_depart_est_exacte(): void
    {
        $this->assertSame(
            'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            Board::start()->fen(),
        );
    }

    public function test_san_converti_en_uci(): void
    {
        $b = Board::start();
        $this->assertSame('e2e4', $b->sanToUci('e4'));
        $this->assertSame('g1f3', $b->sanToUci('Nf3'));
    }

    public function test_san_illegal_est_rejete(): void
    {
        $this->assertNull(Board::start()->sanToUci('e5'));
    }

    public function test_mat_du_berger_est_detecte(): void
    {
        $b = Board::start();
        foreach (['e4', 'e5', 'Qh5', 'Nc6', 'Bc4', 'Nf6', 'Qxf7#'] as $san) {
            $this->assertTrue($b->applySan($san), "le coup {$san} doit être jouable");
        }
        $this->assertTrue($b->isCheckmate('b'));
        $this->assertSame([], $b->legalMovesUci());
    }

    public function test_petit_roque_blanc(): void
    {
        $b = Board::fromFen('r3k2r/pppppppp/8/8/8/8/PPPPPPPP/R3K2R w KQkq - 0 1');
        $this->assertSame('e1g1', $b->sanToUci('O-O'));
        $this->assertTrue($b->applySan('O-O'));
        $this->assertSame('wR', $b->pieceAt('f1'));
        $this->assertSame('wK', $b->pieceAt('g1'));
    }

    public function test_grand_roque_blanc(): void
    {
        $b = Board::fromFen('r3k2r/pppppppp/8/8/8/8/PPPPPPPP/R3K2R w KQkq - 0 1');
        $this->assertSame('e1c1', $b->sanToUci('O-O-O'));
        $this->assertTrue($b->applySan('O-O-O'));
        $this->assertSame('wR', $b->pieceAt('d1'));
    }

    public function test_roque_refuse_si_une_case_du_parcours_est_attaque(): void
    {
        $b = Board::fromFen('r3k2r/pppppppp/8/8/8/5n2/PPPPPPPP/R3K2R w KQkq - 0 1');
        $this->assertNull($b->sanToUci('O-O'));
    }

    public function test_piece_clouee_donne_tout_de_meme_echec(): void
    {
        // Cavalier noir a4 cloué par la tour blanche a1 (roi noir a8) :
        // il attaque pourtant le roi blanc b6, qui est donc en échec.
        $b = Board::fromFen('k7/8/1K6/8/n7/8/8/R7 w - - 0 1');
        $this->assertTrue($b->isCheck('w'));
    }

    public function test_prise_en_passant(): void
    {
        $b = Board::start();
        $b->applySan('e4');
        $b->applySan('a6');
        $b->applySan('e5');
        $b->applySan('d5');
        $this->assertSame('e5d6', $b->sanToUci('exd6'));
        $this->assertTrue($b->applySan('exd6'));
        $this->assertNull($b->pieceAt('d5'));
    }

    public function test_promotion(): void
    {
        $b = Board::fromFen('8/P7/8/8/8/8/7k/K7 w - - 0 1');
        $this->assertSame('a7a8q', $b->sanToUci('a8=Q'));
        $this->assertTrue($b->applySan('a8=N'));
        $this->assertSame('wN', $b->pieceAt('a8'));
    }

    public function test_promotion_sans_piece_indiquee_est_rejetee(): void
    {
        $b = Board::fromFen('8/P7/8/8/8/8/7k/K7 w - - 0 1');
        $this->assertFalse($b->applyUci('a7a8'));
    }

    public function test_pat_est_detecte(): void
    {
        $this->assertTrue(Board::fromFen('7k/5Q2/8/8/8/8/8/K7 b - - 0 1')->isStalemate());
    }

    public function test_fen_aller_retour(): void
    {
        $fen = 'r1bqkbnr/pppp1ppp/2n5/4p3/2B1P3/5Q2/PPPP1PPP/RNB1K1NR w KQkq - 4 4';
        $this->assertSame($fen, Board::fromFen($fen)->fen());
    }

    public function test_fen_invalide_rejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Board::fromFen('FEN invalide');
    }

    public function test_ambiguite_san_resolue_par_colonne_ou_rangee(): void
    {
        $b = Board::fromFen('1k6/8/8/8/8/8/1K6/R6R w - - 0 1');
        $this->assertSame('a1d1', $b->sanToUci('Rad1'));
        $this->assertSame('h1d1', $b->sanToUci('Rhd1'));
    }

    public function test_perft_kiwipete(): void
    {
        $kiwi = Board::fromFen('r3k2r/p1ppqpb1/bn2pnp1/3PN3/1p2P3/2N2Q1p/PPPBBPPP/R3K2R w KQkq - 0 1');
        $this->assertSame(48, $this->perft($kiwi, 1), 'perft(1) doit valoir 48');
        $this->assertSame(2039, $this->perft($kiwi, 2), 'perft(2) doit valoir 2039');
    }

    private function perft(Board $b, int $depth): int
    {
        if ($depth === 0) {
            return 1;
        }
        $nodes = 0;
        foreach ($b->legalMovesUci() as $uci) {
            $clone = clone $b;
            $clone->applyUci($uci);
            $nodes += $this->perft($clone, $depth - 1);
        }

        return $nodes;
    }

    public function test_pgn_parse_extraits_en_tetes_et_coups(): void
    {
        $pgn = '[Event "Test"]' . "\n" . '[White "Alice"]' . "\n" . '[Black "Bob"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 *';
        $parsed = Pgn::parse($pgn);
        $this->assertCount(6, $parsed['moves']);
        $this->assertSame('Alice', $parsed['headers']['White']);
    }

    public function test_pgn_rejeu_en_uci(): void
    {
        $pgn = '[Event "Test"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 *';
        $uci = Pgn::movesToUci($pgn);
        $this->assertNotNull($uci);
        $this->assertSame('e2e4', $uci[0]);
        $this->assertCount(6, $uci);
    }

    public function test_pgn_avec_coup_illegal_renvoye_null(): void
    {
        $bad = '[Event "Test"]' . "\n\n" . '1. e4 e5 2. Zzz9 *';
        $this->assertNull(Pgn::movesToUci($bad));
    }

    public function test_pgn_jetons_inconnus_marques_invalide(): void
    {
        $parsed = Pgn::parse('[Event "Test"]' . "\n\n" . '1. e4 e5 2. Zzz9 *');
        $this->assertTrue($parsed['invalid']);
    }

    public function test_pgn_variantes_et_commentaires_ignores(): void
    {
        $pgn = '[Event "Test"]' . "\n\n" . '1. e4 e5 {commentaire} (1... c5 2. Nf3) 2. Nf3 *';
        $this->assertSame(['e2e4', 'e7e5', 'g1f3'], Pgn::movesToUci($pgn));
    }

    public function test_pgn_construction_depuis_des_coups_uci(): void
    {
        $built = Pgn::build(['Event' => 'Test'], ['e2e4', 'e7e5', 'g1f3'], '1-0');
        $this->assertStringContainsString('1. e4 e5 2. Nf3', $built);
        $this->assertStringEndsWith('1-0', trim($built));
    }

    public function test_pgn_aller_retour_build_puis_rejeu(): void
    {
        $built = Pgn::build(['Event' => 'Test'], ['e2e4', 'e7e5', 'g1f3'], '1-0');
        $this->assertSame(['e2e4', 'e7e5', 'g1f3'], Pgn::movesToUci($built));
    }
}
