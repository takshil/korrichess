<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Mini-worker embarqué : traite les jobs en attente, 10 s au maximum. Sur
 * un mutualisé sans SSH, c'est ce qui complète le cron pour vider la file.
 */
final class EmbeddedQueueWorker
{
    private const LOCK_KEY = 'korrichess:queue-drain';

    public function drain(): void
    {
        // Connexion « sync » : les jobs s'exécutent à l'enfilage, rien à vider.
        if (config('queue.default') === 'sync') {
            return;
        }

        $lock = Cache::lock(self::LOCK_KEY, 120);
        if (! $lock->get()) {
            return; // un worker tourne déjà, on laisse tomber
        }

        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => 10,
                '--timeout' => 900,
            ]);
        } finally {
            $lock->release();
        }
    }
}
