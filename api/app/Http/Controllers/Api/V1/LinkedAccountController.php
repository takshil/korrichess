<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\AccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LinkAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LinkedAccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    /** POST /api/v1/me/linked-accounts — lier un pseudo (C3). */
    public function store(LinkAccountRequest $request): JsonResponse
    {
        [$status, $body] = $this->accounts->linkAccount(
            (int) $request->user()->id,
            (string) $request->validated('platform'),
            (string) $request->validated('username'),
        );

        return response()->json($body, $status);
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
