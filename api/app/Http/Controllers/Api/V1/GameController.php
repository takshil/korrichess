<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Library\LibraryService;
use App\Domain\Library\PgnExporter;
use App\Domain\Library\PositionSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AnnotateGameRequest;
use App\Http\Requests\V1\ExportGamesRequest;
use App\Http\Requests\V1\ListGamesRequest;
use App\Http\Requests\V1\SearchPositionRequest;
use App\Http\Resources\GameDetailResource;
use App\Http\Resources\GamePageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameController extends Controller
{
    public function __construct(private readonly LibraryService $library)
    {
    }

    /**
     * GET /api/v1/games — liste, filtres, recherche texte, tri, pagination
     * (B1–B3, issue #4).
     */
    public function index(ListGamesRequest $request): GamePageResource
    {
        return new GamePageResource($this->library->list(
            (int) $request->user()->id,
            $request->filters(),
            $request->limit(),
            $request->offset(),
            $request->sortColumn(),
            $request->sortOrder(),
        ));
    }

    /** GET /api/v1/games/{game} — détail d'une partie, PGN inclus (B1). */
    public function show(Request $request, int $game): GameDetailResource
    {
        return new GameDetailResource($this->library->detail((int) $request->user()->id, $game));
    }

    /** PATCH /api/v1/games/{game} — note, étiquettes, favori (B4). */
    public function update(AnnotateGameRequest $request, int $game): GameDetailResource
    {
        return new GameDetailResource(
            $this->library->annotate((int) $request->user()->id, $game, $request->validated()),
        );
    }

    /** POST /api/v1/games/export — export PGN d'une sélection (B5). */
    public function export(ExportGamesRequest $request, PgnExporter $exporter): JsonResponse
    {
        return response()->json($exporter->export(
            (int) $request->user()->id,
            array_map('intval', (array) $request->validated('game_ids')),
        ));
    }

    /** POST /api/v1/games/search-position — recherche par position (B6). */
    public function searchPosition(SearchPositionRequest $request, PositionSearch $search): JsonResponse
    {
        return response()->json($search->search(
            (int) $request->user()->id,
            (string) $request->validated('fen'),
        ));
    }
}
