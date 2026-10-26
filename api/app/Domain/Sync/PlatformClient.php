<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Sync;

/**
 * Client HTTP des plateformes : abstraction remplaçable (les API peuvent changer,
 * cf. AGENTS.md « points sensibles »). Les tests injectent un client en mémoire.
 */
interface PlatformClient
{
    /** Retourne les parties du joueur, les plus récentes en premier.
     * @param array{since?:?string,time_controls?:array<string>,period_start?:?string} $filters
     * @return array<int,array{external_id:string,pgn:string,played_at:?string}>
     */
    public function fetchGames(string $username, array $filters): array;
}

final class InMemoryPlatformClient implements PlatformClient
{
    /** @param array<int,array{external_id:string,pgn:string,played_at:?string}> $games */
    public function __construct(public array $games = [])
    {
    }

    public function fetchGames(string $username, array $filters): array
    {
        $games = $this->games;
        usort($games, static fn ($a, $b) => strcmp((string) $b['played_at'], (string) $a['played_at']));
        if (!empty($filters['since'])) {
            $games = array_values(array_filter($games, static fn ($g) => (string) $g['played_at'] > (string) $filters['since']));
        }
        return $games;
    }
}

/**
 * Limites de requêtes : file d'attente locale avec fenêtre glissante et reprises
 * (chaque plateforme a son quota propre).
 */
final class RateLimiter
{
    /** @var array<string,array<int,int>> plateforme => timestamps des appels */
    private array $calls = [];

    public function __construct(private readonly int $maxPerMinute = 20)
    {
    }

    public function wait(string $platform): void
    {
        $now = time();
        $this->calls[$platform] = array_values(array_filter(
            $this->calls[$platform] ?? [],
            static fn ($t) => $t > $now - 60,
        ));
        if (count($this->calls[$platform]) >= $this->maxPerMinute) {
            sleep(1);
            $this->wait($platform);
            return;
        }
        $this->calls[$platform][] = $now;
    }
}
