<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\AccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
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
        [$status, $body] = $this->accounts->updateProfile(
            (int) $request->user()->id,
            $request->validated(),
        );

        return response()->json($body, $status);
    }

    /** GET /api/v1/me/export — export RGPD (C4 / #18). */
    public function export(Request $request): JsonResponse
    {
        return response()->json($this->accounts->exportData((int) $request->user()->id));
    }

    /** DELETE /api/v1/me — suppression RGPD du compte (C4). */
    public function destroy(Request $request): JsonResponse
    {
        $this->accounts->deleteAccount($request->user());

        return response()->json(['message' => 'compte_supprime']);
    }
}
