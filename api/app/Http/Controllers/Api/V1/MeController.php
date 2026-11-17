<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\AccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    /** GET /api/v1/me — profil joueur (C2). */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->accounts->profile((int) $request->user()->id));
    }

    /** PATCH /api/v1/me — mise à jour du profil (C2). */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $result = $this->accounts->updateProfile(
            (int) $request->user()->id,
            $request->validated(),
        );

        return response()->json($result['body'], $result['status']);
    }

    /** GET /api/v1/me/export — export RGPD (C4 / #18). */
    public function export(Request $request): JsonResponse
    {
        return response()->json($this->accounts->exportData((int) $request->user()->id));
    }

    /**
     * GET /api/v1/me/stats — agrégats du tableau de bord (W2), calculés
     * en base sur TOUTES les parties : le front ne peut pas les déduire
     * d'une liste paginée sans sous-estimer les totaux.
     */
    public function stats(Request $request): JsonResponse
    {
        $row = DB::table('games')
            ->where('user_id', (int) $request->user()->id)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN (result = '1-0' AND color = 'white') OR (result = '0-1' AND color = 'black') THEN 1 ELSE 0 END) AS victoires,
                SUM(CASE WHEN result = '1/2-1/2' THEN 1 ELSE 0 END) AS nulles,
                SUM(CASE WHEN (result = '0-1' AND color = 'white') OR (result = '1-0' AND color = 'black') THEN 1 ELSE 0 END) AS defaites")
            ->first();

        $total = (int) ($row->total ?? 0);
        $victoires = (int) ($row->victoires ?? 0);

        return response()->json([
            'total' => $total,
            'victoires' => $victoires,
            'nulles' => (int) ($row->nulles ?? 0),
            'defaites' => (int) ($row->defaites ?? 0),
            'taux_victoires' => $total > 0 ? (int) round($victoires * 100 / $total) : 0,
        ]);
    }

    /** DELETE /api/v1/me — suppression RGPD du compte (C4). */
    public function destroy(Request $request): JsonResponse
    {
        $this->accounts->deleteAccount($request->user());

        return response()->json(['message' => 'compte_supprime']);
    }
}
