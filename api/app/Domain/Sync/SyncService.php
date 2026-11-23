<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;

/**
 * Synchronisation incrémentale d'un compte lié (L3) : ne récupère que les
 * parties plus récentes que la dernière mémorisée (L4 récentes d'abord).
 */
final class SyncService
{
    public function __construct(
        private readonly PlatformRegistry $platforms,
        private readonly GameRepository $games,
        private readonly SyncStateRepository $states,
        private readonly PgnMetadataExtractor $metadata,
    ) {
    }

    /**
     * @param array{time_controls?:array<string>,period_start?:?string} $filters
     *
     * @throws DomainError plateforme inconnue ou pseudo vide
     */
    public function sync(int $userId, string $platform, string $username, array $filters = []): SyncReport
    {
        $platform = PlatformRegistry::normalise($platform);
        $client = $this->platforms->client($platform)
            ?? throw DomainError::invalid('plateforme_inconnue');
        $username = trim($username);
        if ($username === '') {
            throw DomainError::invalid('pseudo_invalide');
        }

        $syncFilters = SyncFilters::fromArray($filters);
        $since = $this->states->cursor($userId, $platform, $username)['last_game_at']
            ?? $syncFilters->periodStart;

        $imported = 0;
        $duplicates = 0;
        $newest = $since;
        foreach ($client->fetchGames($username, [
            'since' => $since,
            'time_controls' => $syncFilters->timeControls,
            'period_start' => $syncFilters->periodStart,
        ]) as $game) {
            $meta = $this->metadata->extract($game['pgn'], $username);
            if (($game['played_at'] ?? null) !== null) {
                $meta['played_at'] = $game['played_at'];
            }
            if (!$syncFilters->accepts($game, $meta)) {
                continue;
            }

            if ($this->games->upsertFromPgn($userId, $platform, $game['pgn'], $game['external_id'], $meta)) {
                $imported++;
                if ($game['played_at'] !== null && ($newest === null || $game['played_at'] > $newest)) {
                    $newest = $game['played_at'];
                }
            } else {
                $duplicates++;
            }
        }

        $this->states->save($userId, $platform, $username, $newest);

        return new SyncReport($platform, $username, $imported, $duplicates);
    }

    /**
     * L8 : pseudos introuvables / comptes fermés ou privés.
     *
     * @return 'connu'|'inconnu'
     *
     * @throws DomainError plateforme inconnue
     */
    public function verifyUsername(string $platform, string $username): string
    {
        $client = $this->platforms->client($platform)
            ?? throw DomainError::invalid('plateforme_inconnue');

        return $client->userExists(trim($username)) ? 'connu' : 'inconnu';
    }
}
