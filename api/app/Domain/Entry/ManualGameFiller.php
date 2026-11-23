<?php

declare(strict_types=1);

namespace App\Domain\Entry;

use App\Domain\Chess\Pgn;
use App\Models\Game;
use App\Models\User;

/**
 * Reporte la fiche et les coups d'une saisie manuelle sur une partie.
 * moves_uci ne garde que le préfixe vérifié ; raw_moves conserve la saisie
 * exacte dès qu'un coup n'a pas pu être rejoué (needs_review).
 */
final class ManualGameFiller
{
    private const DEFAULT_PLAYER_NAME = 'Joueur Korrichess';

    /** @param array<string,mixed> $input validé par ManualGameRequest */
    public function fill(Game $game, int $userId, array $input, SheetReplay $replay): void
    {
        $game->played_at = (string) $input['played_at'];
        $game->opponent = (string) $input['opponent'];
        $game->opponent_rating = isset($input['opponent_rating']) ? (int) $input['opponent_rating'] : null;
        $game->color = (string) $input['color'];
        $game->result = (string) $input['result'];
        $game->time_control = $input['time_control'] ?? null;
        $game->opening = null; // détection d'ouverture : V3 (R1)
        $game->tournament = $input['tournament'] ?? null;
        $game->round = $input['round'] ?? null;
        $game->pgn = $this->buildPgn($userId, $input, $replay->uci);
        $game->moves_uci = $replay->uci === [] ? null : implode(' ', $replay->uci);
        $game->needs_review = $replay->hasInvalidMove();
        $game->raw_moves = $replay->hasInvalidMove()
            ? json_encode(MoveSheetReplayer::clean($input['moves']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : null;
    }

    /**
     * PGN de la partie : en-têtes repris de la fiche (S7), texte des coups
     * limité au préfixe vérifié — le reste vit dans raw_moves.
     *
     * @param array<string,mixed> $input
     * @param list<string> $uci
     */
    private function buildPgn(int $userId, array $input, array $uci): string
    {
        $player = $this->playerName($userId);
        $opponent = (string) $input['opponent'];
        $white = $input['color'] === 'white';

        $headers = [
            'Event' => $this->filled($input['tournament'] ?? null) ?? 'Partie saisie manuellement',
            'Site' => 'Korrichess',
            'Date' => str_replace('-', '.', substr((string) $input['played_at'], 0, 10)),
            'Round' => $this->filled($input['round'] ?? null) ?? '?',
            'White' => $white ? $player : $opponent,
            'Black' => $white ? $opponent : $player,
            'Result' => (string) $input['result'],
        ];
        if (($timeControl = $this->filled($input['time_control'] ?? null)) !== null) {
            $headers['TimeControl'] = $timeControl;
        }

        return Pgn::build($headers, $uci, (string) $input['result']);
    }

    private function playerName(int $userId): string
    {
        $user = User::query()->find($userId);
        $name = $user === null ? '' : (string) $user->profile()->firstOrCreate([])->display_name;

        return trim($name) !== '' ? $name : self::DEFAULT_PLAYER_NAME;
    }

    /** La valeur si elle contient autre chose que des blancs, sinon null. */
    private function filled(mixed $value): ?string
    {
        return ($value !== null && trim((string) $value) !== '') ? (string) $value : null;
    }
}
