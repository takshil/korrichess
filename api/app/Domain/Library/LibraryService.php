<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Library;

use function mb_strlen;

use Korrichess\Api\Core\Database;
use Korrichess\Api\Domain\Chess\Board;
use Korrichess\Api\Domain\Games\GameRepository;

/**
 * Bibliotheque de parties (issues #4, B1-B6) :
 * liste avec source, filtres, recherche texte, notes/etiquettes/favoris,
 * export PGN et recherche par position.
 */
final class LibraryService
{
    public function __construct(private readonly GameRepository $games = new GameRepository())
    {
    }

    /** Colonnes filtrees et trieables (liste blanche). */
    private const SORTABLE = ['played_at', 'created_at', 'opponent', 'opening'];

    private const FILTERABLE = ['source', 'opponent', 'opening', 'result', 'color', 'tournament', 'time_control'];

    /**
     * B1 + B2 : liste des parties avec indication de la source et filtres.
     * @param array<string,mixed> $filters
     * @return array{status:int,body:array}
     */
    public function list(int $userId, array $filters = [], int $limit = 50, int $offset = 0, string $sort = 'played_at', string $order = 'desc'): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = ['user_id = :uid'];
        $params = ['uid' => $userId];

        foreach (self::FILTERABLE as $field) {
            if (!isset($filters[$field]) || $filters[$field] === '' || $filters[$field] === null) {
                continue;
            }
            $where[] = "$field = :f_$field";
            $params["f_$field"] = (string) $filters[$field];
        }
        if (!empty($filters['period_start'])) {
            $where[] = 'played_at >= :period_start';
            $params['period_start'] = (string) $filters['period_start'];
        }
        if (!empty($filters['period_end'])) {
            $where[] = 'played_at <= :period_end';
            $params['period_end'] = (string) $filters['period_end'];
        }
        // B3 : recherche texte sur adversaire, tournoi, ouverture
        if (!empty($filters['q'])) {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
            $where[] = '(opponent LIKE :q ESCAPE "\\" OR tournament LIKE :q ESCAPE "\\" OR opening LIKE :q ESCAPE "\\" OR tags LIKE :q ESCAPE "\\")';
            $params['q'] = $needle;
        }
        if (!empty($filters['favorite'])) {
            $where[] = 'favorite = 1';
        }
        if (!empty($filters['tag'])) {
            $where[] = '"," || tags || "," LIKE :tag';
            $params['tag'] = '%,' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['tag']) . ',%';
        }

        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'played_at';
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        $sqlWhere = implode(' AND ', $where);
        $total = (int) (Database::one("SELECT COUNT(*) AS n FROM games WHERE $sqlWhere", $params)['n'] ?? 0);
        $rows = Database::all(
            "SELECT id, source, external_id, played_at, opponent, opponent_rating, color, result,
                    time_control, opening, tournament, round, favorite, personal_note, tags
             FROM games WHERE $sqlWhere
             ORDER BY $sort $order, id ASC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params,
        );

        return ['status' => 200, 'body' => [
            'games' => array_map(static fn (array $r): array => self::present($r), $rows),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]];
    }

    /** B1 : detail d'une partie, PGN inclus. @return array{status:int,body:array} */
    public function detail(int $userId, int $gameId): array
    {
        $row = self::findOwned($userId, $gameId);
        if ($row === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }
        $game = self::present($row);
        $game['pgn'] = $row['pgn'];
        $game['moves_uci'] = $this->games->movesUci($gameId);
        return ['status' => 200, 'body' => $game];
    }

    /** B4 : note personnelle, etiquettes, favori. @param array<string,mixed> $input @return array{status:int,body:array} */
    public function annotate(int $userId, int $gameId, array $input): array
    {
        $row = self::findOwned($userId, $gameId);
        if ($row === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }
        $updates = [];
        $params = ['id' => $gameId];
        if (array_key_exists('personal_note', $input)) {
            $note = (string) $input['personal_note'];
            if (mb_strlen($note) > 2000) {
                return ['status' => 422, 'body' => ['error' => 'note_trop_longue']];
            }
            $updates[] = 'personal_note = :note';
            $params['note'] = $note;
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
            $updates[] = 'tags = :tags';
            $params['tags'] = implode(',', array_slice(array_unique($clean), 0, 20));
        }
        if (array_key_exists('favorite', $input)) {
            $updates[] = 'favorite = :fav';
            $params['fav'] = $input['favorite'] ? 1 : 0;
        }
        if ($updates === []) {
            return ['status' => 422, 'body' => ['error' => 'rien_a_mettre_a_jour']];
        }
        $updates[] = 'updated_at = :now';
        $params['now'] = gmdate('c');
        Database::run('UPDATE games SET ' . implode(', ', $updates) . ' WHERE id = :id', $params);
        return $this->detail($userId, $gameId);
    }

    /** B5 : export PGN d'une selection. @param array<int,int> $gameIds @return array{status:int,body:array} */
    public function exportPgn(int $userId, array $gameIds): array
    {
        $ids = [];
        foreach ($gameIds as $id) {
            $ids[] = (int) $id;
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return ['status' => 422, 'body' => ['error' => 'selection_vide']];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all(
            "SELECT pgn, personal_note, tags FROM games WHERE user_id = ? AND id IN ($placeholders) ORDER BY played_at DESC",
            array_merge([$userId], $ids),
        );
        if ($rows === []) {
            return ['status' => 404, 'body' => ['error' => 'aucune_partie']];
        }
        $pgns = [];
        foreach ($rows as $row) {
            $pgn = trim((string) $row['pgn']);
            $extras = '';
            if ((string) $row['personal_note'] !== '') {
                $extras .= '[KorriNote "' . str_replace('"', '', (string) $row['personal_note']) . "\"]\n";
            }
            if ((string) $row['tags'] !== '') {
                $extras .= '[KorriTags "' . str_replace('"', '', (string) $row['tags']) . "\"]\n";
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
     * B6 : recherche par position. Indexe les positions d'une partie a la demande,
     * puis trouve toutes les parties ayant atteint la position donnee.
     * @return array{status:int,body:array}
     */
    public function searchByPosition(int $userId, string $fen, int $limit = 50): array
    {
        if (!self::isValidFen($fen)) {
            return ['status' => 422, 'body' => ['error' => 'fen_invalide']];
        }
        $fen = trim($fen);
        $limit = max(1, min(200, $limit));
        $gameIds = array_column(Database::all(
            'SELECT DISTINCT id FROM games WHERE user_id = :u',
            ['u' => $userId],
        ), 'id');
        foreach ($gameIds as $gameId) {
            $this->indexPositions((int) $gameId);
        }
        $rows = Database::all(
            'SELECT g.id, g.source, g.played_at, g.opponent, g.result, g.opening, gp.ply
             FROM game_positions gp
             JOIN games g ON g.id = gp.game_id
             WHERE gp.fen = :fen AND g.user_id = :u
             ORDER BY g.played_at DESC
             LIMIT ' . (int) $limit,
            ['fen' => $fen, 'u' => $userId],
        );
        return ['status' => 200, 'body' => [
            'games' => array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'source' => $r['source'],
                'played_at' => $r['played_at'],
                'opponent' => $r['opponent'],
                'result' => $r['result'],
                'opening' => $r['opening'],
                'ply' => (int) $r['ply'],
            ], $rows),
            'total' => count($rows),
        ]];
    }

    /** Indexe les positions (FEN) atteintes par une partie, une seule fois. */
    private function indexPositions(int $gameId): void
    {
        $already = Database::one('SELECT game_id FROM game_positions WHERE game_id = :g LIMIT 1', ['g' => $gameId]);
        if ($already !== null) {
            return;
        }
        $uciMoves = $this->games->movesUci($gameId);
        $board = Board::start();
        $rows = [];
        $rows[] = [$gameId, 0, $board->fen()];
        $ply = 0;
        foreach ($uciMoves as $uci) {
            if (!$board->applyUci($uci)) {
                break;
            }
            $ply++;
            $rows[] = [$gameId, $ply, $board->fen()];
        }
        Database::pdo()->beginTransaction();
        try {
            $stmt = Database::pdo()->prepare('INSERT OR IGNORE INTO game_positions (game_id, ply, fen) VALUES (?, ?, ?)');
            foreach ($rows as [$g, $p, $f]) {
                $stmt->execute([$g, $p, $f]);
            }
            Database::pdo()->commit();
        } catch (\Throwable $e) {
            Database::pdo()->rollBack();
            throw $e;
        }
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

    /** @return array<string,mixed>|null */
    private static function findOwned(int $userId, int $gameId): ?array
    {
        return Database::one('SELECT * FROM games WHERE id = :id AND user_id = :u', ['id' => $gameId, 'u' => $userId]);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'source' => $row['source'],
            'played_at' => $row['played_at'],
            'opponent' => $row['opponent'],
            'opponent_rating' => $row['opponent_rating'] !== null ? (int) $row['opponent_rating'] : null,
            'color' => $row['color'],
            'result' => $row['result'],
            'time_control' => $row['time_control'],
            'opening' => $row['opening'],
            'tournament' => $row['tournament'],
            'round' => $row['round'],
            'favorite' => ((int) $row['favorite']) === 1,
            'personal_note' => $row['personal_note'],
            'tags' => $row['tags'] === '' ? [] : explode(',', (string) $row['tags']),
        ];
    }
}
