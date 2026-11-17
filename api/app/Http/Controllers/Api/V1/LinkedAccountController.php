<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\AccountService;
use App\Domain\Sync\SyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LinkAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LinkedAccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly SyncService $sync,
    ) {
    }

    /** POST /api/v1/me/linked-accounts — lier un pseudo (C3). */
    public function store(LinkAccountRequest $request): JsonResponse
    {
        $platform = (string) $request->validated('platform');
        $username = (string) $request->validated('username');

        $result = $this->accounts->linkAccount((int) $request->user()->id, $platform, $username);

        // Nouveau lien : la première synchronisation part immédiatement en
        // file ; l'espace affiche l'indicateur de chargement pendant l'import.
        if ($result['status'] === 201) {
            $this->sync->scheduleAutoSync((int) $request->user()->id, $platform, $username);
        }

        return response()->json($result['body'], $result['status']);
    }

    /** DELETE /api/v1/me/linked-accounts — délier un pseudo (C3). */
    public function destroy(Request $request): JsonResponse
    {
        $removed = $this->accounts->unlinkAccount(
            (int) $request->user()->id,
            (string) $request->input('platform', ''),
            (string) $request->input('username', ''),
        );

        if (!$removed) {
            return response()->json(['error' => 'introuvable'], 404);
        }

        return response()->json(['message' => 'delie']);
    }
}
