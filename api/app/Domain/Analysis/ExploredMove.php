<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/** A8 : résultat d'un coup alternatif essayé depuis une position de la partie. */
final class ExploredMove
{
    public function __construct(
        public readonly int $ply,
        public readonly string $moveUci,
        public readonly string $moveSan,
        public readonly string $fen,
        public readonly int $evalCp,
    ) {
    }
}
