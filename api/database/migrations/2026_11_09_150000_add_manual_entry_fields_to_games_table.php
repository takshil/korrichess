<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saisie manuelle (S5 + S7, issue #25) : une partie transcrite depuis
     * une feuille papier peut contenir des erreurs. Quand la validation
     * des coups est désactivée, la partie est conservée telle quelle :
     * needs_review la signale, raw_moves garde la liste des coups tels
     * qu'ils ont été saisis (JSON), et moves_uci ne contient que le
     * préfixe vérifié (le rejeu s'arrête au premier coup douteux).
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->boolean('needs_review')->default(false);
            $table->text('raw_moves')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['needs_review', 'raw_moves']);
        });
    }
};
