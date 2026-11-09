<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    public const CREATED_AT = null;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'language',
        'theme',
        'board_style',
        'piece_style',
        'notify_analysis_done',
        'notify_exercise_reminder',
        'notify_games_synced',
    ];

    protected function casts(): array
    {
        return [
            'notify_analysis_done' => 'boolean',
            'notify_exercise_reminder' => 'boolean',
            'notify_games_synced' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
