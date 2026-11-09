<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Chess\Board;
use App\Domain\Games\GameRepository;
use App\Jobs\AnalyseGameJob;
use App\Models\AnalysisMove;
use App\Models\Game;
use App\Models\GameAnalysis;
use Illuminate\Support\Facades\DB;

/**
 * Rejeu et analyse des parties (issue #5) :
 * A2 évaluations par position, A3 courbe, A4 classement des coups,
 * A5 moment décisif, A6 meilleur coup et variante, A7 explications,
 * A9 ouverture et sortie de théorie, A10 analyse à la demande ou
 * automatique (asynchrone via AnalyseGameJob).
 */
final class AnalysisService
{
    /** Seuils en centipions pour le classement (A4). */
    private const THRESHOLDS = [
        'meilleur' => 10,
        'bon' => 50,
        'imprecision' => 100,
        'erreur' => 250,
    ];

    /** Ouvertures mini-book pour A9 (ECO → [nom, coups théoriques SAN]). */
    private const OPENING_BOOK = [
        'C50' => ['Partie italienne', ['e4', 'e5', 'Nf3', 'Nc6', 'Bc4', 'Bc5']],
        'C55' => ['Partie italienne, variante hongroise', ['e4', 'e5', 'Nf3', 'Nc6', 'Bc4', 'Nf6']],
        'B00' => ['Ouverture irrégulière', ['e4']],
        'B01' => ['Défense scandinave', ['e4', 'd5']],
        'D02' => ['Ouverture de la dame', ['d4', 'd5', 'Nf3']],
        'C44' => ['Ouverture écossaise', ['e4', 'e5', 'Nf3', 'Nc6', 'd4']],
    ];

    public function __construct(
        private readonly EngineClient $engine,
        private readonly GameRepository $games = new GameRepository(),
    ) {
    }

    /**
     * A10 : enfile une analyse. Retourne le statut courant.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function request(int $userId, int $gameId, int $depth = 12): array
    {
        $game = Game::query()->where('id', $gameId)->where('user_id', $userId)->first();
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }

        $existing = $this->latest($userId, $gameId);
        if ($existing !== null && $existing->status === 'done') {
            return ['status' => 200, 'body' => ['analysis_id' => (int) $existing->id, 'status' => 'done', 'already' => true]];
        }

        $pending = GameAnalysis::query()
            ->where('game_id', $gameId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->first();
        if ($pending !== null) {
            return ['status' => 200, 'body' => ['analysis_id' => (int) $pending->id, 'status' => 'pending']];
        }

        $depth = max(4, min(20, $depth));
        $analysis = GameAnalysis::create([
            'game_id' => $gameId,
            'user_id' => $userId,
            'depth' => $depth,
            'engine' => $this->engine->name(),
            'status' => 'pending',
        ]);

        AnalyseGameJob::dispatch((int) $analysis->id, $gameId, $userId, $depth);

        return ['status' => 202, 'body' => ['analysis_id' => (int) $analysis->id, 'status' => 'pending']];
    }

    /**
     * A2–A7, A9 : exécute l'analyse complète d'une partie et persiste les
     * résultats. Appelé par le job AnalyseGameJob (asynchrone, A10).
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function analyse(int $userId, int $gameId, int $depth = 12): array
    {
        $game = Game::query()->where('id', $gameId)->where('user_id', $userId)->first();
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }

        $uciMoves = $this->games->movesUci($gameId);
        if ($uciMoves === []) {
            return ['status' => 422, 'body' => ['error' => 'partie_sans_coups']];
        }
        $depth = max(4, min(20, $depth));

        $pending = GameAnalysis::query()
            ->where('game_id', $gameId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
        if ($pending !== null) {
            $pending->status = 'running';
            $pending->depth = $depth;
            $pending->engine = $this->engine->name();
            $pending->save();
            $analysisId = (int) $pending->id;
        } else {
            $analysis = GameAnalysis::create([
                'game_id' => $gameId,
                'user_id' => $userId,
                'depth' => $depth,
                'engine' => $this->engine->name(),
                'status' => 'running',
            ]);
            $analysisId = (int) $analysis->id;
        }

        $board = Board::start();
        $positions = [$board->fen()];
        foreach ($uciMoves as $uci) {
            if (!$board->applyUci($uci)) {
                break;
            }
            $positions[] = $board->fen();
        }

        // A9 : ouverture et sortie de théorie.
        [$openingName, $bookExitPly] = $this->detectOpening($game->opening, $uciMoves);

        // A2 : évaluation de chaque position.
        $rows = [];
        $evals = [];
        foreach ($positions as $ply => $fen) {
            $result = $this->engine->evaluate($fen, $depth);
            $rows[] = [
                'ply' => $ply,
                'fen' => $fen,
                'eval' => $result['eval_cp'],
                'best' => $result['best_move'],
                'variation' => $result['variation'],
            ];
            $evals[] = $result['eval_cp'];
        }

        try {
            DB::transaction(static function () use ($analysisId, $positions, $uciMoves, $rows, $evals, $openingName, $bookExitPly): void {
                for ($ply = 0; $ply < count($rows); $ply++) {
                    $row = $rows[$ply];
                    $played = $ply < count($uciMoves) ? $uciMoves[$ply] : null;
                    [$classification, $explanation, $bestSan] = self::classifyMove($positions, $uciMoves, $rows, $ply);
                    AnalysisMove::create([
                        'analysis_id' => $analysisId,
                        'ply' => $ply,
                        'fen' => $row['fen'],
                        'move_uci' => $played ?? '',
                        'move_san' => self::sanAt($positions, $uciMoves, $ply),
                        'eval_cp' => $row['eval'],
                        'best_move_uci' => $row['best'],
                        'best_move_san' => $bestSan,
                        'variation_uci' => implode(' ', array_slice($row['variation'], 0, 12)),
                        'classification' => $classification,
                        'explanation' => $explanation,
                    ]);
                }

                // A5 : moment décisif = plus grande chute d'évaluation.
                GameAnalysis::query()->whereKey($analysisId)->update([
                    'status' => 'done',
                    'decisive_ply' => self::decisivePly($evals),
                    'opening_name' => $openingName,
                    'book_exit_ply' => $bookExitPly,
                ]);
            });
        } catch (\Throwable $e) {
            GameAnalysis::query()->whereKey($analysisId)->update(['status' => 'failed']);
            throw $e;
        }

        return ['status' => 200, 'body' => ['analysis_id' => $analysisId, 'status' => 'done']];
    }

    /**
     * A1 : rejeu — positions et coups de la partie, prêts pour un échiquier
     * interactif (coup par coup, avance rapide, retour).
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function replay(int $userId, int $gameId): array
    {
        $game = Game::query()->where('id', $gameId)->where('user_id', $userId)->first();
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }

        $uciMoves = $this->games->movesUci($gameId);
        $board = Board::start();
        $positions = [$board->fen()];
        $sans = [];
        foreach ($uciMoves as $uci) {
            $sans[] = $board->uciToSan($uci);
            if (!$board->applyUci($uci)) {
                break;
            }
            $positions[] = $board->fen();
        }

        return ['status' => 200, 'body' => [
            'game_id' => $gameId,
            'source' => $game->source,
            'result' => $game->result,
            'moves_san' => $sans,
            'moves_uci' => $uciMoves,
            'positions' => $positions,
        ]];
    }

    /**
     * A2–A6, A9 : résultat complet de la dernière analyse enregistrée.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function result(int $userId, int $gameId): array
    {
        $analysis = $this->latest($userId, $gameId);
        if ($analysis === null) {
            return ['status' => 404, 'body' => ['error' => 'analyse_introuvable']];
        }

        $moves = AnalysisMove::query()
            ->where('analysis_id', $analysis->id)
            ->orderBy('ply')
            ->get();

        $body = [
            'analysis_id' => (int) $analysis->id,
            'game_id' => (int) $analysis->game_id,
            'status' => $analysis->status,
            'depth' => (int) $analysis->depth,
            'engine' => $analysis->engine,
            'opening_name' => $analysis->opening_name,
            'book_exit_ply' => $analysis->book_exit_ply !== null ? (int) $analysis->book_exit_ply : null,
            'decisive_ply' => $analysis->decisive_ply !== null ? (int) $analysis->decisive_ply : null,
            'moves' => $moves->map(static fn (AnalysisMove $m): array => [
                'ply' => (int) $m->ply,
                'fen' => $m->fen,
                'move_san' => $m->move_san,
                'eval_cp' => (int) $m->eval_cp,
                'best_move_san' => $m->best_move_san,
                'classification' => $m->classification,
                'explanation' => $m->explanation,
                'variation' => ($m->variation_uci === '' || $m->variation_uci === null) ? [] : explode(' ', (string) $m->variation_uci),
            ])->all(),
        ];
        if ($analysis->status === 'done') {
            // A3 : courbe d'évaluation.
            $body['eval_curve'] = $moves->map(static fn (AnalysisMove $m): array => [
                'ply' => (int) $m->ply,
                'eval_cp' => (int) $m->eval_cp,
            ])->all();
            // A4 : comptage par catégorie.
            $body['classification_counts'] = array_count_values(array_column($body['moves'], 'classification'));
        }

        return ['status' => 200, 'body' => $body];
    }

    /**
     * A8 : essayer un autre coup — valide un coup depuis une position de la
     * partie et retourne la position résultante (sans modifier la partie).
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function explore(int $userId, int $gameId, int $ply, string $moveUci): array
    {
        $game = Game::query()->where('id', $gameId)->where('user_id', $userId)->first();
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }

        $uciMoves = $this->games->movesUci($gameId);
        $board = Board::start();
        for ($i = 0; $i < $ply && $i < count($uciMoves); $i++) {
            if (!$board->applyUci($uciMoves[$i])) {
                return ['status' => 422, 'body' => ['error' => 'position_inatteignable']];
            }
        }
        if ($board->isCheckmate($board->turn()) || $board->isStalemate()) {
            return ['status' => 422, 'body' => ['error' => 'partie_terminee']];
        }

        $boardBefore = clone $board;
        if (!$board->applyUci($moveUci)) {
            return ['status' => 422, 'body' => ['error' => 'coup_illégal', 'fen' => $boardBefore->fen()]];
        }

        $san = $boardBefore->uciToSan($moveUci);
        $evalResult = $this->engine->evaluate($board->fen());

        return ['status' => 200, 'body' => [
            'ply' => $ply,
            'move_uci' => $moveUci,
            'move_san' => $san,
            'fen' => $board->fen(),
            'eval_cp' => $evalResult['eval_cp'],
        ]];
    }

    /**
     * A4 + A7 : classe un coup et génère une explication simple.
     *
     * @param array<int,string> $positions
     * @param array<int,string> $uciMoves
     * @param array<int,array<string,mixed>> $rows
     *
     * @return array{0:string,1:?string,2:?string}
     */
    private static function classifyMove(array $positions, array $uciMoves, array $rows, int $ply): array
    {
        $default = ['meilleur', null, null];
        if ($ply >= count($uciMoves)) {
            return $default;
        }

        $played = $uciMoves[$ply];
        $before = $rows[$ply]['eval'];
        $after = $rows[$ply + 1]['eval'] ?? $before;
        $best = $rows[$ply]['best'];
        $variation = $rows[$ply]['variation'];
        $board = Board::fromFen($positions[$ply]);
        $turn = $board->turn();

        // Évaluation du point de vue du joueur au trait : blanc veut +, noir veut −.
        $loss = $turn === 'w' ? ($before - $after) : ($after - $before);

        $bestSan = $best !== null ? $board->uciToSan($best) : null;
        if ($best !== null && $played === $best) {
            return ['meilleur', null, $bestSan];
        }

        $classification = 'gaffe';
        foreach (self::THRESHOLDS as $label => $threshold) {
            if ($loss <= $threshold) {
                $classification = $label;
                break;
            }
        }

        $explanation = null;
        if ($classification !== 'meilleur' && $classification !== 'bon') {
            $explanation = self::explain($board, $played, $best, $variation, $loss);
        }

        return [$classification, $explanation, $bestSan];
    }

    /**
     * A7 : explication en langage simple.
     *
     * @param array<int,string> $variation
     */
    private static function explain(Board $board, string $played, ?string $best, array $variation, int $loss): ?string
    {
        $parts = [];
        if ($loss > 900) {
            $parts[] = 'Ce coup perd une pièce.';
        } elseif ($loss > 250) {
            $parts[] = 'Ce coup laisse échapper un avantage important.';
        } else {
            $parts[] = 'Ce coup laisse passer une meilleure opportunité.';
        }
        if ($best !== null) {
            $san = $board->uciToSan($best);
            $parts[] = "Le moteur suggère $san.";
        }
        // Tactique manquée : le meilleur coup est une prise non jouée.
        if ($best !== null && $board->pieceAt(substr($best, 2, 2)) !== null && $board->pieceAt(substr($best, 2, 2))[0] !== $board->turn()) {
            $parts[] = 'Une pièce adverse était prenable.';
        }
        // Pièce sans défense : la pièce jouée atterrit sur une case attaquée.
        $after = clone $board;
        $after->applyUci($played);
        $to = substr($played, 2, 2);
        $piece = $after->pieceAt($to);
        if ($piece !== null && $piece[0] === $board->turn() && $piece[1] !== 'K' && $after->isCheck($board->turn())) {
            $parts[] = 'Ta pièce tombe sous le contrôle adverse.';
        }

        return implode(' ', $parts);
    }

    /**
     * A5 : plus grande chute d'évaluation entre deux demi-coups consécutifs.
     *
     * @param array<int,int> $evals
     */
    private static function decisivePly(array $evals): ?int
    {
        $maxDrop = 0;
        $ply = null;
        for ($i = 1; $i < count($evals); $i++) {
            $drop = abs($evals[$i] - $evals[$i - 1]);
            if ($drop > $maxDrop) {
                $maxDrop = $drop;
                $ply = $i - 1;
            }
        }

        return $ply;
    }

    /**
     * A9 : nom de l'ouverture et demi-coup de sortie de théorie.
     *
     * @param array<int,string> $uciMoves
     *
     * @return array{0:?string,1:?int}
     */
    private static function detectOpening(?string $eco, array $uciMoves): array
    {
        if ($eco !== null && isset(self::OPENING_BOOK[$eco])) {
            [$name, $bookSans] = self::OPENING_BOOK[$eco];
            $board = Board::start();
            $exit = null;
            foreach ($bookSans as $i => $san) {
                $uci = $board->sanToUci($san);
                if ($uci === null || $i >= count($uciMoves) || $uciMoves[$i] !== $uci) {
                    $exit = $i;
                    break;
                }
                $board->applyUci($uci);
            }

            return [$name, $exit];
        }

        return [$eco, null];
    }

    /**
     * SAN joué au demi-coup $ply (coup quittant la position).
     *
     * @param array<int,string> $positions
     * @param array<int,string> $uciMoves
     */
    private static function sanAt(array $positions, array $uciMoves, int $ply): string
    {
        if ($ply >= count($uciMoves)) {
            return '';
        }
        $board = Board::fromFen($positions[$ply]);

        return $board->uciToSan($uciMoves[$ply]);
    }

    private function latest(int $userId, int $gameId): ?GameAnalysis
    {
        return GameAnalysis::query()
            ->where('game_id', $gameId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();
    }
}
