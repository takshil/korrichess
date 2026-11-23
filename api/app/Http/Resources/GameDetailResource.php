<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Vue JSON détaillée d'une partie : PGN, coups UCI et saisie brute (B1, S5). */
final class GameDetailResource extends GameResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'pgn' => $this->resource->pgn,
            'moves_uci' => $this->resource->uciMoves(),
            // Saisie manuelle sans validation : coups tels qu'ils ont été
            // tapés, pour rouvrir la correction là où la feuille s'était trompée.
            'raw_moves' => $this->resource->rawMoveList(),
        ];
    }
}
