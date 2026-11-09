<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /** GET /api/v1/me/settings — paramètres du joueur (issue #10). */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->settings->get((int) $request->user()->id));
    }

    /** PATCH /api/v1/me/settings — mise à jour des paramètres (issue #10). */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $result = $this->settings->update(
            (int) $request->user()->id,
            $request->validated(),
        );

        return response()->json($result['body'], $result['status']);
    }
}
