<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncPlatformJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisation nocturne de tous les comptes liés (L7).
 *
 * Enfile une synchronisation incrémentale par compte lié : chaque joueur
 * récupère ses nouvelles parties pendant la nuit, dans la limite de son
 * curseur mémorisé. Appelée par le scheduler (routes/console.php) — sur
 * hébergement mutualisé, le scheduler est piloté par le cron cPanel.
 */
final class SyncAllCommand extends Command
{
    protected $signature = 'korrichess:sync-all';

    protected $description = 'Enfile une synchronisation incrémentale pour chaque compte lié (nocturne L7)';

    public function handle(): int
    {
        $comptes = DB::table('linked_accounts')
            ->get(['user_id', 'platform', 'username']);

        foreach ($comptes as $compte) {
            SyncPlatformJob::dispatch(
                (int) $compte->user_id,
                strtolower(trim((string) $compte->platform)),
                trim((string) $compte->username),
            );
        }

        $this->info(count($comptes) . ' synchronisation(s) enfilée(s).');

        return self::SUCCESS;
    }
}
