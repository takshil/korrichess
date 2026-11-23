<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DailySyncTrigger;
use App\Support\EmbeddedQueueWorker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| Worker de queue + sync quotidienne embarqués (hybride sans SSH)
|--------------------------------------------------------------------------
| Sur un mutualisé sans SSH, la file ne peut être vidée que par le cron.
| Ce middleware complète le cron, après l'envoi de la réponse (terminate) :
|
|   1. la sync quotidienne (DailySyncTrigger) : à la première requête
|      après 3 h (Europe/Paris), une fois par jour au plus, on enfile
|      korrichess:sync-all (une sync incrémentale par compte lié) ;
|   2. un mini-worker --stop-when-empty (EmbeddedQueueWorker) qui traite
|      aussitôt les jobs ainsi enfilés.
|
| Des verrous de cache empêchent les doublons entre requêtes simultanées.
| Toute exception est avalée : un échec ici ne doit jamais casser la
| requête web. Le scheduler (cron) reste en filet de sécurité : il ferait
| la même chose à 3 h si personne ne visite le site ce jour-là... et le
| cron est doublement utile tant que son process lit mal le .env.
*/
final class DrainQueueAfterResponse
{
    public function __construct(
        private readonly DailySyncTrigger $dailySync,
        private readonly EmbeddedQueueWorker $worker,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    // Type sur la base Symfony : les réponses API sont des JsonResponse,
    // qui n'étendent pas Illuminate\Http\Response.
    public function terminate(Request $request, Response $response): void
    {
        // Enfiler d'abord la sync du jour (éventuelle), puis vider la file.
        $this->swallow('Sync quotidienne embarquée échouée', fn () => $this->dailySync->triggerIfDue());
        $this->swallow('Worker embarqué échoué', fn () => $this->worker->drain());
    }

    private function swallow(string $label, callable $task): void
    {
        try {
            $task();
        } catch (\Throwable $e) {
            Log::warning($label . ' : ' . $e->getMessage());
        }
    }
}
