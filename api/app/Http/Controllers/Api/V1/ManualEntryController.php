<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Entry\ManualEntryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ManualGameRequest;
use App\Http\Requests\V1\PreviewMovesRequest;
use App\Http\Resources\GameDetailResource;
use Illuminate\Http\JsonResponse;

final class ManualEntryController extends Controller
{
    public function __construct(private readonly ManualEntryService $entry)
    {
    }

    /**
     * POST /api/v1/games/manual/preview — rejoue des coups sans enregistrer :
     * FEN atteinte, SAN, premier coup impossible et coups légaux suivants (S5).
     */
    public function preview(PreviewMovesRequest $request): JsonResponse
    {
        return response()->json($this->entry->preview((array) $request->validated('moves')));
    }

    /**
     * POST /api/v1/games/manual — enregistre une partie saisie à la main,
     * avec sa fiche (S5 + S7).
     */
    public function store(ManualGameRequest $request): JsonResponse
    {
        $game = $this->entry->store((int) $request->user()->id, (array) $request->validated());

        return (new GameDetailResource($game))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/games/{game}/manual — corrige une saisie manuelle,
     * fiche et coups remplacés en entier (S7).
     */
    public function update(ManualGameRequest $request, int $game): GameDetailResource
    {
        return new GameDetailResource(
            $this->entry->update((int) $request->user()->id, $game, (array) $request->validated()),
        );
    }
}
