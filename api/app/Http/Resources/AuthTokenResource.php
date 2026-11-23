<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auth\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON du jeton émis à l'inscription / connexion (C1).
 *
 * @property AuthToken $resource
 */
final class AuthTokenResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['user_id' => $this->resource->userId, 'token' => $this->resource->token];
    }
}
