<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Client en mémoire des plateformes, utilisé par les tests et le
 * développement local (aucun appel réseau).
 */
final class InMemoryPlatformClient implements PlatformClient
{
    /**
     * @param array<int,array{external_id:string,pgn:string,played_at:?string}> $games
     * @param array<int,string> $knownUsers pseudos renvoyés comme existants par userExists()
     */
    public function __construct(
        public array $games = [],
        public array $knownUsers = [],
    ) {
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

    public function userExists(string $username): bool
    {
        $username = strtolower(trim($username));

        foreach ($this->knownUsers as $known) {
            if (strtolower(trim((string) $known)) === $username) {
                return true;
            }
        }

        return false;
    }
}
