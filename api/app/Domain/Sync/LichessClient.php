<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use Illuminate\Support\Facades\Http;

/**
 * Client HTTP réel de Lichess (API publique documentée sur lichess.org/api).
 * Les tests injectent les réponses via Http::fake() : aucun appel réseau
 * dans la suite automatisée. L'abstraction reste remplaçable si l'API
 * évolue (AGENTS.md « points sensibles »).
 *
 * L'export d'un historique complet dépasse le timeout HTTP d'un compte actif
 * (constaté en production : 30 s pour 1,2 Mo sans fin de flux) : les parties
 * sont donc récupérées par lots de 50, du plus récent vers le plus ancien,
 * via les curseurs since / until de l'API.
 */
final class LichessClient implements PlatformClient
{
    private const LOT = 50;

    /** Garde-fou : 60 lots = 3 000 parties par passage ; les passages suivants convergent (dédoublonnage L6). */
    private const LOTS_MAX = 60;

    public function __construct(
        private readonly string $baseUrl = 'https://lichess.org',
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /**
     * Export NDJSON paginé des parties du joueur : une partie JSON par ligne,
     * PGN inclus via pgnInJson. Le curseur incrémental since (ISO 8601) est
     * converti en millisecondes comme l'attend l'API ; la partie à la limite
     * d'un lot peut revenir au suivant, le dédoublonnage en amont l'ignore (L6).
     *
     * @param array{since?:?string,time_controls?:array<string>,period_start?:?string} $filters
     * @return array<int,array{external_id:string,pgn:string,played_at:?string}>
     */
    public function fetchGames(string $username, array $filters): array
    {
        $username = trim($username);
        if (!$this->validUsername($username)) {
            return [];
        }

        $since = null;
        $brutSince = $filters['since'] ?? null;
        if (is_string($brutSince) && $brutSince !== '' && ($timestamp = strtotime($brutSince)) !== false) {
            $since = (string) ($timestamp * 1000);
        }

        $games = [];
        $until = null;
        for ($lot = 0; $lot < self::LOTS_MAX; $lot++) {
            $batch = $this->fetchBatch($username, $since, $until);
            if ($batch['games'] === []) {
                break;
            }
            $games = array_merge($games, $batch['games']);
            if (count($batch['games']) < self::LOT || $batch['oldest_ms'] === null) {
                break;
            }
            $until = (string) $batch['oldest_ms'];
        }

        // Les plus récentes en premier (contrat de l'interface).
        usort($games, static fn (array $a, array $b): int => strcmp((string) $b['played_at'], (string) $a['played_at']));

        return $games;
    }

    /**
     * Un lot de parties.
     *
     * @return array{games:array<int,array{external_id:string,pgn:string,played_at:?string}>,oldest_ms:?int}
     */
    private function fetchBatch(string $username, ?string $since, ?string $until): array
    {
        $query = [
            'max' => (string) self::LOT,
            'moves' => 'true',
            'pgnInJson' => 'true',
            'clocks' => 'false',
            'evals' => 'false',
            'opening' => 'false',
        ];
        if ($since !== null) {
            $query['since'] = $since;
        }
        if ($until !== null) {
            $query['until'] = $until;
        }

        $this->limiter->wait('lichess');
        $response = Http::baseUrl($this->baseUrl)
            ->accept('application/x-ndjson')
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->get('/api/games/user/' . $username, $query);

        // Pseudo introuvable ou compte fermé (L8) : rien à importer.
        if ($response->status() === 404) {
            return ['games' => [], 'oldest_ms' => null];
        }
        $response->throw();

        $games = [];
        $oldest = null;
        foreach (explode("\n", trim((string) $response->body())) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $data = json_decode($line, true);
            if (!is_array($data) || !isset($data['id'], $data['pgn'])) {
                continue;
            }
            $createdAt = (int) ($data['createdAt'] ?? 0);
            if ($createdAt > 0 && ($oldest === null || $createdAt < $oldest)) {
                $oldest = $createdAt;
            }
            $games[] = [
                'external_id' => (string) $data['id'],
                'pgn' => (string) $data['pgn'],
                'played_at' => $createdAt > 0 ? $this->iso($createdAt / 1000) : null,
            ];
        }

        return ['games' => $games, 'oldest_ms' => $oldest];
    }

    /**
     * Le compte existe-t-il sur Lichess (L8) ? Un compte fermé répond 404.
     */
    public function userExists(string $username): bool
    {
        $username = trim($username);
        if (!$this->validUsername($username)) {
            return false;
        }

        $this->limiter->wait('lichess');
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->get('/api/user/' . $username);

        return $response->status() === 200;
    }

    /**
     * Date ISO 8601 UTC, même format que le curseur incrémental mémorisé.
     */
    private function iso(float|int $timestamp): string
    {
        return gmdate('Y-m-d', (int) $timestamp) . 'T' . gmdate('H:i:s', (int) $timestamp) . 'Z';
    }

    private function validUsername(string $username): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,29}$/', $username);
    }
}
