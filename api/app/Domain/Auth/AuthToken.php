<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/** Jeton d'accès émis à l'inscription ou à la connexion. */
final class AuthToken
{
    public function __construct(
        public readonly int $userId,
        public readonly string $token,
    ) {
    }
}
