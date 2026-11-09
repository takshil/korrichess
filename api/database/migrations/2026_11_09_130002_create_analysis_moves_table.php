<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Évaluation par position d'une analyse (A2–A7) : FEN, coup joué (UCI/SAN),
     * évaluation en centipions, meilleur coup, variante, classement (A4) et
     * explication (A7).
     */
    public function up(): void
    {
        Schema::create('analysis_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_id')->constrained('game_analyses')->cascadeOnDelete();
            $table->unsignedSmallInteger('ply');
            $table->string('fen', 96);
            $table->string('move_uci', 8)->default('');
            $table->string('move_san', 16)->default('');
            $table->integer('eval_cp');
            $table->string('best_move_uci', 8)->nullable();
            $table->string('best_move_san', 16)->nullable();
            $table->text('variation_uci')->nullable();
            $table->string('classification', 20);
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->unique(['analysis_id', 'ply']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_moves');
    }
};
