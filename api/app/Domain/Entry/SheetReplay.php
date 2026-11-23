<?php

declare(strict_types=1);

namespace App\Domain\Entry;

use App\Domain\Chess\Board;

/**
 * Résultat du rejeu d'une feuille de partie : position atteinte, coups
 * reconnus (UCI et SAN) et, le cas échéant, le premier coup impossible.
 */
final class SheetReplay
{
    /**
     * @param list<string> $uci
     * @param list<string> $san
     */
    public function __construct(
        public readonly Board $board,
        public readonly array $uci,
        public readonly array $san,
        public readonly ?int $invalidPly,
        public readonly ?string $invalidMove,
    ) {
    }

    public function hasInvalidMove(): bool
    {
        return $this->invalidPly !== null;
    }
}
