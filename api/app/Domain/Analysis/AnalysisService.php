<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Analysis;

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Queue;
use Korrichess\Api\Domain\Chess\Board;
use Korrichess\Api\Domain\Games\GameRepository;

/**
 * Rejeu et analyse des parties (issue #5) :
 * A2 evaluations par position, A3 courbe, A4 classement des coups,
 * A5 moment decisif, A6 meilleur coup et variante, A7 explications,
 * A9 ouverture et sortie de theorie, A10 analyse a la demande ou automatique.
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

    /** ouvertures mini-book pour A9 (ECO -> [nom, coups theoriques SAN]). */
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
        private readonly Queue $queue = new Queue(),
    ) {
    }

    /** A10 : enfile une analyse. Retourne le statut courant. @return array{status:int,body:array} */
    public function request(int $userId, int $gameId, int $depth = 12): array
    {
        $game = Database::one('SELECT id, user_id FROM games WHERE id = :id AND user_id = :u', ['id' => $gameId, 'u' => $userId]);
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }
        $existing = $this->latest($userId, $gameId);
        if ($existing !== null && $existing['status'] === 'done') {
            return ['status' => 200, 'body' => ['analysis_id' => $existing['id'], 'status' => 'done', 'already' => true]];
        }
        $row = Database::one('SELECT id FROM game_analyses WHERE game_id = :g AND user_id = :u AND status = "pending"', ['g' => $gameId, 'u' => $userId]);
        if ($row !== null) {
            return ['status' => 200, 'body' => ['analysis_id' => (int) $row['id'], 'status' => 'pending']];
        }
        $now = gmdate('c');
        Database::run(
            'INSERT INTO game_analyses (game_id, user_id, depth, engine, status, created_at, updated_at)
             VALUES (:g, :u, :d, :e, "pending", :now, :now)',
            ['g' => $gameId, 'u' => $userId, 'd' => max(4, min(20, $depth)), 'e' => $this->engine->name(), 'now' => $now],
        );
        $analysisId = Database::lastId();
        $this->queue->enqueue('analyse', ['analysis_id' => $analysisId, 'game_id' => $gameId, 'user_id' => $userId], priority: 5);
        return ['status' => 202, 'body' => ['analysis_id' => $analysisId, 'status' => 'pending']];
    }

    /**
     * A2-A7, A9 : execute l'analyse complete d'une partie et persiste les
     * resultats. Appele par le worker (asynchrone, A10).
     * @return array{status:int,body:array}
     */
    public function analyse(int $userId, int $gameId, int $depth = 12): array
    {
        $game = Database::one('SELECT * FROM games WHERE id = :id AND user_id = :u', ['id' => $gameId, 'u' => $userId]);
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }
        $uciMoves = $this->games->movesUci($gameId);
        if ($uciMoves === []) {
            return ['status' => 422, 'body' => ['error' => 'partie_sans_coups']];
        }
        $depth = max(4, min(20, $depth));
        $now = gmdate('c');
        $pending = Database::one(
            'SELECT id FROM game_analyses WHERE game_id = :g AND user_id = :u AND status = "pending" ORDER BY id DESC LIMIT 1',
            ['g' => $gameId, 'u' => $userId],
        );
        if ($pending !== null) {
            $analysisId = (int) $pending['id'];
            Database::run(
                'UPDATE game_analyses SET status = "running", depth = :d, engine = :e, updated_at = :now WHERE id = :id',
                ['d' => $depth, 'e' => $this->engine->name(), 'now' => $now, 'id' => $analysisId],
            );
        } else {
            Database::run(
                'INSERT INTO game_analyses (game_id, user_id, depth, engine, status, created_at, updated_at)
                 VALUES (:g, :u, :d, :e, "running", :now, :now)',
                ['g' => $gameId, 'u' => $userId, 'd' => $depth, 'e' => $this->engine->name(), 'now' => $now],
            );
            $analysisId = Database::lastId();
        }

        $board = Board::start();
        $rows = [];
        $evals = [];
        $positions = [];
        $positions[] = $board->fen();
        foreach ($uciMoves as $uci) {
            if (!$board->applyUci($uci)) {
                break;
            }
            $positions[] = $board->fen();
        }

        // A9 : ouverture et sortie de theorie
        [$openingName, $bookExitPly] = $this->detectOpening($game['opening'], $uciMoves);

        // A2 : evaluation de chaque position
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

        Database::pdo()->beginTransaction();
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO analysis_moves (analysis_id, ply, fen, move_uci, move_san, eval_cp, best_move_uci, best_move_san, variation_uci, classification, explanation)
                 VALUES (:a, :ply, :fen, :mu, :ms, :ev, :bu, :bs, :var, :class, :expl)',
            );
            for ($ply = 0; $ply < count($rows); $ply++) {
                $row = $rows[$ply];
                $played = $ply < count($uciMoves) ? $uciMoves[$ply] : null;
                [$classification, $explanation, $bestSan] = $this->classify($positions, $uciMoves, $rows, $ply);
                $stmt->execute([
                    'a' => $analysisId,
                    'ply' => $ply,
                    'fen' => $row['fen'],
                    'mu' => $played ?? '',
                    'ms' => $this->sanAt($positions, $uciMoves, $ply),
                    'ev' => $row['eval'],
                    'bu' => $row['best'],
                    'bs' => $bestSan,
                    'var' => implode(' ', array_slice($row['variation'], 0, 12)),
                    'class' => $classification,
                    'expl' => $explanation,
                ]);
            }
            // A5 : moment decisif = plus grande chute d'evaluation
            $decisivePly = $this->decisivePly($evals);
            Database::run(
                'UPDATE game_analyses SET status = "done", decisive_ply = :dp, opening_name = :on, book_exit_ply = :bx, updated_at = :now
                 WHERE id = :id',
                ['dp' => $decisivePly, 'on' => $openingName, 'bx' => $bookExitPly, 'now' => gmdate('c'), 'id' => $analysisId],
            );
            Database::pdo()->commit();
        } catch (\Throwable $e) {
            Database::pdo()->rollBack();
            Database::run('UPDATE game_analyses SET status = "failed", updated_at = :now WHERE id = :id', ['now' => gmdate('c'), 'id' => $analysisId]);
            throw $e;
        }
        return ['status' => 200, 'body' => ['analysis_id' => $analysisId, 'status' => 'done']];
    }

    /**
     * A1 : rejeu — positions et coups de la partie, pret pour un echiquier
     * interactif (coup par coup, avance rapide, retour).
     * @return array{status:int,body:array}
     */
    public function replay(int $userId, int $gameId): array
    {
        $game = Database::one('SELECT * FROM games WHERE id = :id AND user_id = :u', ['id' => $gameId, 'u' => $userId]);
        if ($game === null) {
            return ['status' => 404, 'body' => ['error' => 'partie_introuvable']];
        }
        $uciMoves = $this->games->movesUci($gameId);
        $board = Board::start();
        $positions = [$board->fen()];
        $sans = [];
        foreach ($uciMoves as $i => $uci) {
            $sans[] = $board->uciToSan($uci);
            if (!$board->applyUci($uci)) {
                break;
            }
            $positions[] = $board->fen();
        }
        return ['status' => 200, 'body' => [
            'game_id' => $gameId,
            'source' => $game['source'],
            'result' => $game['result'],
            'moves_san' => $sans,
            'moves_uci' => $uciMoves,
            'positions' => $positions,
        ]];
    }

    /** A2-A6 : resultat complet d'une analyse enregistree. @return array{status:int,body:array} */
    public function result(int $userId, int $gameId): array
    {
        $analysis = $this->latest($userId, $gameId);
        if ($analysis === null) {
            return ['status' => 404, 'body' => ['error' => 'analyse_introuvable']];
        }
        $moves = Database::all(
            'SELECT ply, fen, move_uci, move_san, eval_cp, best_move_uci, best_move_san, variation_uci, classification, explanation
             FROM analysis_moves WHERE analysis_id = :id ORDER BY ply',
            ['id' => $analysis['id']],
        );
        $body = [
            'analysis_id' => (int) $analysis['id'],
            'game_id' => (int) $analysis['game_id'],
            'status' => $analysis['status'],
            'depth' => (int) $analysis['depth'],
            'engine' => $analysis['engine'],
            'opening_name' => $analysis['opening_name'],
            'book_exit_ply' => $analysis['book_exit_ply'] !== null ? (int) $analysis['book_exit_ply'] : null,
            'decisive_ply' => $analysis['decisive_ply'] !== null ? (int) $analysis['decisive_ply'] : null,
            'moves' => array_map(static fn (array $m): array => [
                'ply' => (int) $m['ply'],
                'fen' => $m['fen'],
                'move_san' => $m['move_san'],
                'eval_cp' => (int) $m['eval_cp'],
                'best_move_san' => $m['best_move_san'],
                'classification' => $m['classification'],
                'explanation' => $m['explanation'],
                'variation' => $m['variation_uci'] === '' ? [] : explode(' ', (string) $m['variation_uci']),
            ], $moves),
        ];
        if ($analysis['status'] === 'done') {
            $body['eval_curve'] = array_map(static fn (array $m): array => [
                'ply' => (int) $m['ply'],
                'eval_cp' => (int) $m['eval_cp'],
            ], $moves);
            $counts = array_count_values(array_column($body['moves'], 'classification'));
            $body['classification_counts'] = $counts;
        }
        return ['status' => 200, 'body' => $body];
    }

    /**
     * A8 : essayer un autre coup — valide un coup depuis une position de la
     * partie et retourne la position resultante (sans modifier la partie).
     * @return array{status:int,body:array}
     */
    public function explore(int $userId, int $gameId, int $ply, string $moveUci): array
    {
        $game = Database::one('SELECT id FROM games WHERE id = :id AND user_id = :u', ['id' => $gameId, 'u' => $userId]);
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

    /** A4 + A7 : classe un coup et genere une explication simple. @param array<int,array> $rows */
    private function classify(array $positions, array $uciMoves, array $rows, int $ply): array
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
        // Evaluation du point de vue du joueur au trait : blanc veut +, noir veut -
        $loss = $turn === 'w' ? ($before - $after) : ($after - $before);
        $bestSan = null;
        if ($best !== null) {
            $bestSan = $board->uciToSan($best);
        }
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
            $explanation = $this->explain($board, $played, $best, $variation, $loss);
        }
        return [$classification, $explanation, $bestSan];
    }

    /** A7 : explication en langage simple. */
    private function explain(Board $board, string $played, ?string $best, array $variation, int $loss): ?string
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
        // tactique manquee : le meilleur coup est une prise non jouee
        if ($best !== null && $board->pieceAt(substr($best, 2, 2)) !== null && $board->pieceAt(substr($best, 2, 2))[0] !== $board->turn()) {
            $parts[] = 'Une pièce adverse était prenable.';
        }
        // piece sans defense : la piece jouee atterrit sur une case attaquee
        $after = clone $board;
        $after->applyUci($played);
        $to = substr($played, 2, 2);
        $piece = $after->pieceAt($to);
        if ($piece !== null && $piece[0] === $board->turn() && $piece[1] !== 'K' && $after->isCheck($board->turn())) {
            $parts[] = 'Ta pièce tombe sous le contrôle adverse.';
        }
        return implode(' ', $parts);
    }

    /** A5 : plus grande chute d'evaluation entre deux demi-coups consecutifs. @param array<int,int> $evals */
    private function decisivePly(array $evals): ?int
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
     * A9 : nom de l'ouverture et demi-coup de sortie de theorie.
     * @return array{0:?string,1:?int}
     */
    private function detectOpening(?string $eco, array $uciMoves): array
    {
        if ($eco !== null && isset(self::OPENING_BOOK[$eco])) {
            [$name, $bookSans] = self::OPENING_BOOK[$eco];
            $board = Board::start();
            $exit = null;
            foreach ($bookSans as $i => $san) {
                if ($i >= count($uciMoves) || $board->sanToUci($san) === null) {
                    $exit = $i;
                    break;
                }
                $uci = $board->sanToUci($san);
                if ($uciMoves[$i] !== $uci) {
                    $exit = $i;
                    break;
                }
                $board->applyUci($uci);
            }
            return [$name, $exit];
        }
        return [$eco, null];
    }

    /** SAN joue au demi-coup $ply (coup quittant la position). @param array<int,string> $uciMoves */
    private function sanAt(array $positions, array $uciMoves, int $ply): string
    {
        if ($ply >= count($uciMoves)) {
            return '';
        }
        $board = Board::fromFen($positions[$ply]);
        return $board->uciToSan($uciMoves[$ply]);
    }

    /** @return array<string,mixed>|null */
    private function latest(int $userId, int $gameId): ?array
    {
        return Database::one(
            'SELECT * FROM game_analyses WHERE game_id = :g AND user_id = :u ORDER BY id DESC LIMIT 1',
            ['g' => $gameId, 'u' => $userId],
        );
    }
}
