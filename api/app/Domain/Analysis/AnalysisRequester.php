<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Games\GameRepository;
use App\Jobs\AnalyseGameJob;
use App\Models\GameAnalysis;

/** A10 : enfile une analyse à la demande (le travail est fait par GameAnalyser, en arrière-plan). */
final class AnalysisRequester
{
    public function __construct(
        private readonly EngineClient $engine,
        private readonly GameRepository $games,
    ) {
    }

    public function request(int $userId, int $gameId, ?int $depth = null): AnalysisRequestResult
    {
        $this->games->findOwnedOrFail($userId, $gameId);

        $latest = GameAnalysis::query()->forGame($userId, $gameId)->orderByDesc('id')->first();
        if ($latest !== null && $latest->status === 'done') {
            return new AnalysisRequestResult($latest, AnalysisRequestOutcome::AlreadyDone);
        }

        $pending = GameAnalysis::query()->forGame($userId, $gameId)->where('status', 'pending')->first();
        if ($pending !== null) {
            return new AnalysisRequestResult($pending, AnalysisRequestOutcome::AlreadyPending);
        }

        $depth = AnalysisDepth::clamp($depth);
        $analysis = GameAnalysis::create([
            'game_id' => $gameId,
            'user_id' => $userId,
            'depth' => $depth,
            'engine' => $this->engine->name(),
            'status' => 'pending',
        ]);

        AnalyseGameJob::dispatch((int) $analysis->id, $gameId, $userId, $depth);

        return new AnalysisRequestResult($analysis, AnalysisRequestOutcome::Queued);
    }
}
