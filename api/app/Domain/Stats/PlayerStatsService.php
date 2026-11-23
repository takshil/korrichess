<?php

declare(strict_types=1);

namespace App\Domain\Stats;

use App\Domain\Games\GameRepository;

/**
 * Agrégats du tableau de bord (W2), calculés en base sur TOUTES les
 * parties : le front ne peut pas les déduire d'une liste paginée sans
 * sous-estimer les totaux.
 */
final class PlayerStatsService
{
    public function __construct(private readonly GameRepository $games)
    {
    }

    /** @return array{total:int,victoires:int,nulles:int,defaites:int,taux_victoires:int} */
    public function forUser(int $userId): array
    {
        $counts = $this->games->resultCounts($userId);

        return $counts + [
            'taux_victoires' => $counts['total'] > 0
                ? (int) round($counts['victoires'] * 100 / $counts['total'])
                : 0,
        ];
    }
}
