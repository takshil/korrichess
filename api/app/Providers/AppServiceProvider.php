<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analysis\EngineClient;
use App\Domain\Analysis\HeuristicEngine;
use App\Domain\Analysis\UciEngine;
use App\Domain\Sync\ChessComClient;
use App\Domain\Sync\InMemoryPlatformClient;
use App\Domain\Sync\LichessClient;
use App\Domain\Sync\SyncService;
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

        // Sync des plateformes (issues #3 et #24) : clients HTTP réels
        // (Lichess et Chess.com) ; le mode « memory » branche des clients
        // en mémoire pour le développement sans réseau. Les tests
        // remplacent l'instance par la leur.
        $this->app->singleton(SyncService::class, static function (): SyncService {
            $clients = [];
            if ((string) config('korrichess.sync.mode', 'http') === 'memory') {
                $clients = [
                    'lichess' => new InMemoryPlatformClient(),
                    'chesscom' => new InMemoryPlatformClient(),
                ];
            } else {
                $clients = [
                    'lichess' => new LichessClient(
                        (string) config('korrichess.sync.lichess_base_url', 'https://lichess.org'),
                    ),
                    'chesscom' => new ChessComClient(
                        (string) config('korrichess.sync.chesscom_base_url', 'https://api.chess.com'),
                        (int) config('korrichess.sync.chesscom_max_archives', 24),
                    ),
                ];
            }

            return new SyncService($clients);
        });
    }

    public function boot(): void
    {
        //
    }
}
