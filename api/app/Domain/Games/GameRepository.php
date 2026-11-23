<?php

declare(strict_types=1);

namespace App\Domain\Games;

use App\Domain\Chess\Pgn;
use App\Domain\Library\GamePage;
use App\Domain\Shared\DomainError;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Accès aux parties : le seul endroit qui sait comment les retrouver,
 * les filtrer et les insérer.
 */
final class GameRepository
{
    /** Colonnes triables (liste blanche). */
    private const SORTABLE = ['played_at', 'created_at', 'opponent', 'opening'];

    /** Filtres d'égalité stricte sur une colonne. */
    private const EXACT_FILTERS = ['source', 'opponent', 'opening', 'result', 'color', 'tournament', 'time_control'];

    /** Colonnes parcourues par la recherche texte (B3). */
    private const SEARCHABLE = ['opponent', 'tournament', 'opening', 'tags'];

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
            ->ownedBy($userId)
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
     * @throws DomainError la partie n'existe pas ou appartient à un autre joueur
     */
    public function findOwnedOrFail(int $userId, int $gameId): Game
    {
        return Game::query()->ownedBy($userId)->find($gameId)
            ?? throw DomainError::notFound('partie_introuvable');
    }

    /**
     * Parties d'un joueur parmi une sélection, les plus récentes d'abord.
     *
     * @param list<int> $gameIds
     * @return Collection<int,Game>
     */
    public function findOwnedMany(int $userId, array $gameIds): Collection
    {
        return Game::query()
            ->ownedBy($userId)
            ->whereIn('id', $gameIds)
            ->orderByDesc('played_at')
            ->get(['id', 'pgn', 'personal_note', 'tags']);
    }

    /** @return Collection<int,int> */
    public function idsOwnedBy(int $userId): Collection
    {
        return Game::query()->ownedBy($userId)->pluck('id');
    }

    /**
     * Coups d'une partie au format UCI (liste vide si la partie n'existe pas).
     *
     * @return list<string>
     */
    public function movesUci(int $gameId): array
    {
        return Game::query()->find($gameId)?->uciMoves() ?? [];
    }

    /**
     * B1 + B2 : page de la bibliothèque, filtrée, triée et paginée.
     *
     * @param array<string,mixed> $filters
     */
    public function page(int $userId, array $filters, int $limit, int $offset, string $sort, string $order): GamePage
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'played_at';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $query = $this->filtered(Game::query()->ownedBy($userId), $filters);

        $total = (clone $query)->toBase()->count();
        $games = $query
            ->orderBy($sort, $order)
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return new GamePage($games, $total, $limit, $offset);
    }

    /**
     * Totaux victoires / nulles / défaites du joueur, agrégés en base.
     *
     * @return array{total:int,victoires:int,nulles:int,defaites:int}
     */
    public function resultCounts(int $userId): array
    {
        $row = DB::table('games')
            ->where('user_id', $userId)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN (result = '1-0' AND color = 'white') OR (result = '0-1' AND color = 'black') THEN 1 ELSE 0 END) AS victoires,
                SUM(CASE WHEN result = '1/2-1/2' THEN 1 ELSE 0 END) AS nulles,
                SUM(CASE WHEN (result = '0-1' AND color = 'white') OR (result = '1-0' AND color = 'black') THEN 1 ELSE 0 END) AS defaites")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'victoires' => (int) ($row->victoires ?? 0),
            'nulles' => (int) ($row->nulles ?? 0),
            'defaites' => (int) ($row->defaites ?? 0),
        ];
    }

    /**
     * @param Builder<Game> $query
     * @param array<string,mixed> $filters
     * @return Builder<Game>
     */
    private function filtered(Builder $query, array $filters): Builder
    {
        foreach (self::EXACT_FILTERS as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, (string) $filters[$field]);
            }
        }
        if (!empty($filters['period_start'])) {
            $query->where('played_at', '>=', (string) $filters['period_start']);
        }
        if (!empty($filters['period_end'])) {
            $query->where('played_at', '<=', (string) $filters['period_end']);
        }
        if (!empty($filters['q'])) {
            $this->matchingText($query, (string) $filters['q']);
        }
        if (!empty($filters['favorite'])) {
            $query->where('favorite', true);
        }
        if (!empty($filters['tag'])) {
            $this->withTag($query, (string) $filters['tag']);
        }

        return $query;
    }

    /** B3 : recherche texte sur adversaire, tournoi, ouverture, étiquettes. */
    private function matchingText(Builder $query, string $text): void
    {
        $needle = '%' . $this->escapeLike($text) . '%';
        $query->where(static function (Builder $sub) use ($needle): void {
            foreach (self::SEARCHABLE as $column) {
                $sub->orWhere($column, 'like', $needle);
            }
        });
    }

    /**
     * Étiquette exacte : la virgule de borne évite les faux positifs
     * (« italienne » ne doit pas matcher « italienne_ouvert »).
     */
    private function withTag(Builder $query, string $tag): void
    {
        $tag = $this->escapeLike($tag);
        $query->where(static function (Builder $sub) use ($tag): void {
            $sub->where('tags', $tag)
                ->orWhere('tags', 'like', $tag . ',%')
                ->orWhere('tags', 'like', '%,' . $tag . ',%')
                ->orWhere('tags', 'like', '%,' . $tag);
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
