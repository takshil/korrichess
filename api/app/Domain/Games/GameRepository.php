<?php

declare(strict_types=1);

namespace App\Domain\Games;

use App\Domain\Chess\Pgn;
use App\Models\Game;

final class GameRepository
{
    /**
     * Insère une partie issue d'un PGN ; true si nouvelle, false si doublon
     * déjà importé ou PGN invalide (import idempotent, L6).
     *
     * @param array<string,mixed> $meta
     */
    public function upsertFromPgn(int $userId, string $source, string $pgn, ?string $externalId, array $meta = []): bool
    {
        $uci = Pgn::movesToUci($pgn);
        if ($uci === null) {
            return false;
        }

        $key = $externalId ?? hash(
            'sha256',
            $userId . '|' . $source . '|' . trim((string) preg_replace('/\[[^\]]*\]/', '', $pgn)),
        );

        $exists = Game::query()
            ->where('user_id', $userId)
            ->where('source', $source)
            ->where('external_id', $key)
            ->exists();
        if ($exists) {
            return false;
        }

        Game::create([
            'user_id' => $userId,
            'source' => $source,
            'external_id' => $key,
            'played_at' => $meta['played_at'] ?? null,
            'opponent' => $meta['opponent'] ?? null,
            'opponent_rating' => isset($meta['opponent_rating']) ? (int) $meta['opponent_rating'] : null,
            'color' => $meta['color'] ?? null,
            'result' => $meta['result'] ?? null,
            'time_control' => $meta['time_control'] ?? null,
            'opening' => $meta['opening'] ?? null,
            'tournament' => $meta['tournament'] ?? null,
            'round' => $meta['round'] ?? null,
            'pgn' => $pgn,
            'moves_uci' => implode(' ', $uci),
            'status' => 'final',
        ]);

        return true;
    }

    /**
     * Coups d'une partie au format UCI.
     *
     * @return list<string>
     */
    public function movesUci(int $gameId): array
    {
        $game = Game::query()->find($gameId);
        if ($game === null) {
            return [];
        }
        $raw = trim((string) $game->moves_uci);

        return $raw === '' ? [] : explode(' ', $raw);
    }
}
