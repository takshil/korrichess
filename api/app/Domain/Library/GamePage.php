<?php

declare(strict_types=1);

namespace App\Domain\Library;

use Illuminate\Support\Collection;

/** Une page de la bibliothèque (B1–B2) : parties de la page + total et fenêtre appliquée. */
final class GamePage
{
    /** @param Collection<int,\App\Models\Game> $games */
    public function __construct(
        public readonly Collection $games,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
