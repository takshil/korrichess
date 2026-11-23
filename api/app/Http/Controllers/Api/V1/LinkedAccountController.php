<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\LinkedAccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LinkAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LinkedAccountController extends Controller
{
    public function __construct(private readonly LinkedAccountService $accounts)
    {
    }

    /** POST /api/v1/me/linked-accounts — lier un pseudo (C3). */
    public function store(LinkAccountRequest $request): JsonResponse
    {
        $created = $this->accounts->link(
            $request->user(),
            (string) $request->validated('platform'),
            (string) $request->validated('username'),
        );

        return $created
            ? response()->json(['message' => 'compte_lie'], 201)
            : response()->json(['message' => 'deja_lie']);
    }

    /** DELETE /api/v1/me/linked-accounts — délier un pseudo (C3). */
    public function destroy(Request $request): JsonResponse
    {
        $this->accounts->unlink(
            $request->user(),
            (string) $request->input('platform', ''),
            (string) $request->input('username', ''),
        );

        return response()->json(['message' => 'delie']);
    }
}
