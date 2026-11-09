<?php

declare(strict_types=1);

namespace App\Domain\Entry;

use App\Domain\Chess\Board;
use App\Domain\Chess\Pgn;
use App\Models\Game;
use App\Models\User;

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
    private const UCI = '/^[a-h][1-8][a-h][1-8][qrbn]?$/';

    /**
     * Rejoue une liste de coups sans rien enregistrer : FEN atteinte, SAN,
     * coups légaux suivants et, le cas échéant, le premier coup impossible.
     *
     * @param list<string> $moves
     * @return array{status:int,body:array<string,mixed>}
     */
    public function preview(array $moves): array
    {
        $r = $this->rejouer($this->nettoyer($moves));
        $board = $r['board'];

        return ['status' => 200, 'body' => [
            'fen' => $board->fen(),
            'moves_san' => $r['san'],
            'moves_uci' => $r['uci'],
            'invalid_ply' => $r['invalidPly'],
            'invalid_move' => $r['invalidMove'],
            'turn' => $board->turn() === 'w' ? 'white' : 'black',
            'in_check' => $board->isCheck($board->turn()),
            'is_checkmate' => $board->isCheckmate($board->turn()),
            'is_stalemate' => $board->isStalemate(),
            'legal_uci' => $board->legalMovesUci(),
        ]];
    }

    /**
     * Enregistre une partie saisie à la main (S5 + S7).
     *
     * @param array<string,mixed> $input validé par ManualGameRequest
     * @return array{status:int,body:array<string,mixed>}
     */
    public function store(int $userId, array $input): array
    {
        $rejeu = $this->rejouer($this->nettoyer($input['moves']));
        $refus = $this->refusSiStrict($userId, $input, $rejeu);
        if ($refus !== null) {
            return $refus;
        }

        $game = new Game();
        $game->user_id = $userId;
        $game->source = 'manual';
        $game->external_id = 'manual-' . bin2hex(random_bytes(12));
        $this->remplir($game, $userId, $input, $rejeu);
        $game->save();

        return ['status' => 201, 'body' => $this->presenter($game)];
    }

    /**
     * Corrige une saisie manuelle : fiche et coups sont remplacés en
     * entier (ré-entrer une saisie, c'est la corriger).
     *
     * @param array<string,mixed> $input validé par ManualGameRequest
     * @return array{status:int,body:array<string,mixed>}
     */
    public function update(int $userId, int $gameId, array $input): array
    {
        $game = Game::query()->where('id', $gameId)->where('user_id', $userId)->first();
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }
        if ($game->source !== 'manual') {
            return ['status' => 422, 'body' => ['error' => 'pas_une_saisie_manuelle']];
        }

        $rejeu = $this->rejouer($this->nettoyer($input['moves']));
        $refus = $this->refusSiStrict($userId, $input, $rejeu);
        if ($refus !== null) {
            return $refus;
        }

        $this->remplir($game, $userId, $input, $rejeu);
        $game->save();

        return ['status' => 200, 'body' => $this->presenter($game)];
    }

    /**
     * En mode vérifié, refuse l'enregistrement dès qu'un coup est
     * impossible à rejouer (le client ne devrait pas en produire).
     *
     * @param array<string,mixed> $input
     * @param array{board:Board,uci:array<int,string>,san:array<int,string>,invalidPly:?int,invalidMove:?string} $rejeu
     * @return array{status:int,body:array<string,mixed>}|null
     */
    private function refusSiStrict(int $userId, array $input, array $rejeu): ?array
    {
        $strict = (bool) ($input['validate_moves'] ?? true);
        if ($strict && $rejeu['invalidPly'] !== null) {
            return ['status' => 422, 'body' => [
                'error' => 'coup_invalide',
                'ply' => $rejeu['invalidPly'],
                'move' => $rejeu['invalidMove'],
                'fen' => $rejeu['board']->fen(),
            ]];
        }

        return null;
    }

    /**
     * Reporte la fiche et les coups sur la partie. moves_uci ne garde que
     * le préfixe vérifié ; raw_moves conserve la saisie exacte dès qu'un
     * coup n'a pas pu être rejoué (needs_review).
     *
     * @param array<string,mixed> $input
     * @param array{board:Board,uci:array<int,string>,san:array<int,string>,invalidPly:?int,invalidMove:?string} $rejeu
     */
    private function remplir(Game $game, int $userId, array $input, array $rejeu): void
    {
        $douteux = $rejeu['invalidPly'] !== null;

        $game->played_at = (string) $input['played_at'];
        $game->opponent = (string) $input['opponent'];
        $game->opponent_rating = isset($input['opponent_rating']) ? (int) $input['opponent_rating'] : null;
        $game->color = (string) $input['color'];
        $game->result = (string) $input['result'];
        $game->time_control = $input['time_control'] ?? null;
        $game->opening = null; // détection d'ouverture : V3 (R1)
        $game->tournament = $input['tournament'] ?? null;
        $game->round = $input['round'] ?? null;
        $game->pgn = $this->construirePgn($userId, $input, $rejeu['uci']);
        $game->moves_uci = $rejeu['uci'] === [] ? null : implode(' ', $rejeu['uci']);
        $game->needs_review = $douteux;
        $game->raw_moves = $douteux
            ? json_encode($this->nettoyer($input['moves']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : null;
    }

    /**
     * PGN de la partie : en-têtes repris de la fiche (S7), texte des coups
     * limité au préfixe vérifié — le reste vit dans raw_moves.
     *
     * @param array<string,mixed> $input
     * @param array<int,string> $uci
     */
    private function construirePgn(int $userId, array $input, array $uci): string
    {
        $user = User::query()->find($userId);
        $nom = $user === null ? '' : (string) $user->profile()->firstOrCreate([])->display_name;
        $nom = trim($nom) !== '' ? $nom : 'Joueur Korrichess';
        $adversaire = (string) $input['opponent'];

        $entetes = [
            'Event' => trim((string) ($input['tournament'] ?? '')) !== '' ? (string) $input['tournament'] : 'Partie saisie manuellement',
            'Site' => 'Korrichess',
            'Date' => str_replace('-', '.', substr((string) $input['played_at'], 0, 10)),
            'Round' => trim((string) ($input['round'] ?? '')) !== '' ? (string) $input['round'] : '?',
            'White' => $input['color'] === 'white' ? $nom : $adversaire,
            'Black' => $input['color'] === 'white' ? $adversaire : $nom,
            'Result' => (string) $input['result'],
        ];
        if (isset($input['time_control']) && $input['time_control'] !== null && trim((string) $input['time_control']) !== '') {
            $entetes['TimeControl'] = (string) $input['time_control'];
        }

        return Pgn::build($entetes, $uci, (string) $input['result']);
    }

    /**
     * Rejoue les coups depuis la position initiale. Chaque coup est une
     * chaîne UCI (échiquier interactif) ou en notation algébrique (feuille
     * papier) ; le premier coup impossible arrête le rejeu.
     *
     * @param list<string> $moves
     * @return array{board:Board,uci:array<int,string>,san:array<int,string>,invalidPly:?int,invalidMove:?string}
     */
    private function rejouer(array $moves): array
    {
        $board = Board::start();
        $uci = [];
        $san = [];
        $invalidPly = null;
        $invalidMove = null;

        foreach ($moves as $ply => $move) {
            $move = trim((string) $move);
            $ok = false;
            if (preg_match(self::UCI, $move) === 1) {
                if (in_array($move, $board->legalMovesUci(), true)) {
                    $san[] = $board->uciToSan($move);
                    $board->applyUci($move);
                    $uci[] = $move;
                    $ok = true;
                }
            } else {
                $u = $board->sanToUci($move);
                if ($u !== null) {
                    $san[] = $board->uciToSan($u);
                    $board->applyUci($u);
                    $uci[] = $u;
                    $ok = true;
                }
            }
            if (!$ok) {
                $invalidPly = $ply;
                $invalidMove = $move;
                break;
            }
        }

        return ['board' => $board, 'uci' => $uci, 'san' => $san, 'invalidPly' => $invalidPly, 'invalidMove' => $invalidMove];
    }

    /**
     * @param array<int|string,mixed> $moves
     * @return list<string>
     */
    private function nettoyer(array $moves): array
    {
        $propres = [];
        foreach ($moves as $move) {
            $propres[] = trim((string) $move);
        }

        return array_values($propres);
    }

    /**
     * Corps de réponse d'une saisie : fiche, coups vérifiés, saisie brute.
     *
     * @return array<string,mixed>
     */
    private function presenter(Game $game): array
    {
        $uci = trim((string) $game->moves_uci);
        $bruts = $game->raw_moves !== null ? json_decode((string) $game->raw_moves, true) : null;

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
            'needs_review' => (bool) $game->needs_review,
            'pgn' => $game->pgn,
            'moves_uci' => $uci === '' ? [] : explode(' ', $uci),
            'raw_moves' => is_array($bruts) ? array_values($bruts) : null,
        ];
    }
}
