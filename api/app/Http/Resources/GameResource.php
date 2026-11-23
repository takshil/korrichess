<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON résumée d'une partie (liste de la bibliothèque, B1).
 *
 * @property Game $resource
 */
class GameResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $game = $this->resource;

        return [
            'id' => (int) $game->id,
            'source' => $game->source,
            'played_at' => $game->played_at,
            'opponent' => $game->opponent,
            'opponent_rating' => $game->opponent_rating !== null ? (int) $game->opponent_rating : null,
            'color' => $game->color,
            'result' => $game->result,
            'time_control' => $game->time_control,
            'opening' => $game->opening,
            'tournament' => $game->tournament,
            'round' => $game->round,
            'favorite' => (bool) $game->favorite,
            'personal_note' => $game->personal_note,
            'tags' => $game->tagList(),
            'needs_review' => (bool) $game->needs_review,
        ];
    }
}
