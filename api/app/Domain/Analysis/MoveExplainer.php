<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Chess\Board;

/** A7 : explication en langage simple d'un coup perdant. */
final class MoveExplainer
{
    public function explain(Board $board, string $played, ?string $best, int $loss): string
    {
        $parts = [$this->severityMessage($loss)];

        if ($best !== null) {
            $parts[] = 'Le moteur suggère ' . $board->uciToSan($best) . '.';
            if ($this->missedCapture($board, $best)) {
                $parts[] = 'Une pièce adverse était prenable.';
            }
        }
        if ($this->landsOnAttackedSquare($board, $played)) {
            $parts[] = 'Ta pièce tombe sous le contrôle adverse.';
        }

        return implode(' ', $parts);
    }

    private function severityMessage(int $loss): string
    {
        return match (true) {
            $loss > 900 => 'Ce coup perd une pièce.',
            $loss > 250 => 'Ce coup laisse échapper un avantage important.',
            default => 'Ce coup laisse passer une meilleure opportunité.',
        };
    }

    /** Tactique manquée : le meilleur coup est une prise non jouée. */
    private function missedCapture(Board $board, string $best): bool
    {
        $target = $board->pieceAt(substr($best, 2, 2));

        return $target !== null && $target[0] !== $board->turn();
    }

    /** Pièce sans défense : la pièce jouée atterrit sur une case attaquée. */
    private function landsOnAttackedSquare(Board $board, string $played): bool
    {
        $after = clone $board;
        $after->applyUci($played);
        $piece = $after->pieceAt(substr($played, 2, 2));

        return $piece !== null
            && $piece[0] === $board->turn()
            && $piece[1] !== 'K'
            && $after->isCheck($board->turn());
    }
}
