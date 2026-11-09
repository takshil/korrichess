<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Library\LibraryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AnnotateGameRequest;
use App\Http\Requests\V1\ExportGamesRequest;
use App\Http\Requests\V1\ListGamesRequest;
use App\Http\Requests\V1\SearchPositionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameController extends Controller
{
    public function __construct(private readonly LibraryService $library = new LibraryService())
    {
    }

    /**
     * GET /api/v1/games — liste, filtres, recherche texte, tri, pagination
     * (B1–B3, issue #4).
     */
    public function index(ListGamesRequest $request): JsonResponse
    {
        $q = $request->query();
        $filters = [];
        foreach (['source', 'opponent', 'opening', 'result', 'color', 'tournament', 'time_control', 'period_start', 'period_end', 'q', 'favorite', 'tag'] as $key) {
            if (isset($q[$key]) && $q[$key] !== '' && $q[$key] !== null) {
                $filters[$key] = $key === 'favorite' ? (bool) $q[$key] : (string) $q[$key];
            }
        }

        $result = $this->library->list(
            (int) $request->user()->id,
            $filters,
            (int) ($q['limit'] ?? 50),
            (int) ($q['offset'] ?? 0),
            (string) ($q['sort'] ?? 'played_at'),
            (string) ($q['order'] ?? 'desc'),
        );

        return response()->json($result['body'], $result['status']);
    }

    /** GET /api/v1/games/{game} — détail d'une partie, PGN inclus (B1). */
    public function show(Request $request, int $game): JsonResponse
    {
        $result = $this->library->detail((int) $request->user()->id, $game);

        return response()->json($result['body'], $result['status']);
    }

    /** PATCH /api/v1/games/{game} — note, étiquettes, favori (B4). */
    public function update(AnnotateGameRequest $request, int $game): JsonResponse
    {
        $result = $this->library->annotate((int) $request->user()->id, $game, $request->validated());

        return response()->json($result['body'], $result['status']);
    }

    /** POST /api/v1/games/export — export PGN d'une sélection (B5). */
    public function export(ExportGamesRequest $request): JsonResponse
    {
        $result = $this->library->exportPgn(
            (int) $request->user()->id,
            array_map('intval', (array) $request->validated('game_ids')),
        );

        return response()->json($result['body'], $result['status']);
    }

    /** POST /api/v1/games/search-position — recherche par position (B6). */
    public function searchPosition(SearchPositionRequest $request): JsonResponse
    {
        $result = $this->library->searchByPosition(
            (int) $request->user()->id,
            (string) $request->validated('fen'),
        );

        return response()->json($result['body'], $result['status']);
    }
}
