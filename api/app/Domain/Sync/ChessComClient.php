<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use Illuminate\Support\Facades\Http;

/**
 * Client HTTP réel de Chess.com (API publique api.chess.com). Les parties
 * y sont exposées par archives mensuelles : on ne récupère que les mois
 * postérieurs au curseur incrémental, dans la limite d'un plafond pour
 * borner les requêtes. Les tests injectent les réponses via Http::fake().
 */
final class ChessComClient implements PlatformClient
{
    /**
     * L'API Chess.com (derrière Cloudflare) rejette en 403 les requêtes sans
     * User-Agent descriptif : nom d'app + contact, comme demandé dans leur
     * documentation Published-Data.
     */
    private const USER_AGENT = 'korrichess/1.0 (contact: contact@korrichess.fr)';

    public function __construct(
        private readonly string $baseUrl = 'https://api.chess.com',
        private readonly int $maxArchives = 24,
        private readonly RateLimiter $limiter = new RateLimiter(60),
    ) {
    }

    /**
     * @param array{since?:?string,time_controls?:array<string>,period_start?:?string} $filters
     * @return array<int,array{external_id:string,pgn:string,played_at:?string}>
     */
    public function fetchGames(string $username, array $filters): array
    {
        $username = trim($username);
        if (!$this->validUsername($username)) {
            return [];
        }

        $this->limiter->wait('chesscom');
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('/pub/player/' . $username . '/games/archives');

        // Pseudo introuvable ou compte fermé (L8).
        if ($response->status() === 404) {
            return [];
        }
        $response->throw();

        $archives = $response->json('archives');
        if (!is_array($archives) || $archives === []) {
            return [];
        }

        // Les archives sont renvoyées de la plus ancienne à la plus récente ;
        // on ne garde que les mois du curseur ou postérieurs, puis les plus
        // récents dans la limite du plafond.
        $archives = array_values(array_map('strval', $archives));
        $sinceMonth = null;
        $since = $filters['since'] ?? null;
        if (is_string($since) && $since !== '' && ($timestamp = strtotime($since)) !== false) {
            $sinceMonth = gmdate('Y/m', $timestamp);
        }
        if ($sinceMonth !== null) {
            $archives = array_values(array_filter(
                $archives,
                static fn (string $url): bool => self::monthOf($url) >= $sinceMonth,
            ));
        }
        $archives = array_slice($archives, -$this->maxArchives);

        $games = [];
        foreach ($archives as $archiveUrl) {
            $this->limiter->wait('chesscom');
            $month = Http::timeout(15)
                ->retry(2, 500, throw: false)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($archiveUrl);
            // Archive temporairement indisponible : on passe à la suivante.
            if (!$month->ok()) {
                continue;
            }
            $monthGames = $month->json('games');
            if (!is_array($monthGames)) {
                continue;
            }
            foreach ($monthGames as $game) {
                if (!is_array($game)) {
                    continue;
                }
                $pgn = $game['pgn'] ?? null;
                if (!is_string($pgn) || $pgn === '') {
                    continue;
                }
                $games[] = [
                    'external_id' => $this->externalId($game, $pgn),
                    'pgn' => $pgn,
                    'played_at' => $this->playedAt($game),
                ];
            }
        }

        // Les plus récentes en premier (contrat de l'interface).
        usort($games, static fn (array $a, array $b): int => strcmp((string) $b['played_at'], (string) $a['played_at']));

        return $games;
    }

    /**
     * Le compte existe-t-il sur Chess.com (L8) ?
     */
    public function userExists(string $username): bool
    {
        $username = trim($username);
        if (!$this->validUsername($username)) {
            return false;
        }

        $this->limiter->wait('chesscom');
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('/pub/player/' . $username);

        return $response->status() === 200;
    }

    /**
     * Identifiant externe stable : dernier segment de l'URL de la partie.
     */
    private function externalId(array $game, string $pgn): string
    {
        $url = (string) ($game['url'] ?? '');
        if ($url !== '' && preg_match('#/([0-9A-Za-z-]+)$#', $url, $m) === 1) {
            return 'chesscom-' . $m[1];
        }

        return 'chesscom-' . substr(sha1($pgn . '|' . (string) ($game['endTime'] ?? $game['end_time'] ?? '')), 0, 16);
    }

    /**
     * Date de fin : epoch (champ end_time historique) ou chaîne ISO
     * (champ endTime actuel), normalisée au format du curseur.
     */
    private function playedAt(array $game): ?string
    {
        $end = $game['endTime'] ?? $game['end_time'] ?? null;
        if (is_numeric($end)) {
            return $this->iso((int) $end);
        }
        if (is_string($end) && ($timestamp = strtotime($end)) !== false) {
            return $this->iso($timestamp);
        }

        return null;
    }

    private function iso(int $timestamp): string
    {
        return gmdate('Y-m-d', $timestamp) . 'T' . gmdate('H:i:s', $timestamp) . 'Z';
    }

    /** Mois d'une archive, « YYYY/MM » comparable en chaînes. */
    private static function monthOf(string $url): string
    {
        if (preg_match('#/(\d{4}/\d{2})$#', $url, $m) === 1) {
            return $m[1];
        }

        return '0000/00';
    }

    private function validUsername(string $username): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,29}$/', $username);
    }
}
