<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameAnalysis extends Model
{
    /**
     * Une analyse de partie (issue #5) : pending → running → done/failed.
     */
    protected $fillable = [
        'game_id',
        'user_id',
        'depth',
        'engine',
        'status',
        'decisive_ply',
        'opening_name',
        'book_exit_ply',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function moves(): HasMany
    {
        return $this->hasMany(AnalysisMove::class, 'analysis_id');
    }

    /** Analyses d'une partie d'un joueur. */
    public function scopeForGame(Builder $query, int $userId, int $gameId): Builder
    {
        return $query->where('game_id', $gameId)->where('user_id', $userId);
    }
}
