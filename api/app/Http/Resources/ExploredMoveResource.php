<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Analysis\ExploredMove;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON d'un coup alternatif exploré (A8).
 *
 * @property ExploredMove $resource
 */
final class ExploredMoveResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ply' => $this->resource->ply,
            'move_uci' => $this->resource->moveUci,
            'move_san' => $this->resource->moveSan,
            'fen' => $this->resource->fen,
            'eval_cp' => $this->resource->evalCp,
        ];
    }
}
