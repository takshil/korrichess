<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index des positions (B6) : FEN de chaque demi-coup d'une partie,
     * indexée à la demande une seule fois. Clé (game_id, ply).
     */
    public function up(): void
    {
        Schema::create('game_positions', function (Blueprint $table): void {
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedSmallInteger('ply');
            $table->string('fen', 96);
            $table->primary(['game_id', 'ply']);
            $table->index('fen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_positions');
    }
};
