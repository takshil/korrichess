<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    /**
     * Une partie importée depuis une plateforme (issue #3) ou saisie à la main.
     */
    protected $fillable = [
        'user_id',
        'source',
        'external_id',
        'played_at',
        'opponent',
        'opponent_rating',
        'color',
        'result',
        'time_control',
        'opening',
        'tournament',
        'round',
        'pgn',
        'moves_uci',
        'status',
        'storage_key',
    ];

    protected $attributes = [
        'status' => 'final',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
