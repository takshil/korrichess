<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/**
 * Interface du moteur d'analyse (ADR-002).
 * UciEngine appelle un binaire UCI (Stockfish) en processus externe ;
 * HeuristicEngine fournit des évaluations déterministes pour les tests.
 */
interface EngineClient
{
    /**
     * Analyse une position et retourne l'évaluation du point de vue des blancs
     * (centipions), le meilleur coup UCI et une variante proposée.
     *
     * @return array{eval_cp:int,best_move:?string,variation:array<int,string>}
     */
    public function evaluate(string $fen, int $depth = 12): array;

    public function name(): string;
}
