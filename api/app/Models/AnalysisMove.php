<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisMove extends Model
{
    /**
     * Évaluation d'une position dans une analyse (A2–A7, issue #5).
     */
    protected $fillable = [
        'analysis_id',
        'ply',
        'fen',
        'move_uci',
        'move_san',
        'eval_cp',
        'best_move_uci',
        'best_move_san',
        'variation_uci',
        'classification',
        'explanation',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(GameAnalysis::class, 'analysis_id');
    }
}
