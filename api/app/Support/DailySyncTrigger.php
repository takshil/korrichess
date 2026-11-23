<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Sync nocturne sans dépendre du cron : à la première requête du jour
 * postérieure à 3 h (heure de Paris), si aucune sync quotidienne n'a été
 * déclenchée depuis le 3 h du jour, on enfile une sync par compte lié. Le
 * verrou évite un double déclenchement ; le cache mémorise le dernier
 * passage pour ne le refaire qu'une fois par 24 h.
 */
final class DailySyncTrigger
{
    private const LAST_RUN_KEY = 'korrichess:sync-quotidienne';
    private const LOCK_KEY = 'korrichess:sync-quotidienne-verrou';

    public function triggerIfDue(): void
    {
        // En tests et en local, on ne veut pas enfiler de vraies sync.
        if (! app()->environment('production')) {
            return;
        }

        $troisHeures = (new \DateTimeImmutable('today 03:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        if ((int) Cache::get(self::LAST_RUN_KEY) >= $troisHeures) {
            return; // déjà passée aujourd'hui
        }

        $lock = Cache::lock(self::LOCK_KEY, 300);
        if (! $lock->get()) {
            return; // une autre requête vient de la lancer
        }

        try {
            // Marquée avant l'appel : même si le crash arrive, on ne
            // re-déclenche pas à chaque requête de la journée.
            Cache::forever(self::LAST_RUN_KEY, time());
            Artisan::call('korrichess:sync-all');
        } finally {
            $lock->release();
        }
    }
}
