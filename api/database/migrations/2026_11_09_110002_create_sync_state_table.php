<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table sync_state : curseur incrémental par compte lié (L3, issue #3).
     * last_game_at mémorise la plus récente partie importée : la sync suivante
     * ne récupère que les parties plus récentes.
     */
    public function up(): void
    {
        Schema::create('sync_state', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('username', 64);
            $table->string('last_game_at', 40)->nullable();
            $table->string('last_sync_at', 40)->nullable();
            $table->primary(['user_id', 'platform', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_state');
    }
};
