<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedAccount extends Model
{
    protected $fillable = ['platform', 'username'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
