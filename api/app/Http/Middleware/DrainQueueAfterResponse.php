<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Worker de queue embarqué dans les requêtes (hybride sans SSH)
|--------------------------------------------------------------------------
| Sur un mutualisé sans SSH, la file ne peut être vidée que par le cron.
| Ce middleware complément le cron : après l'envoi de la réponse au
| visiteur (terminate), il lance un mini-worker --stop-when-empty pour
| traiter les jobs tout de suite, sans latence perçue.
|
| Un verrou de cache empêche deux requêtes simultanées de lancer deux
| workers en parallèle. Toute exception est avalée : un échec du worker
| ne doit jamais casser la requête web.
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
        try {
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
        } catch (\Throwable $e) {
            \Log::warning('Worker embarqué échoué : '.$e->getMessage());
        }
    }
}
