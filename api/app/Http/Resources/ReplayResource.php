<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Analysis\ReplayedGame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON du rejeu d'une partie (A1).
 *
 * @property ReplayedGame $resource
 */
final class ReplayResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $replay = $this->resource;

        return [
            'game_id' => (int) $replay->game->id,
            'source' => $replay->game->source,
            'result' => $replay->game->result,
            'moves_san' => $replay->movesSan,
            'moves_uci' => $replay->movesUci,
            'positions' => $replay->positions,
        ];
    }
}
