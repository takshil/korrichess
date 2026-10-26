<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

use Korrichess\Api\Domain\Auth\AuthService;

final class AuthMiddleware
{
    public static function requireUser(Request $request): ?array
    {
        $auth = new AuthService();
        $userId = $auth->userIdForToken($request->bearerToken());
        if ($userId === null) {
            return ['status' => 401, 'body' => ['error' => 'token_invalide_ou_absent']];
        }
        return null;
    }

    public static function userId(Request $request): int
    {
        $auth = new AuthService();
        $userId = $auth->userIdForToken($request->bearerToken());
        if ($userId === null) {
            throw new RuntimeException('non_authentifie');
        }
        return $userId;
    }
}
