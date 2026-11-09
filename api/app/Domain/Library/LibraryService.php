<?php

declare(strict_types=1);

namespace App\Domain\Library;

use App\Domain\Chess\Board;
use App\Domain\Games\GameRepository;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * Bibliothèque de parties (issue #4, B1–B6) : liste avec indication de la
 * source, filtres, recherche texte, notes/étiquettes/favoris, export PGN
 * et recherche par position.
 */
final class LibraryService
{
    /** Colonnes filtrables et triables (liste blanche). */
    private const SORTABLE = ['played_at', 'created_at', 'opponent', 'opening'];

    private const FILTERABLE = ['source', 'opponent', 'opening', 'result', 'color', 'tournament', 'time_control'];

    public function __construct(private readonly GameRepository $games = new GameRepository())
    {
    }

    /**
     * B1 + B2 : liste des parties avec indication de la source, filtres,
     * tri et pagination.
     *
     * @param array<string,mixed> $filters
     * @return array{status:int,body:array<string,mixed>}
     */
    public function list(int $userId, array $filters = [], int $limit = 50, int $offset = 0, string $sort = 'played_at', string $order = 'desc'): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'played_at';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $query = Game::query()->where('user_id', $userId);

        foreach (self::FILTERABLE as $field) {
            if (!isset($filters[$field]) || $filters[$field] === '' || $filters[$field] === null) {
                continue;
            }
            $query->where($field, (string) $filters[$field]);
        }
        if (!empty($filters['period_start'])) {
            $query->where('played_at', '>=', (string) $filters['period_start']);
        }
        if (!empty($filters['period_end'])) {
            $query->where('played_at', '<=', (string) $filters['period_end']);
        }
        // B3 : recherche texte sur adversaire, tournoi, ouverture, étiquettes.
        if (!empty($filters['q'])) {
            $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']) . '%';
            $query->where(static function ($sub) use ($needle): void {
                foreach (['opponent', 'tournament', 'opening', 'tags'] as $col) {
                    $sub->orWhere($col, 'like', $needle);
                }
            });
        }
        if (!empty($filters['favorite'])) {
            $query->where('favorite', true);
        }
        // Étiquette exacte : la virgule de borne évite les faux positifs
        // (« italienne » ne doit pas matcher « italienne_ouvert »).
        if (!empty($filters['tag'])) {
            $tag = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['tag']);
            $query->where(static function ($sub) use ($tag): void {
                $sub->where('tags', $tag)
                    ->orWhere('tags', 'like', $tag . ',%')
                    ->orWhere('tags', 'like', '%,' . $tag . ',%')
                    ->orWhere('tags', 'like', '%,' . $tag);
            });
        }

        $total = (clone $query)->toBase()->count();
        $rows = (clone $query)
            ->orderBy($sort, $order)
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return ['status' => 200, 'body' => [
            'games' => $rows->map(static fn (Game $g): array => self::present($g))->all(),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]];
    }

    /**
     * B1 : détail d'une partie, PGN et coups UCI inclus.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function detail(int $userId, int $gameId): array
    {
        $game = self::findOwned($userId, $gameId);
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }

        $body = self::present($game);
        $body['pgn'] = $game->pgn;
        $body['moves_uci'] = $this->games->movesUci($gameId);

        return ['status' => 200, 'body' => $body];
    }

    /**
     * B4 : note personnelle, étiquettes, favori.
     *
     * @param array<string,mixed> $input
     * @return array{status:int,body:array<string,mixed>}
     */
    public function annotate(int $userId, int $gameId, array $input): array
    {
        $game = self::findOwned($userId, $gameId);
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }

        $updates = [];
        if (array_key_exists('personal_note', $input)) {
            $note = (string) $input['personal_note'];
            if (mb_strlen($note) > 2000) {
                return ['status' => 422, 'body' => ['error' => 'note_trop_longue']];
            }
            $game->personal_note = $note;
            $updates[] = 'personal_note';
        }
        if (array_key_exists('tags', $input)) {
            $tags = $input['tags'];
            if (!is_array($tags)) {
                return ['status' => 422, 'body' => ['error' => 'etiquettes_invalides']];
            }
            $clean = [];
            foreach ($tags as $tag) {
                $tag = trim((string) $tag);
                if ($tag === '' || mb_strlen($tag) > 32) {
                    return ['status' => 422, 'body' => ['error' => 'etiquette_invalide']];
                }
                $clean[] = $tag;
            }
            $game->tags = implode(',', array_slice(array_unique($clean), 0, 20));
            $updates[] = 'tags';
        }
        if (array_key_exists('favorite', $input)) {
            $game->favorite = (bool) $input['favorite'];
            $updates[] = 'favorite';
        }
        if ($updates === []) {
            return ['status' => 422, 'body' => ['error' => 'rien_a_mettre_a_jour']];
        }

        $game->save();

        return $this->detail($userId, $gameId);
    }

    /**
     * B5 : export PGN d'une sélection (note et étiquettes en en-têtes
     * KorriNote / KorriTags).
     *
     * @param array<int,int> $gameIds
     * @return array{status:int,body:array<string,mixed>}
     */
    public function exportPgn(int $userId, array $gameIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $gameIds)));
        if ($ids === []) {
            return ['status' => 422, 'body' => ['error' => 'selection_vide']];
        }

        $rows = Game::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->orderByDesc('played_at')
            ->get(['id', 'pgn', 'personal_note', 'tags']);
        if ($rows->isEmpty()) {
            return ['status' => 404, 'body' => ['error' => 'aucune_partie']];
        }

        $pgns = [];
        foreach ($rows as $row) {
            $pgn = trim((string) $row->pgn);
            $extras = '';
            if ((string) $row->personal_note !== '') {
                $extras .= '[KorriNote "' . str_replace('"', '', (string) $row->personal_note) . "\"]\n";
            }
            if ((string) $row->tags !== '') {
                $extras .= '[KorriTags "' . str_replace('"', '', (string) $row->tags) . "\"]\n";
            }
            if ($extras !== '' && str_contains($pgn, "\n\n")) {
                $head = substr($pgn, 0, (int) strpos($pgn, "\n\n"));
                $body = substr($pgn, (int) strpos($pgn, "\n\n") + 2);
                $pgn = $head . "\n" . $extras . "\n" . $body;
            }
            $pgns[] = $pgn;
        }

        return ['status' => 200, 'body' => ['count' => count($pgns), 'pgn' => implode("\n\n", $pgns) . "\n"]];
    }

    /**
     * B6 : recherche par position. Indexe les positions (FEN) des parties
     * de l'utilisateur à la demande, une seule fois par partie, puis trouve
     * toutes les parties ayant atteint la position donnée.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function searchByPosition(int $userId, string $fen, int $limit = 50): array
    {
        if (!self::isValidFen($fen)) {
            return ['status' => 422, 'body' => ['error' => 'fen_invalide']];
        }
        $fen = trim($fen);
        $limit = max(1, min(200, $limit));

        Game::query()
            ->where('user_id', $userId)
            ->pluck('id')
            ->each(fn (int $gameId) => $this->indexPositions($gameId));

        $rows = DB::table('game_positions as gp')
            ->join('games as g', 'g.id', '=', 'gp.game_id')
            ->where('gp.fen', $fen)
            ->where('g.user_id', $userId)
            ->orderByDesc('g.played_at')
            ->limit($limit)
            ->get(['g.id', 'g.source', 'g.played_at', 'g.opponent', 'g.result', 'g.opening', 'gp.ply']);

        return ['status' => 200, 'body' => [
            'games' => $rows->map(static fn ($r): array => [
                'id' => (int) $r->id,
                'source' => $r->source,
                'played_at' => $r->played_at,
                'opponent' => $r->opponent,
                'result' => $r->result,
                'opening' => $r->opening,
                'ply' => (int) $r->ply,
            ])->all(),
            'total' => $rows->count(),
        ]];
    }

    /** Indexe les FEN atteints par une partie (position de départ comprise), une seule fois. */
    private function indexPositions(int $gameId): void
    {
        $already = DB::table('game_positions')->where('game_id', $gameId)->exists();
        if ($already) {
            return;
        }

        $uciMoves = $this->games->movesUci($gameId);
        $board = Board::start();
        $rows = [['game_id' => $gameId, 'ply' => 0, 'fen' => $board->fen()]];
        $ply = 0;
        foreach ($uciMoves as $uci) {
            if (!$board->applyUci($uci)) {
                break;
            }
            $ply++;
            $rows[] = ['game_id' => $gameId, 'ply' => $ply, 'fen' => $board->fen()];
        }

        DB::transaction(static function () use ($rows): void {
            DB::table('game_positions')->insertOrIgnore($rows);
        });
    }

    private static function isValidFen(string $fen): bool
    {
        try {
            Board::fromFen($fen);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private static function findOwned(int $userId, int $gameId): ?Game
    {
        return Game::query()
            ->where('id', $gameId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @return array<string,mixed>
     */
    private static function present(Game $game): array
    {
        return [
            'id' => (int) $game->id,
            'source' => $game->source,
            'played_at' => $game->played_at,
            'opponent' => $game->opponent,
            'opponent_rating' => $game->opponent_rating !== null ? (int) $game->opponent_rating : null,
            'color' => $game->color,
            'result' => $game->result,
            'time_control' => $game->time_control,
            'opening' => $game->opening,
            'tournament' => $game->tournament,
            'round' => $game->round,
            'favorite' => (bool) $game->favorite,
            'personal_note' => $game->personal_note,
            'tags' => ($game->tags === '' || $game->tags === null) ? [] : explode(',', (string) $game->tags),
        ];
    }
}
