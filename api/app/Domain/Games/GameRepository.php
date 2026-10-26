<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Games;

use Korrichess\Api\Core\Database;
use Korrichess\Api\Domain\Chess\Pgn;

final class GameRepository
{
    /** Insère ou met à jour une partie ; true si nouvelle, false si doublon ignoré. */
    public function upsertFromPgn(int $userId, string $source, string $pgn, ?string $externalId, array $meta = []): bool
    {
        $uci = Pgn::movesToUci($pgn);
        if ($uci === null) {
            return false;
        }
        $key = $externalId ?? hash('sha256', $userId . '|' . $source . '|' . trim(preg_replace('/\[[^\]]*\]/', '', $pgn) ?? ''));
        $existing = Database::one(
            'SELECT id FROM games WHERE user_id = :u AND source = :s AND external_id = :e',
            ['u' => $userId, 's' => $source, 'e' => $key],
        );
        if ($existing !== null) {
            return false;
        }
        $now = gmdate('c');
        Database::run(
            'INSERT INTO games (user_id, source, external_id, played_at, opponent, opponent_rating, color, result, time_control, opening, tournament, round, pgn, moves_uci, status, created_at, updated_at)
             VALUES (:u, :s, :e, :played, :opp, :oppr, :color, :res, :tc, :open, :tour, :round, :pgn, :uci, :status, :now, :now)',
            [
                'u' => $userId,
                's' => $source,
                'e' => $key,
                'played' => $meta['played_at'] ?? null,
                'opp' => $meta['opponent'] ?? null,
                'oppr' => isset($meta['opponent_rating']) ? (int) $meta['opponent_rating'] : null,
                'color' => $meta['color'] ?? null,
                'res' => $meta['result'] ?? null,
                'tc' => $meta['time_control'] ?? null,
                'open' => $meta['opening'] ?? null,
                'tour' => $meta['tournament'] ?? null,
                'round' => $meta['round'] ?? null,
                'pgn' => $pgn,
                'uci' => implode(' ', $uci),
                'status' => 'final',
                'now' => $now,
            ],
        );
        return true;
    }

    public function movesUci(int $gameId): array
    {
        $row = Database::one('SELECT moves_uci FROM games WHERE id = :id', ['id' => $gameId]);
        $raw = trim((string) ($row['moves_uci'] ?? ''));
        return $raw === '' ? [] : explode(' ', $raw);
    }
}
