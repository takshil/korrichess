<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Domain\Shared\DomainError;
use App\Domain\Shared\ErrorKind;
use Illuminate\Http\JsonResponse;

/**
 * Traduit une DomainError en réponse JSON `{"error": "<code>", ...contexte}`.
 * Seul endroit où le domaine rencontre les codes de statut HTTP.
 */
final class DomainErrorRenderer
{
    public function __invoke(DomainError $e): JsonResponse
    {
        $status = match ($e->kind) {
            ErrorKind::NotFound => 404,
            ErrorKind::Invalid => 422,
            ErrorKind::Conflict => 409,
            ErrorKind::Unauthenticated => 401,
        };

        return response()->json(['error' => $e->errorCode] + $e->context, $status);
    }
}
