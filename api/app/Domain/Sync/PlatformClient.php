<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Client HTTP des plateformes : abstraction remplaçable (les API peuvent
 * changer, cf. AGENTS.md « points sensibles »). Les tests injectent un client
 * en mémoire.
 */
interface PlatformClient
{
    /**
     * Retourne les parties du joueur, les plus récentes en premier.
     *
     * @param array{since?:?string,time_controls?:array<string>,period_start?:?string} $filters
     * @return array<int,array{external_id:string,pgn:string,played_at:?string}>
     */
    public function fetchGames(string $username, array $filters): array;

    /**
     * Le compte existe-t-il sur la plateforme (L8) ? Un compte fermé
     * ou privé est considéré comme introuvable.
     */
    public function userExists(string $username): bool;
}
