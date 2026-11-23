<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Library\GamePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON d'une page de la bibliothèque (B1–B2).
 *
 * @property GamePage $resource
 */
final class GamePageResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'games' => GameResource::collection($this->resource->games)->resolve($request),
            'total' => $this->resource->total,
            'limit' => $this->resource->limit,
            'offset' => $this->resource->offset,
        ];
    }
}
