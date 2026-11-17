<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncPlatformJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $platform,
        public readonly string $username,
        public readonly array $filters = [],
    ) {
    }

    /**
     * Sync automatique en file (L7) : rejoue la synchronisation incrémentale
     * en tâche de fond. Sans client plateforme configuré, la sync répondra
     * plateforme_inconnue sans effet de bord.
     */
    public function handle(SyncService $service): void
    {
        $service->sync($this->userId, $this->platform, $this->username, $this->filters);
    }
}
