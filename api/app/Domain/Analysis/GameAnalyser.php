<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Chess\Board;
use App\Domain\Chess\GameReplay;
use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;
use App\Models\AnalysisMove;
use App\Models\GameAnalysis;
use Illuminate\Support\Facades\DB;

/**
 * A2–A7, A9 : exécute l'analyse complète d'une partie et persiste les
 * résultats. Appelé par AnalyseGameJob (asynchrone, A10).
 */
final class GameAnalyser
{
    /** Nombre de coups de la variante principale conservés par position (A6). */
    private const VARIATION_LENGTH = 12;

    public function __construct(
        private readonly EngineClient $engine,
        private readonly GameRepository $games,
        private readonly MoveClassifier $classifier,
        private readonly OpeningDetector $openings,
    ) {
    }

    /**
     * @throws DomainError partie introuvable ou sans coups
     */
    public function analyse(int $userId, int $gameId, ?int $depth = null): GameAnalysis
    {
        $game = $this->games->findOwnedOrFail($userId, $gameId);
        $uciMoves = $game->uciMoves();
        if ($uciMoves === []) {
            throw DomainError::invalid('partie_sans_coups');
        }

        $depth = AnalysisDepth::clamp($depth);
        $analysis = $this->startRun($userId, $gameId, $depth);

        $positions = GameReplay::fromUci($uciMoves)->positions;
        [$openingName, $bookExitPly] = $this->openings->detect($game->opening, $uciMoves);
        $evaluations = $this->evaluateAll($positions, $depth);

        try {
            DB::transaction(function () use ($analysis, $positions, $uciMoves, $evaluations, $openingName, $bookExitPly): void {
                foreach ($positions as $ply => $fen) {
                    $this->storeMove($analysis, $ply, $positions, $uciMoves, $evaluations);
                }

                $analysis->update([
                    'status' => 'done',
                    'decisive_ply' => DecisiveMoment::ply(array_column($evaluations, 'eval_cp')),
                    'opening_name' => $openingName,
                    'book_exit_ply' => $bookExitPly,
                ]);
            });
        } catch (\Throwable $e) {
            $analysis->update(['status' => 'failed']);
            throw $e;
        }

        return $analysis;
    }

    /** Reprend l'analyse en attente (créée par AnalysisRequester) ou en ouvre une nouvelle. */
    private function startRun(int $userId, int $gameId, int $depth): GameAnalysis
    {
        $analysis = GameAnalysis::query()
            ->forGame($userId, $gameId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first() ?? new GameAnalysis(['game_id' => $gameId, 'user_id' => $userId]);

        $analysis->fill([
            'status' => 'running',
            'depth' => $depth,
            'engine' => $this->engine->name(),
        ])->save();

        return $analysis;
    }

    /**
     * A2 : évaluation moteur de chaque position.
     *
     * @param list<string> $positions
     * @return list<array{eval_cp:int,best_move:?string,variation:list<string>}>
     */
    private function evaluateAll(array $positions, int $depth): array
    {
        return array_map(fn (string $fen): array => $this->engine->evaluate($fen, $depth), $positions);
    }

    /**
     * @param list<string> $positions
     * @param list<string> $uciMoves
     * @param list<array{eval_cp:int,best_move:?string,variation:list<string>}> $evaluations
     */
    private function storeMove(GameAnalysis $analysis, int $ply, array $positions, array $uciMoves, array $evaluations): void
    {
        $played = $uciMoves[$ply] ?? null;
        $evaluation = $evaluations[$ply];

        $san = '';
        $verdict = MoveVerdict::none();
        if ($played !== null) {
            $san = Board::fromFen($positions[$ply])->uciToSan($played);
            $verdict = $this->classifier->classify(
                $positions[$ply],
                $played,
                $evaluation['eval_cp'],
                $evaluations[$ply + 1]['eval_cp'] ?? $evaluation['eval_cp'],
                $evaluation['best_move'],
            );
        }

        AnalysisMove::create([
            'analysis_id' => $analysis->id,
            'ply' => $ply,
            'fen' => $positions[$ply],
            'move_uci' => $played ?? '',
            'move_san' => $san,
            'eval_cp' => $evaluation['eval_cp'],
            'best_move_uci' => $evaluation['best_move'],
            'best_move_san' => $verdict->bestSan,
            'variation_uci' => implode(' ', array_slice($evaluation['variation'], 0, self::VARIATION_LENGTH)),
            'classification' => $verdict->classification,
            'explanation' => $verdict->explanation,
        ]);
    }
}
