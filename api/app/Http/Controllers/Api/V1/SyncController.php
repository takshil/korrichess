<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\DomainError;
use App\Domain\Sync\PlatformRegistry;
use App\Domain\Sync\SyncScheduler;
use App\Domain\Sync\SyncStateRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SyncRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SyncController extends Controller
{
    /**
     * POST /api/v1/sync — enfile la synchronisation d'un compte lié (issue #3).
     *
     * Asynchrone (L7) : la route répond 202 dès que le job est enfilé ; le
     * worker (cron mutualisé, cf. routes/console.php) importe les parties
     * en tâche de fond. L'état se consulte via GET /api/v1/sync/statut.
     */
    public function store(SyncRequest $request, PlatformRegistry $platforms, SyncScheduler $scheduler): JsonResponse
    {
        $platform = PlatformRegistry::normalise((string) $request->validated('platform'));
        $username = trim((string) $request->validated('username'));
        if (! $platforms->has($platform)) {
            throw DomainError::invalid('plateforme_inconnue');
        }
        if ($username === '') {
            throw DomainError::invalid('pseudo_invalide');
        }

        $scheduler->schedule((int) $request->user()->id, $platform, $username, $request->filters());

        return response()->json([
            'statut' => 'en_file',
            'platform' => $platform,
            'username' => $username,
            'message' => 'Synchronisation lancée en arrière-plan ; tes parties arrivent dans un instant.',
        ], 202);
    }

    /** GET /api/v1/sync/statut — curseurs de synchronisation du joueur. */
    public function statut(Request $request, SyncStateRepository $states): JsonResponse
    {
        return response()->json([
            'synchronisations' => $states->forUser((int) $request->user()->id)
                ->map(static fn (object $l): array => [
                    'platform' => $l->platform,
                    'username' => $l->username,
                    'last_game_at' => $l->last_game_at,
                    'last_sync_at' => $l->last_sync_at,
                ])
                ->all(),
        ]);
    }
}
