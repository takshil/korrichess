<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\AccountEraser;
use App\Domain\Account\PersonalDataExporter;
use App\Domain\Account\ProfileService;
use App\Domain\Stats\PlayerStatsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __construct(private readonly ProfileService $profiles)
    {
    }

    /** GET /api/v1/me — profil joueur (C2). */
    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($this->profiles->show($request->user()));
    }

    /** PATCH /api/v1/me — mise à jour du profil (C2). */
    public function update(UpdateProfileRequest $request): ProfileResource
    {
        return new ProfileResource($this->profiles->update($request->user(), $request->validated()));
    }

    /** GET /api/v1/me/export — export RGPD (C4 / #18). */
    public function export(Request $request, PersonalDataExporter $exporter): JsonResponse
    {
        return response()->json($exporter->export($request->user()));
    }

    /** GET /api/v1/me/stats — agrégats du tableau de bord (W2). */
    public function stats(Request $request, PlayerStatsService $stats): JsonResponse
    {
        return response()->json($stats->forUser((int) $request->user()->id));
    }

    /** DELETE /api/v1/me — suppression RGPD du compte (C4). */
    public function destroy(Request $request, AccountEraser $eraser): JsonResponse
    {
        $eraser->erase($request->user());

        return response()->json(['message' => 'compte_supprime']);
    }
}
