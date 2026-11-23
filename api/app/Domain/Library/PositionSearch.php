<?php

declare(strict_types=1);

namespace App\Domain\Library;

use App\Domain\Chess\Board;
use App\Domain\Chess\GameReplay;
use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;
use Illuminate\Support\Facades\DB;

/**
 * B6 : recherche par position. Les positions (FEN) des parties du joueur
 * sont indexées à la demande, une seule fois par partie, puis on retrouve
 * toutes les parties ayant atteint la position donnée.
 */
final class PositionSearch
{
    public function __construct(private readonly GameRepository $games)
    {
    }

    /**
     * @return array{games:list<array<string,mixed>>,total:int}
     *
     * @throws DomainError FEN invalide
     */
    public function search(int $userId, string $fen, int $limit = 50): array
    {
        $fen = trim($fen);
        if (!$this->isValidFen($fen)) {
            throw DomainError::invalid('fen_invalide');
        }

        $this->games->idsOwnedBy($userId)->each(fn (int $gameId) => $this->indexPositions($gameId));

        $rows = DB::table('game_positions as gp')
            ->join('games as g', 'g.id', '=', 'gp.game_id')
            ->where('gp.fen', $fen)
            ->where('g.user_id', $userId)
            ->orderByDesc('g.played_at')
            ->limit(max(1, min(200, $limit)))
            ->get(['g.id', 'g.source', 'g.played_at', 'g.opponent', 'g.result', 'g.opening', 'gp.ply']);

        return [
            'games' => $rows->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'source' => $r->source,
                'played_at' => $r->played_at,
                'opponent' => $r->opponent,
                'result' => $r->result,
                'opening' => $r->opening,
                'ply' => (int) $r->ply,
            ])->all(),
            'total' => $rows->count(),
        ];
    }

    /** Indexe les FEN atteints par une partie (position de départ comprise), une seule fois. */
    private function indexPositions(int $gameId): void
    {
        if (DB::table('game_positions')->where('game_id', $gameId)->exists()) {
            return;
        }

        $positions = GameReplay::fromUci($this->games->movesUci($gameId))->positions;
        $rows = [];
        foreach ($positions as $ply => $fen) {
            $rows[] = ['game_id' => $gameId, 'ply' => $ply, 'fen' => $fen];
        }

        DB::table('game_positions')->insertOrIgnore($rows);
    }

    private function isValidFen(string $fen): bool
    {
        try {
            Board::fromFen($fen);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
