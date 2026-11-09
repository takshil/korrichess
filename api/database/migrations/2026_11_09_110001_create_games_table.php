<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table games : parties importées depuis les plateformes (L2, issue #3).
     * L'unicité (user_id, source, external_id) rend l'import idempotent :
     * une partie déjà importée n'est jamais insérée deux fois (L6).
     * played_at est stocké tel que fourni (date PGN ou ISO plateforme).
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('external_id', 128);
            $table->string('played_at', 40)->nullable();
            $table->string('opponent', 64)->nullable();
            $table->unsignedInteger('opponent_rating')->nullable();
            $table->string('color', 8)->nullable();
            $table->string('result', 12)->nullable();
            $table->string('time_control', 24)->nullable();
            $table->string('opening', 120)->nullable();
            $table->string('tournament', 160)->nullable();
            $table->string('round', 24)->nullable();
            $table->text('pgn');
            $table->longText('moves_uci')->nullable();
            $table->string('status', 20)->default('final');
            $table->string('storage_key')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'source', 'external_id']);
            $table->index(['user_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
