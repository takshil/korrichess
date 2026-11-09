<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use Illuminate\Support\Facades\Http;

/**
 * Client HTTP réel de Lichess (API publique documentée sur lichess.org/api).
 * Les tests injectent les réponses via Http::fake() : aucun appel réseau
 * dans la suite automatisée. L'abstraction reste remplaçable si l'API
 * évolue (AGENTS.md « points sensibles »).
 */
final class LichessClient implements PlatformClient
{
    public function __construct(
        private readonly string $baseUrl = 'https://lichess.org',
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /**
     * Export NDJSON des parties du joueur : une partie JSON par ligne, PGN
     * inclus via pgnInJson. `since` (curseur incrémental, ISO 8601) est
     * converti en millisecondes comme l'attend l'API ; la partie à la
     * limite peut revenir, le dédoublonnage en amont l'ignore (L6).
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

        $query = [
            'moves' => 'true',
            'pgnInJson' => 'true',
            'clocks' => 'false',
            'evals' => 'false',
            'opening' => 'false',
        ];
        $since = $filters['since'] ?? null;
        if (is_string($since) && $since !== '' && ($timestamp = strtotime($since)) !== false) {
            $query['since'] = (string) ($timestamp * 1000);
        }

        $this->limiter->wait('lichess');
        $response = Http::baseUrl($this->baseUrl)
            ->accept('application/x-ndjson')
            ->timeout(30)
            ->retry(2, 500, throw: false)
            ->get('/api/games/user/' . $username, $query);

        // Pseudo introuvable ou compte fermé (L8) : rien à importer.
        if ($response->status() === 404) {
            return [];
        }
        $response->throw();

        $games = [];
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
            $games[] = [
                'external_id' => (string) $data['id'],
                'pgn' => (string) $data['pgn'],
                'played_at' => $createdAt > 0 ? $this->iso($createdAt / 1000) : null,
            ];
        }

        // Les plus récentes en premier (contrat de l'interface).
        usort($games, static fn (array $a, array $b): int => strcmp((string) $b['played_at'], (string) $a['played_at']));

        return $games;
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
