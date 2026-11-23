<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AnalysisMove;
use App\Models\GameAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON d'une analyse complète (A2–A6, A9). Ressource : GameAnalysis
 * avec sa relation `moves` chargée et triée.
 *
 * @property GameAnalysis $resource
 */
final class AnalysisResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $analysis = $this->resource;

        $moves = $analysis->moves->map(static fn (AnalysisMove $m): array => [
            'ply' => (int) $m->ply,
            'fen' => $m->fen,
            'move_san' => $m->move_san,
            'eval_cp' => (int) $m->eval_cp,
            'best_move_san' => $m->best_move_san,
            'classification' => $m->classification,
            'explanation' => $m->explanation,
            'variation' => ($m->variation_uci === '' || $m->variation_uci === null) ? [] : explode(' ', (string) $m->variation_uci),
        ])->all();

        $body = [
            'analysis_id' => (int) $analysis->id,
            'game_id' => (int) $analysis->game_id,
            'status' => $analysis->status,
            'depth' => (int) $analysis->depth,
            'engine' => $analysis->engine,
            'opening_name' => $analysis->opening_name,
            'book_exit_ply' => $analysis->book_exit_ply !== null ? (int) $analysis->book_exit_ply : null,
            'decisive_ply' => $analysis->decisive_ply !== null ? (int) $analysis->decisive_ply : null,
            'moves' => $moves,
        ];

        if ($analysis->status === 'done') {
            // A3 : courbe d'évaluation.
            $body['eval_curve'] = array_map(
                static fn (array $m): array => ['ply' => $m['ply'], 'eval_cp' => $m['eval_cp']],
                $moves,
            );
            // A4 : comptage par catégorie.
            $body['classification_counts'] = array_count_values(array_column($moves, 'classification'));
        }

        return $body;
    }
}
