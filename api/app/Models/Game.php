<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'needs_review',
        'raw_moves',
    ];

    protected $attributes = [
        'status' => 'final',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Parties d'un joueur : base de toute requête (isolation stricte par utilisateur). */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Coups de la partie au format UCI.
     *
     * @return list<string>
     */
    public function uciMoves(): array
    {
        $raw = trim((string) $this->moves_uci);

        return $raw === '' ? [] : explode(' ', $raw);
    }

    /**
     * Étiquettes personnelles.
     *
     * @return list<string>
     */
    public function tagList(): array
    {
        return ($this->tags === '' || $this->tags === null) ? [] : explode(',', (string) $this->tags);
    }

    /**
     * Saisie manuelle brute (coups tels que tapés), null hors saisie douteuse.
     *
     * @return list<mixed>|null
     */
    public function rawMoveList(): ?array
    {
        $raw = $this->raw_moves !== null ? json_decode((string) $this->raw_moves, true) : null;

        return is_array($raw) ? array_values($raw) : null;
    }
}
