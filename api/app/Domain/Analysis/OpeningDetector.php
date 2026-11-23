<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Chess\Board;

/** A9 : nom de l'ouverture et demi-coup de sortie de théorie (mini-book). */
final class OpeningDetector
{
    /** Ouvertures du mini-book (ECO → [nom, coups théoriques en SAN]). */
    private const BOOK = [
        'C50' => ['Partie italienne', ['e4', 'e5', 'Nf3', 'Nc6', 'Bc4', 'Bc5']],
        'C55' => ['Partie italienne, variante hongroise', ['e4', 'e5', 'Nf3', 'Nc6', 'Bc4', 'Nf6']],
        'B00' => ['Ouverture irrégulière', ['e4']],
        'B01' => ['Défense scandinave', ['e4', 'd5']],
        'D02' => ['Ouverture de la dame', ['d4', 'd5', 'Nf3']],
        'C44' => ['Ouverture écossaise', ['e4', 'e5', 'Nf3', 'Nc6', 'd4']],
    ];

    /**
     * @param list<string> $uciMoves
     *
     * @return array{0:?string,1:?int} [nom, demi-coup de sortie de théorie]
     */
    public function detect(?string $eco, array $uciMoves): array
    {
        if ($eco === null || !isset(self::BOOK[$eco])) {
            return [$eco, null];
        }

        [$name, $bookSans] = self::BOOK[$eco];

        return [$name, $this->bookExitPly($bookSans, $uciMoves)];
    }

    /**
     * @param list<string> $bookSans
     * @param list<string> $uciMoves
     */
    private function bookExitPly(array $bookSans, array $uciMoves): ?int
    {
        $board = Board::start();
        foreach ($bookSans as $i => $san) {
            $uci = $board->sanToUci($san);
            if ($uci === null || $i >= count($uciMoves) || $uciMoves[$i] !== $uci) {
                return $i;
            }
            $board->applyUci($uci);
        }

        return null;
    }
}
