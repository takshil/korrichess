<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sync\SyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SyncRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SyncController extends Controller
{
    /**
     * POST /api/v1/sync — enfile la synchronisation d'un compte lié (issue #3).
     *
     * Asynchrone (L7) : la route répond 202 dès que le job est enfilé ; le
     * worker (cron mutualisé, cf. routes/console.php) importe les parties
     * en tâche de fond. L'état se consulte via GET /api/v1/sync/statut.
     */
    public function store(SyncRequest $request, SyncService $service): JsonResponse
    {
        $validated = $request->validated();
        $platform = strtolower(trim((string) $validated['platform']));
        $username = trim((string) $validated['username']);

        if (! in_array($platform, $service->platforms(), true)) {
            return response()->json(['error' => 'plateforme_inconnue'], 422);
        }
        if ($username === '') {
            return response()->json(['error' => 'pseudo_invalide'], 422);
        }

        $service->scheduleAutoSync((int) $request->user()->id, $platform, $username, $request->filters());

        return response()->json([
            'statut' => 'en_file',
            'platform' => $platform,
            'username' => $username,
            'message' => 'Synchronisation lancée en arrière-plan ; tes parties arrivent dans un instant.',
        ], 202);
    }

    /**
     * GET /api/v1/sync/statut — curseurs de synchronisation du joueur.
     *
     * @return JsonResponse{code:200,body:array{synchronisations:array<int,array{platform:string,username:string,last_game_at:?string,last_sync_at:?string}>}}
     */
    public function statut(Request $request): JsonResponse
    {
        $lignes = DB::table('sync_state')
            ->where('user_id', (int) $request->user()->id)
            ->orderBy('platform')
            ->get(['platform', 'username', 'last_game_at', 'last_sync_at']);

        return response()->json([
            'synchronisations' => $lignes->map(static fn (object $l): array => [
                'platform' => $l->platform,
                'username' => $l->username,
                'last_game_at' => $l->last_game_at,
                'last_sync_at' => $l->last_sync_at,
            ])->all(),
        ]);
    }
}
