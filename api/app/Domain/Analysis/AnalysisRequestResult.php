<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Models\GameAnalysis;

/** Résultat d'une demande d'analyse : la ligne d'analyse et ce qui s'est passé. */
final class AnalysisRequestResult
{
    public function __construct(
        public readonly GameAnalysis $analysis,
        public readonly AnalysisRequestOutcome $outcome,
    ) {
    }
}
