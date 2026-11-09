<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analysis\AnalysisService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AnalyseRequest;
use App\Http\Requests\V1\ExploreRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnalysisController extends Controller
{
    /** GET /api/v1/games/{game}/replay — rejeu de la partie (A1). */
    public function replay(Request $request, AnalysisService $analysis, int $game): JsonResponse
    {
        $result = $analysis->replay((int) $request->user()->id, $game);

        return response()->json($result['body'], $result['status']);
    }

    /** POST /api/v1/games/{game}/analyse — analyse à la demande (A10). */
    public function request(AnalyseRequest $request, AnalysisService $analysis, int $game): JsonResponse
    {
        $result = $analysis->request(
            (int) $request->user()->id,
            $game,
            (int) ($request->validated('depth') ?? 12),
        );

        return response()->json($result['body'], $result['status']);
    }

    /** GET /api/v1/games/{game}/analyse — dernier résultat (A2–A7, A9). */
    public function result(Request $request, AnalysisService $analysis, int $game): JsonResponse
    {
        $result = $analysis->result((int) $request->user()->id, $game);

        return response()->json($result['body'], $result['status']);
    }

    /** POST /api/v1/games/{game}/explore — essayer un autre coup (A8). */
    public function explore(ExploreRequest $request, AnalysisService $analysis, int $game): JsonResponse
    {
        $result = $analysis->explore(
            (int) $request->user()->id,
            $game,
            (int) $request->validated('ply'),
            (string) $request->validated('move_uci'),
        );

        return response()->json($result['body'], $result['status']);
    }
}
