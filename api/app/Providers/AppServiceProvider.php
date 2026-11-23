<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analysis\EngineClient;
use App\Domain\Analysis\HeuristicEngine;
use App\Domain\Analysis\UciEngine;
use App\Domain\Sync\ChessComClient;
use App\Domain\Sync\InMemoryPlatformClient;
use App\Domain\Sync\LichessClient;
use App\Domain\Sync\PlatformRegistry;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

/**
 * Seul endroit où l'on choisit les implémentations concrètes des
 * abstractions du domaine (moteur d'analyse, clients des plateformes) ;
 * tout le reste est câblé par l'autowiring du conteneur.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EngineClient::class, $this->makeEngine(...));
        $this->app->singleton(PlatformRegistry::class, $this->makePlatformRegistry(...));
    }

    public function boot(): void
    {
        // Les réponses JSON de l'API ne sont pas enveloppées dans { "data": … } :
        // le contrat v1 expose directement les objets.
        JsonResource::withoutWrapping();
    }

    /**
     * Moteur d'analyse (ADR-002) : Stockfish UCI externe si le binaire
     * configuré est disponible, sinon moteur heuristique déterministe
     * (tests, environnements sans moteur). Le binaire n'est jamais
     * embarqué ni distribué par le dépôt.
     */
    private function makeEngine(): EngineClient
    {
        try {
            return new UciEngine(
                (string) config('korrichess.analysis.stockfish_path', 'stockfish'),
                (int) config('korrichess.analysis.engine_movetime_ms', 1000),
            );
        } catch (\InvalidArgumentException) {
            return new HeuristicEngine();
        }
    }

    /**
     * Clients des plateformes (issues #3 et #24) : clients HTTP réels
     * (Lichess et Chess.com) ; le mode « memory » branche des clients en
     * mémoire pour le développement sans réseau. Les tests remplacent
     * l'instance du registre par la leur.
     */
    private function makePlatformRegistry(): PlatformRegistry
    {
        if ((string) config('korrichess.sync.mode', 'http') === 'memory') {
            return new PlatformRegistry([
                'lichess' => new InMemoryPlatformClient(),
                'chesscom' => new InMemoryPlatformClient(),
            ]);
        }

        return new PlatformRegistry([
            'lichess' => new LichessClient(
                (string) config('korrichess.sync.lichess_base_url', 'https://lichess.org'),
            ),
            'chesscom' => new ChessComClient(
                (string) config('korrichess.sync.chesscom_base_url', 'https://api.chess.com'),
                (int) config('korrichess.sync.chesscom_max_archives', 24),
            ),
        ]);
    }
}
