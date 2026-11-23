<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use App\Jobs\SyncPlatformJob;

/**
 * Enfile une synchronisation automatique (L7) : le job rejouera la
 * synchronisation incrémentale en tâche de fond, filtres compris.
 */
final class SyncScheduler
{
    /**
     * @param array{time_controls?:array<string>,period_start?:?string} $filters
     */
    public function schedule(int $userId, string $platform, string $username, array $filters = []): void
    {
        SyncPlatformJob::dispatch($userId, PlatformRegistry::normalise($platform), $username, $filters);
    }
}
