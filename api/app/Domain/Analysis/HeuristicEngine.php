<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Analysis;

use Korrichess\Api\Domain\Chess\Board;

/**
 * Moteur deterministe sans processus externe, pour les tests et les
 * environnements sans Stockfish (ADR-002). L'evaluation materiel + mobilite
 * est stable et reproductible : suffisant pour valider le classement des
 * coups, la courbe et le moment decisif.
 */
final class HeuristicEngine implements EngineClient
{
    private const PIECE_VALUE = ['P' => 100, 'N' => 320, 'B' => 330, 'R' => 500, 'Q' => 900, 'K' => 0];

    /** @return array{eval_cp:int,best_move:?string,variation:array<int,string>} */
    public function evaluate(string $fen, int $depth = 12): array
    {
        try {
            $board = Board::fromFen($fen);
        } catch (\InvalidArgumentException) {
            return ['eval_cp' => 0, 'best_move' => null, 'variation' => []];
        }
        $turn = $fen === '' ? 'w' : (preg_split('/\s+/', trim($fen))[1] ?? 'w');
        $moves = $board->legalMovesUci();
        $best = null;
        $bestScore = null;
        foreach ($moves as $uci) {
            $clone = clone $board;
            $clone->applyUci($uci);
            $score = self::score($clone->fen()) * ($turn === 'w' ? 1 : -1);
            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = $uci;
            }
        }
        return [
            'eval_cp' => self::score($fen),
            'best_move' => $best,
            'variation' => $best !== null ? [$best] : [],
        ];
    }

    public function name(): string
    {
        return 'heuristic';
    }

    /** Score statique materiel + mobilite, point de vue des blancs. */
    private static function score(string $fen): int
    {
        $board = Board::fromFen($fen);
        $material = 0;
        foreach ($board->squares() as $piece) {
            $value = self::PIECE_VALUE[$piece[1]] ?? 0;
            $material += $piece[0] === 'w' ? $value : -$value;
        }
        $mobility = count($board->legalMovesUci());
        $sign = $board->turn() === 'w' ? 1 : -1;
        return $material + $sign * $mobility;
    }
}
