<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Analyses de parties (issue #5) : une ligne par analyse demandée,
     * exécutée en file (A10), avec le moment décisif (A5) et la sortie de
     * théorie (A9).
     */
    public function up(): void
    {
        Schema::create('game_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth');
            $table->string('engine', 64);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('decisive_ply')->nullable();
            $table->string('opening_name', 120)->nullable();
            $table->unsignedSmallInteger('book_exit_ply')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_analyses');
    }
};
