<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Analysis\AnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AnalyseGameJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $analysisId,
        public readonly int $gameId,
        public readonly int $userId,
        public readonly int $depth = 12,
    ) {
    }

    /**
     * A10 : analyse en file — le worker exécute l'analyse complète et
     * persiste les résultats (game_analyses / analysis_moves).
     */
    public function handle(AnalysisService $service): void
    {
        $service->analyse($this->userId, $this->gameId, $this->depth);
    }
}
