<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/** Verdict sur un coup joué : catégorie (A4), explication (A7), meilleur coup en SAN (A6). */
final class MoveVerdict
{
    public function __construct(
        public readonly string $classification,
        public readonly ?string $explanation = null,
        public readonly ?string $bestSan = null,
    ) {
    }

    /** Position finale : aucun coup n'est joué, rien à juger. */
    public static function none(): self
    {
        return new self('meilleur');
    }
}
