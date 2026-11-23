<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/** Bilan d'une synchronisation. */
final class SyncReport
{
    public function __construct(
        public readonly string $platform,
        public readonly string $username,
        public readonly int $imported,
        public readonly int $duplicates,
    ) {
    }
}
