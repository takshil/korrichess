<?php

declare(strict_types=1);

namespace Korrichess\Api\Support;

function korri_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function korri_json_response(int $status, $data): array
{
    return ['status' => $status, 'body' => $data];
}

function korri_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function korri_verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}
