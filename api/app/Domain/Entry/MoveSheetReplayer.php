<?php

declare(strict_types=1);

namespace App\Domain\Entry;

use App\Domain\Chess\Board;

/**
 * Rejoue les coups d'une feuille de partie depuis la position initiale.
 * Chaque coup est une chaîne UCI (échiquier interactif) ou en notation
 * algébrique (feuille papier) ; le premier coup impossible arrête le rejeu.
 */
final class MoveSheetReplayer
{
    private const UCI = '/^[a-h][1-8][a-h][1-8][qrbn]?$/';

    /**
     * @param array<int|string,mixed> $moves
     */
    public function replay(array $moves): SheetReplay
    {
        $board = Board::start();
        $uci = [];
        $san = [];

        foreach (self::clean($moves) as $ply => $move) {
            $played = $this->toLegalUci($board, $move);
            if ($played === null) {
                return new SheetReplay($board, $uci, $san, $ply, $move);
            }

            $san[] = $board->uciToSan($played);
            $board->applyUci($played);
            $uci[] = $played;
        }

        return new SheetReplay($board, $uci, $san, null, null);
    }

    /**
     * Coups tels que saisis, débarrassés des espaces et réindexés.
     *
     * @param array<int|string,mixed> $moves
     * @return list<string>
     */
    public static function clean(array $moves): array
    {
        return array_values(array_map(static fn (mixed $move): string => trim((string) $move), $moves));
    }

    /** Coup UCI jouable dans la position, ou null s'il est impossible. */
    private function toLegalUci(Board $board, string $move): ?string
    {
        if (preg_match(self::UCI, $move) === 1) {
            return in_array($move, $board->legalMovesUci(), true) ? $move : null;
        }

        return $board->sanToUci($move);
    }
}
