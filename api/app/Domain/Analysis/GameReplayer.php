<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Chess\Board;
use App\Domain\Chess\GameReplay;
use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;

/** A1 + A8 : rejeu d'une partie et exploration de coups alternatifs (sans rien modifier). */
final class GameReplayer
{
    public function __construct(
        private readonly EngineClient $engine,
        private readonly GameRepository $games,
    ) {
    }

    public function replay(int $userId, int $gameId): ReplayedGame
    {
        $game = $this->games->findOwnedOrFail($userId, $gameId);
        $uciMoves = $game->uciMoves();
        $replay = GameReplay::fromUci($uciMoves);

        return new ReplayedGame($game, $uciMoves, $replay->sans, $replay->positions);
    }

    /**
     * Valide un coup depuis la position atteinte après $ply demi-coups et
     * retourne la position résultante.
     *
     * @throws DomainError position inatteignable, partie terminée ou coup illégal
     */
    public function explore(int $userId, int $gameId, int $ply, string $moveUci): ExploredMove
    {
        $game = $this->games->findOwnedOrFail($userId, $gameId);
        $board = $this->boardAfter($game->uciMoves(), $ply);

        if ($board->isCheckmate($board->turn()) || $board->isStalemate()) {
            throw DomainError::invalid('partie_terminee');
        }

        $before = clone $board;
        if (!$board->applyUci($moveUci)) {
            throw DomainError::invalid('coup_illégal', ['fen' => $before->fen()]);
        }

        return new ExploredMove(
            $ply,
            $moveUci,
            $before->uciToSan($moveUci),
            $board->fen(),
            $this->engine->evaluate($board->fen())['eval_cp'],
        );
    }

    /** @param list<string> $uciMoves */
    private function boardAfter(array $uciMoves, int $ply): Board
    {
        $board = Board::start();
        for ($i = 0; $i < $ply && $i < count($uciMoves); $i++) {
            if (!$board->applyUci($uciMoves[$i])) {
                throw DomainError::invalid('position_inatteignable');
            }
        }

        return $board;
    }
}
