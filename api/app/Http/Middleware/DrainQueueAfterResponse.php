<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Worker de queue + sync quotidienne embarqués (hybride sans SSH)
|--------------------------------------------------------------------------
| Sur un mutualisé sans SSH, la file ne peut être vidée que par le cron.
| Ce middleware complète le cron, après l'envoi de la réponse (terminate) :
|
|   1. la sync quotidienne : à la première requête après 3 h
|      (Europe/Paris), une fois par jour au plus, on enfile
|      korrichess:sync-all (une sync incrémentale par compte lié) ;
|   2. un mini-worker --stop-when-empty qui traite aussitôt les jobs
|      ainsi enfilés.
|
| Des verrous de cache empêchent les doublons entre requêtes simultanées.
| Toute exception est avalée : un échec ici ne doit jamais casser la
| requête web. Le scheduler (cron) reste en filet de sécurité : il ferait
| la même chose à 3 h si personne ne visite le site ce jour-là... et le
| cron est doublement utile tant que son process lit mal le .env.
*/
class DrainQueueAfterResponse
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    // Type sur la base Symfony : les réponses API sont des JsonResponse,
    // qui n'étendent pas Illuminate\Http\Response.
    public function terminate(Request $request, \Symfony\Component\HttpFoundation\Response $response): void
    {
        // Enfiler d'abord la sync du jour (éventuelle), puis vider la file.
        try {
            $this->syncQuotidienne();
        } catch (\Throwable $e) {
            \Log::warning('Sync quotidienne embarquée échouée : '.$e->getMessage());
        }

        try {
            $this->viderLaFile();
        } catch (\Throwable $e) {
            \Log::warning('Worker embarqué échoué : '.$e->getMessage());
        }
    }

    /**
     * Mini-worker : traite les jobs en attente, 10 s au maximum.
     */
    private function viderLaFile(): void
    {
        $lock = Cache::lock('korrichess:queue-drain', 120);

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

    /**
     * Sync nocturne sans dépendre du cron : à la première requête du jour
     * postérieure à 3 h (heure de Paris), si aucune sync quotidienne n'a
     * été déclenchée depuis le 3 h du jour, on enfile une sync par compte
     * lié. Le verrou évite un double déclenchement ; le cache mémorise le
     * dernier passage pour ne le refaire qu'une fois par 24 h.
     */
    private function syncQuotidienne(): void
    {
        // En tests et en local, on ne veut pas enfiler de vraies sync.
        if (! \app()->environment('production')) {
            return;
        }

        $troisHeures = (new \DateTimeImmutable('today 03:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $derniere = (int) Cache::get('korrichess:sync-quotidienne');

        if ($derniere >= $troisHeures) {
            return; // déjà passée aujourd'hui
        }

        $lock = Cache::lock('korrichess:sync-quotidienne-verrou', 300);
        if (! $lock->get()) {
            return; // une autre requête vient de la lancer
        }

        try {
            // Marquée avant l'appel : même si le crash arrive, on ne
            // re-déclenche pas à chaque requête de la journée.
            Cache::forever('korrichess:sync-quotidienne', time());
            Artisan::call('korrichess:sync-all');
        } finally {
            $lock->release();
        }
    }
}
