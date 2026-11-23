<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 * Rejeu d'une suite de coups UCI depuis la position initiale : FEN de
 * chaque position atteinte (départ compris) et SAN de chaque coup.
 *
 * Le rejeu s'arrête au premier coup illégal ; ce coup a alors son SAN
 * calculé (best effort) mais n'ajoute aucune position.
 */
final class GameReplay
{
    /**
     * @param list<string> $positions
     * @param list<string> $sans
     */
    private function __construct(
        public readonly array $positions,
        public readonly array $sans,
    ) {
    }

    /** @param list<string> $uciMoves */
    public static function fromUci(array $uciMoves): self
    {
        $board = Board::start();
        $positions = [$board->fen()];
        $sans = [];

        foreach ($uciMoves as $uci) {
            $sans[] = $board->uciToSan($uci);
            if (!$board->applyUci($uci)) {
                break;
            }
            $positions[] = $board->fen();
        }

        return new self($positions, $sans);
    }
}
