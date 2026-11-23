<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/** Profondeur d'analyse : valeur par défaut et bornes acceptées. */
final class AnalysisDepth
{
    public const DEFAULT = 12;
    private const MIN = 4;
    private const MAX = 20;

    public static function clamp(?int $depth): int
    {
        return max(self::MIN, min(self::MAX, $depth ?? self::DEFAULT));
    }
}
