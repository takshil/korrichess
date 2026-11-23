<?php

declare(strict_types=1);

namespace App\Domain\Entry;

use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;
use App\Models\Game;

/**
 * Saisie manuelle d'une partie de tournoi (S5 + S7, issue #25).
 *
 * Le serveur rejoue les coups avec le moteur Board : le client web
 * n'embarque pas de moteur (ADR-005). Deux modes :
 *  - validation active : tout coup impossible à rejouer est refusé ;
 *  - validation désactivée : une feuille de partie papier peut contenir
 *    des erreurs (coup mal noté pendant la partie, transcription), la
 *    partie est alors enregistrée telle que saisie, marquée « à
 *    vérifier » (needs_review), et le rejeu fonctionne jusqu'au premier
 *    coup douteux.
 */
final class ManualEntryService
{
    public function __construct(
        private readonly MoveSheetReplayer $replayer,
        private readonly ManualGameFiller $filler,
        private readonly GameRepository $games,
    ) {
    }

    /**
     * Rejoue une liste de coups sans rien enregistrer : FEN atteinte, SAN,
     * coups légaux suivants et, le cas échéant, le premier coup impossible.
     *
     * @param array<int|string,mixed> $moves
     * @return array<string,mixed>
     */
    public function preview(array $moves): array
    {
        $replay = $this->replayer->replay($moves);
        $board = $replay->board;

        return [
            'fen' => $board->fen(),
            'moves_san' => $replay->san,
            'moves_uci' => $replay->uci,
            'invalid_ply' => $replay->invalidPly,
            'invalid_move' => $replay->invalidMove,
            'turn' => $board->turn() === 'w' ? 'white' : 'black',
            'in_check' => $board->isCheck($board->turn()),
            'is_checkmate' => $board->isCheckmate($board->turn()),
            'is_stalemate' => $board->isStalemate(),
            'legal_uci' => $board->legalMovesUci(),
        ];
    }

    /**
     * Enregistre une partie saisie à la main (S5 + S7).
     *
     * @param array<string,mixed> $input validé par ManualGameRequest
     *
     * @throws DomainError coup impossible en mode vérifié
     */
    public function store(int $userId, array $input): Game
    {
        $replay = $this->replayStrictly($input);

        $game = new Game();
        $game->user_id = $userId;
        $game->source = 'manual';
        $game->external_id = 'manual-' . bin2hex(random_bytes(12));
        $this->filler->fill($game, $userId, $input, $replay);
        $game->save();

        return $game;
    }

    /**
     * Corrige une saisie manuelle : fiche et coups sont remplacés en
     * entier (ré-entrer une saisie, c'est la corriger).
     *
     * @param array<string,mixed> $input validé par ManualGameRequest
     *
     * @throws DomainError partie introuvable, non manuelle, ou coup impossible en mode vérifié
     */
    public function update(int $userId, int $gameId, array $input): Game
    {
        $game = $this->games->findOwnedOrFail($userId, $gameId);
        if ($game->source !== 'manual') {
            throw DomainError::invalid('pas_une_saisie_manuelle');
        }

        $replay = $this->replayStrictly($input);
        $this->filler->fill($game, $userId, $input, $replay);
        $game->save();

        return $game;
    }

    /**
     * Rejoue la saisie ; en mode vérifié, refuse dès qu'un coup est
     * impossible à rejouer (le client ne devrait pas en produire).
     *
     * @param array<string,mixed> $input
     *
     * @throws DomainError
     */
    private function replayStrictly(array $input): SheetReplay
    {
        $replay = $this->replayer->replay($input['moves']);

        if ((bool) ($input['validate_moves'] ?? true) && $replay->hasInvalidMove()) {
            throw DomainError::invalid('coup_invalide', [
                'ply' => $replay->invalidPly,
                'move' => $replay->invalidMove,
                'fen' => $replay->board->fen(),
            ]);
        }

        return $replay;
    }
}
