<?php

declare(strict_types=1);

namespace App\Domain\Chess;

final class Move
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $promotion = null,
    ) {
    }
}
