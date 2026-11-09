<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analysis\EngineClient;
use App\Domain\Analysis\HeuristicEngine;
use App\Domain\Analysis\UciEngine;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Moteur d'analyse (ADR-002) : Stockfish UCI externe si le binaire
        // configuré est disponible, sinon moteur heuristique déterministe
        // (tests, environnements sans moteur). Le binaire n'est jamais
        // embarqué ni distribué par le dépôt.
        $this->app->singleton(EngineClient::class, static function (): EngineClient {
            $path = (string) config('korrichess.analysis.stockfish_path', 'stockfish');
            try {
                return new UciEngine(
                    $path,
                    (int) config('korrichess.analysis.engine_movetime_ms', 1000),
                );
            } catch (\InvalidArgumentException) {
                return new HeuristicEngine();
            }
        });
    }

    public function boot(): void
    {
        //
    }
}
