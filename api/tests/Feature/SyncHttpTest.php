<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Sync\ChessComClient;
use App\Domain\Sync\LichessClient;
use App\Domain\Sync\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Clients HTTP réels des plateformes (issue #24) : les réponses sont
 * faussées via Http::fake(), aucun appel réseau dans la suite.
 */
final class SyncHttpTest extends TestCase
{
    use RefreshDatabase;

    private const PGN_BLANC = '[Event "Lichess blitz"]' . "\n" . '[White "bob"]' . "\n" . '[Black "alice"]' . "\n"
        . '[UTCDate "2025.03.10"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 *';

    /**
     * Inscription via l'API ; retourne le corps de la réponse.
     *
     * @return array{user_id:int,token:string}
     */
    private function registerUser(): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'synchttp@example.com',
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    // ------------------------------------------------- Lichess

    public function test_lichess_importe_les_parties_ndjson_les_plus_recentes_dabord(): void
    {
        Http::fake([
            'https://lichess.test/api/games/user/*' => Http::response(
                json_encode(['id' => 'recente', 'createdAt' => 1741636800000, 'pgn' => self::PGN_BLANC]) . "\n"
                . json_encode(['id' => 'ancienne', 'createdAt' => 1741550400000, 'pgn' => self::PGN_BLANC]) . "\n",
                200,
            ),
        ]);

        $games = (new LichessClient('https://lichess.test'))
            ->fetchGames('alice', ['since' => null, 'time_controls' => [], 'period_start' => null]);

        $this->assertCount(2, $games);
        $this->assertSame('recente', $games[0]['external_id']);
        $this->assertSame('ancienne', $games[1]['external_id']);
        $this->assertSame('2025-03-10T20:00:00Z', $games[0]['played_at']);
        $this->assertSame('2025-03-09T20:00:00Z', $games[1]['played_at']);
    }

    public function test_lichess_transmet_le_curseur_en_millisecondes(): void
    {
        Http::fake([
            'https://lichess.test/api/games/user/*' => Http::response('', 200),
        ]);

        (new LichessClient('https://lichess.test'))
            ->fetchGames('alice', ['since' => '2025-03-10T00:00:00Z', 'time_controls' => [], 'period_start' => null]);

        Http::assertSent(
            fn ($request) => str_contains($request->url(), '/api/games/user/alice')
                && str_contains($request->url(), 'since=1741564800000')
                && str_contains($request->url(), 'pgnInJson=true'),
        );
    }

    public function test_lichess_renvoie_rien_pour_un_pseudo_inconnu(): void
    {
        Http::fake([
            'https://lichess.test/api/games/user/*' => Http::response('', 404),
        ]);

        $games = (new LichessClient('https://lichess.test'))
            ->fetchGames('inconnu', ['since' => null, 'time_controls' => [], 'period_start' => null]);

        $this->assertSame([], $games);
    }

    public function test_lichess_user_exists(): void
    {
        Http::fake([
            'https://lichess.test/api/user/alice' => Http::response(['id' => 'alice'], 200),
            'https://lichess.test/api/user/ghost' => Http::response([], 404),
        ]);
        $client = new LichessClient('https://lichess.test');

        $this->assertTrue($client->userExists('alice'));
        $this->assertFalse($client->userExists('ghost'));
    }

    // ------------------------------------------------- Chess.com

    public function test_chesscom_importe_les_parties_des_archives_mensuelles(): void
    {
        Http::fake([
            'https://api.chess.test/pub/player/alice/games/archives' => Http::response([
                'archives' => [
                    'https://api.chess.test/pub/player/alice/games/2025/02',
                    'https://api.chess.test/pub/player/alice/games/2025/03',
                ],
            ]),
            'https://api.chess.test/pub/player/alice/games/2025/02' => Http::response([
                'games' => [
                    ['url' => 'https://www.chess.com/game/live/111', 'end_time' => 1740225600, 'pgn' => self::PGN_BLANC],
                ],
            ]),
            'https://api.chess.test/pub/player/alice/games/2025/03' => Http::response([
                'games' => [
                    ['url' => 'https://www.chess.com/game/live/222', 'endTime' => '2025-03-10T20:00:00.000+00:00', 'pgn' => self::PGN_BLANC],
                ],
            ]),
        ]);

        $games = (new ChessComClient('https://api.chess.test'))
            ->fetchGames('alice', ['since' => null, 'time_controls' => [], 'period_start' => null]);

        $this->assertCount(2, $games);
        $this->assertSame('chesscom-222', $games[0]['external_id']);
        $this->assertSame('2025-03-10T20:00:00Z', $games[0]['played_at']);
        $this->assertSame('chesscom-111', $games[1]['external_id']);
        $this->assertSame('2025-02-22T12:00:00Z', $games[1]['played_at']);
    }

    public function test_chesscom_ne_requete_que_les_archives_posterieures_au_curseur(): void
    {
        Http::fake([
            'https://api.chess.test/pub/player/alice/games/archives' => Http::response([
                'archives' => [
                    'https://api.chess.test/pub/player/alice/games/2024/12',
                    'https://api.chess.test/pub/player/alice/games/2025/02',
                    'https://api.chess.test/pub/player/alice/games/2025/03',
                ],
            ]),
            'https://api.chess.test/pub/player/alice/games/*' => Http::response(['games' => []]),
        ]);

        (new ChessComClient('https://api.chess.test'))
            ->fetchGames('alice', ['since' => '2025-02-01T00:00:00Z', 'time_controls' => [], 'period_start' => null]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/games/2024/12'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/games/2025/02'));
    }

    public function test_chesscom_user_exists(): void
    {
        Http::fake([
            'https://api.chess.test/pub/player/alice' => Http::response(['username' => 'alice'], 200),
            'https://api.chess.test/pub/player/ghost' => Http::response([], 404),
        ]);
        $client = new ChessComClient('https://api.chess.test');

        $this->assertTrue($client->userExists('alice'));
        $this->assertFalse($client->userExists('ghost'));
    }

    public function test_chesscom_renvoie_rien_pour_un_pseudo_inconnu(): void
    {
        Http::fake([
            'https://api.chess.test/pub/player/ghost/games/archives' => Http::response([], 404),
        ]);

        $games = (new ChessComClient('https://api.chess.test'))
            ->fetchGames('ghost', ['since' => null, 'time_controls' => [], 'period_start' => null]);

        $this->assertSame([], $games);
    }

    public function test_chesscom_envoie_un_user_agent_descriptif(): void
    {
        Http::fake([
            'https://api.chess.test/pub/player/alice/games/archives' => Http::response(['archives' => []]),
        ]);

        (new ChessComClient('https://api.chess.test'))
            ->fetchGames('alice', ['since' => null, 'time_controls' => [], 'period_start' => null]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/pub/player/alice/games/archives')
                && str_contains(implode(',', (array) $request->header('User-Agent')), 'korrichess');
        });
    }

    // ------------------------------------------------- Câblage conteneur

    public function test_le_conteneur_fournit_les_deux_plateformes_en_mode_memory(): void
    {
        $this->registerUser();
        config(['korrichess.sync.mode' => 'memory']);
        $service = $this->app->make(SyncService::class);

        $report = $service->sync(1, 'lichess', 'alice');

        $this->assertSame(0, $report->imported);
    }

    public function test_le_conteneur_utilise_le_client_http_en_mode_http(): void
    {
        $this->registerUser();
        config(['korrichess.sync.mode' => 'http']);
        config(['korrichess.sync.lichess_base_url' => 'https://lichess.test']);
        Http::fake([
            'https://lichess.test/api/user/*' => Http::response(['id' => 'alice'], 200),
        ]);
        $service = $this->app->make(SyncService::class);

        $this->assertSame('connu', $service->verifyUsername('lichess', 'alice'));
    }
}
