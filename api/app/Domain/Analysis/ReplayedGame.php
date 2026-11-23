<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Models\Game;

/** A1 : une partie rejouée, prête pour un échiquier interactif. */
final class ReplayedGame
{
    /**
     * @param list<string> $movesUci
     * @param list<string> $movesSan
     * @param list<string> $positions FEN de chaque position, départ compris
     */
    public function __construct(
        public readonly Game $game,
        public readonly array $movesUci,
        public readonly array $movesSan,
        public readonly array $positions,
    ) {
    }
}
