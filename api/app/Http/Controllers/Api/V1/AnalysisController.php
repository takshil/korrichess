<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analysis\AnalysisReader;
use App\Domain\Analysis\AnalysisRequester;
use App\Domain\Analysis\GameReplayer;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AnalyseRequest;
use App\Http\Requests\V1\ExploreRequest;
use App\Http\Resources\AnalysisRequestResource;
use App\Http\Resources\AnalysisResource;
use App\Http\Resources\ExploredMoveResource;
use App\Http\Resources\ReplayResource;
use Illuminate\Http\Request;

final class AnalysisController extends Controller
{
    /** GET /api/v1/games/{game}/replay — rejeu de la partie (A1). */
    public function replay(Request $request, GameReplayer $replayer, int $game): ReplayResource
    {
        return new ReplayResource($replayer->replay((int) $request->user()->id, $game));
    }

    /** POST /api/v1/games/{game}/analyse — analyse à la demande (A10). */
    public function request(AnalyseRequest $request, AnalysisRequester $requester, int $game): AnalysisRequestResource
    {
        $depth = $request->validated('depth');

        return new AnalysisRequestResource($requester->request(
            (int) $request->user()->id,
            $game,
            $depth === null ? null : (int) $depth,
        ));
    }

    /** GET /api/v1/games/{game}/analyse — dernier résultat (A2–A7, A9). */
    public function result(Request $request, AnalysisReader $reader, int $game): AnalysisResource
    {
        return new AnalysisResource($reader->latest((int) $request->user()->id, $game));
    }

    /** POST /api/v1/games/{game}/explore — essayer un autre coup (A8). */
    public function explore(ExploreRequest $request, GameReplayer $replayer, int $game): ExploredMoveResource
    {
        return new ExploredMoveResource($replayer->explore(
            (int) $request->user()->id,
            $game,
            (int) $request->validated('ply'),
            (string) $request->validated('move_uci'),
        ));
    }
}
