<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Shared\DomainError;
use App\Models\GameAnalysis;

/** A2–A6, A9 : lecture de la dernière analyse enregistrée d'une partie. */
final class AnalysisReader
{
    /**
     * @throws DomainError aucune analyse pour cette partie
     */
    public function latest(int $userId, int $gameId): GameAnalysis
    {
        $analysis = GameAnalysis::query()
            ->forGame($userId, $gameId)
            ->orderByDesc('id')
            ->first();

        if ($analysis === null) {
            throw DomainError::notFound('analyse_introuvable');
        }

        return $analysis->load(['moves' => static fn ($q) => $q->orderBy('ply')]);
    }
}
