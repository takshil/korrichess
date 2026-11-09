<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sync\SyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SyncRequest;
use Illuminate\Http\JsonResponse;

final class SyncController extends Controller
{
    /**
     * POST /api/v1/sync — synchronise les parties d'un compte lié (issue #3).
     */
    public function store(SyncRequest $request, SyncService $service): JsonResponse
    {
        $validated = $request->validated();

        [$status, $body] = $service->sync(
            (int) $request->user()->id,
            (string) $validated['platform'],
            (string) $validated['username'],
            $request->filters(),
        );

        return response()->json($body, $status);
    }
}
