<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use App\Domain\Chess\Pgn;
use App\Domain\Games\GameRepository;
use App\Jobs\SyncPlatformJob;
use Illuminate\Support\Facades\DB;

final class SyncService
{
    /**
     * @param array<string,PlatformClient> $clients plateforme => client
     */
    public function __construct(private readonly array $clients = [])
    {
    }

    /**
     * Synchronisation incrémentale d'un compte lié (L3) : ne récupère que les
     * parties plus récentes que la dernière mémorisée (L4 récentes d'abord).
     *
     * @param array{time_controls?:array<string>,period_start?:?string} $filters
     * @return array{status:int,body:array<string,mixed>}
     */
    public function sync(int $userId, string $platform, string $username, array $filters = []): array
    {
        $platform = strtolower(trim($platform));
        $client = $this->clients[$platform] ?? null;
        if ($client === null) {
            return ['status' => 422, 'body' => ['error' => 'plateforme_inconnue']];
        }
        $username = trim($username);
        if ($username === '') {
            return ['status' => 422, 'body' => ['error' => 'pseudo_invalide']];
        }

        $state = $this->state($userId, $platform, $username);
        $since = $state['last_game_at'] ?? ($filters['period_start'] ?? null);
        $fetchFilters = [
            'since' => $since,
            'time_controls' => $filters['time_controls'] ?? [],
            'period_start' => $filters['period_start'] ?? null,
        ];

        $repo = new GameRepository();
        $imported = 0;
        $duplicates = 0;
        $newest = $since;
        foreach ($client->fetchGames($username, $fetchFilters) as $game) {
            $meta = $this->metaFromPgn($game['pgn'], $username);
            if (($game['played_at'] ?? null) !== null) {
                $meta['played_at'] = $game['played_at'];
            }
            if (!$this->passesFilters($game, $meta, $filters)) {
                continue;
            }
            $created = $repo->upsertFromPgn($userId, $platform, $game['pgn'], $game['external_id'], $meta);
            if ($created) {
                $imported++;
                if ($game['played_at'] !== null && ($newest === null || $game['played_at'] > $newest)) {
                    $newest = $game['played_at'];
                }
            } else {
                $duplicates++;
            }
        }

        $this->saveState($userId, $platform, $username, $newest);

        return ['status' => 200, 'body' => [
            'platform' => $platform,
            'username' => $username,
            'imported' => $imported,
            'duplicates_ignores' => $duplicates,
        ]];
    }

    /**
     * Pseudos introuvables / comptes fermés ou privés (L8).
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function verifyUsername(string $platform, string $username): array
    {
        $platform = strtolower(trim($platform));
        $client = $this->clients[$platform] ?? null;
        if ($client === null) {
            return ['status' => 422, 'body' => ['error' => 'plateforme_inconnue']];
        }

        $username = trim($username);

        return ['status' => 200, 'body' => [
            'platform' => $platform,
            'username' => $username,
            'status' => $client->userExists($username) ? 'connu' : 'inconnu',
        ]];
    }

    /**
     * Plateformes clientes disponibles (validation synchrone du bouton).
     *
     * @return array<int,string>
     */
    public function platforms(): array
    {
        return array_keys($this->clients);
    }

    /**
     * Enregistre une sync automatique en file (L7) : le job rejouera la
     * synchronisation incrémentale en tâche de fond, filtres compris.
     *
     * @param array{time_controls?:array<string>,period_start?:?string} $filters
     */
    public function scheduleAutoSync(int $userId, string $platform, string $username, array $filters = []): void
    {
        SyncPlatformJob::dispatch($userId, strtolower(trim($platform)), $username, $filters);
    }

    /**
     * Curseur incrémental d'un compte lié.
     *
     * @return array{last_game_at:?string,last_sync_at:?string}
     */
    public function state(int $userId, string $platform, string $username): array
    {
        $row = DB::table('sync_state')
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->where('username', $username)
            ->first();
        if ($row === null) {
            return ['last_game_at' => null, 'last_sync_at' => null];
        }

        return ['last_game_at' => $row->last_game_at, 'last_sync_at' => $row->last_sync_at];
    }

    private function saveState(int $userId, string $platform, string $username, ?string $lastGameAt): void
    {
        DB::table('sync_state')->upsert(
            [
                'user_id' => $userId,
                'platform' => $platform,
                'username' => $username,
                'last_game_at' => $lastGameAt,
                'last_sync_at' => gmdate('c'),
            ],
            ['user_id', 'platform', 'username'],
            ['last_game_at', 'last_sync_at'],
        );
    }

    /**
     * Métadonnées extraites des en-têtes PGN ; l'adversaire et la couleur sont
     * déduits du pseudo synchronisé.
     *
     * @return array<string,mixed>
     */
    private function metaFromPgn(string $pgn, string $username): array
    {
        $parsed = Pgn::parse($pgn);
        $h = $parsed['headers'];
        $white = $h['White'] ?? null;
        $black = $h['Black'] ?? null;
        $meta = [
            'played_at' => $h['UTCDate'] ?? $h['Date'] ?? null,
            'opening' => $h['ECO'] ?? $h['Opening'] ?? null,
            'time_control' => $h['TimeControl'] ?? null,
            'result' => $h['Result'] ?? null,
            'tournament' => $h['Event'] ?? null,
        ];
        if ($white !== null && $black !== null) {
            if (strcasecmp((string) $white, $username) === 0) {
                $meta['opponent'] = $black;
                $meta['color'] = 'white';
            } else {
                $meta['opponent'] = $white;
                $meta['color'] = 'black';
            }
        }

        return $meta;
    }

    /**
     * @param array<string,mixed> $game
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $filters
     */
    private function passesFilters(array $game, array $meta, array $filters): bool
    {
        if (!empty($filters['time_controls'])) {
            $tc = (string) ($meta['time_control'] ?? '');
            $matched = false;
            foreach ($filters['time_controls'] as $keep) {
                if ($tc === $keep) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }
        if (!empty($filters['period_start']) && ($game['played_at'] ?? null) !== null) {
            if ((string) $game['played_at'] < (string) $filters['period_start']) {
                return false;
            }
        }

        return true;
    }
}
