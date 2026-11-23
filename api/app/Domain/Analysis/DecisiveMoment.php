<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/** A5 : moment décisif = plus grande variation d'évaluation entre deux demi-coups consécutifs. */
final class DecisiveMoment
{
    /** @param list<int> $evals évaluation (cp) de chaque position */
    public static function ply(array $evals): ?int
    {
        $maxDrop = 0;
        $ply = null;

        for ($i = 1; $i < count($evals); $i++) {
            $drop = abs($evals[$i] - $evals[$i - 1]);
            if ($drop > $maxDrop) {
                $maxDrop = $drop;
                $ply = $i - 1;
            }
        }

        return $ply;
    }
}
