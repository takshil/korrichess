<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\DomainError;
use App\Domain\Shared\ErrorKind;
use App\Domain\Sync\InMemoryPlatformClient;
use App\Domain\Sync\PlatformRegistry;
use App\Domain\Sync\RateLimiter;
use App\Domain\Sync\SyncScheduler;
use App\Domain\Sync\SyncService;
use App\Domain\Sync\SyncStateRepository;
use App\Jobs\SyncPlatformJob;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SyncTest extends TestCase
{
    use RefreshDatabase;

    private const GAME_BLANC = '[Event "Lichess blitz"]' . "\n" . '[White "bob"]' . "\n" . '[Black "alice"]' . "\n"
        . '[UTCDate "2025.03.10"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 *';

    private const GAME_NOIR = '[Event "Lichess blitz"]' . "\n" . '[White "alice"]' . "\n" . '[Black "bob"]' . "\n"
        . '[UTCDate "2025.03.12"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. d4 d5 2. c4 e6 *';

    private const GAME_RAPIDE = '[Event "Lichess rapid"]' . "\n" . '[TimeControl "600"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 *';

    /**
     * Inscription via l'API ; retourne le corps de la réponse.
     *
     * @return array{user_id:int,token:string}
     */
    private function registerUser(): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'sync@example.com',
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    /**
     * Service de sync avec un client Lichess en mémoire, enregistré dans le
     * conteneur pour que la route /sync l'utilise aussi.
     */
    private function syncServiceWith(InMemoryPlatformClient $client): SyncService
    {
        $this->app->instance(PlatformRegistry::class, new PlatformRegistry(['lichess' => $client]));

        return $this->app->make(SyncService::class);
    }

    /** @return array<int,array{external_id:string,pgn:string,played_at:string}> */
    private function initialGames(): array
    {
        return [
            ['external_id' => 'g1', 'pgn' => self::GAME_BLANC, 'played_at' => '2025-03-10T20:00:00Z'],
            ['external_id' => 'g2', 'pgn' => self::GAME_NOIR, 'played_at' => '2025-03-12T21:00:00Z'],
            ['external_id' => 'g3', 'pgn' => self::GAME_RAPIDE, 'played_at' => '2025-03-13T22:00:00Z'],
        ];
    }

    // ------------------------------------------------- Import (L1/L2)

    public function test_premiere_sync_importe_toutes_les_parties(): void
    {
        $this->registerUser();
        $service = $this->syncServiceWith(new InMemoryPlatformClient($this->initialGames()));

        $report = $service->sync(1, 'lichess', 'alice');

        $this->assertSame(3, $report->imported);
        $this->assertSame(0, $report->duplicates);
        $this->assertSame(3, Game::query()->count());
    }

    public function test_metadonnees_extraites_du_pgn(): void
    {
        $this->registerUser();
        $service = $this->syncServiceWith(new InMemoryPlatformClient($this->initialGames()));
        $service->sync(1, 'lichess', 'alice');

        // alice joue les noirs dans g1 : l'adversaire est bob.
        $this->assertDatabaseHas('games', [
            'external_id' => 'g1',
            'source' => 'lichess',
            'opponent' => 'bob',
            'color' => 'black',
            'played_at' => '2025-03-10T20:00:00Z',
            'time_control' => '300',
        ]);
        // alice joue les blancs dans g2.
        $this->assertDatabaseHas('games', [
            'external_id' => 'g2',
            'opponent' => 'bob',
            'color' => 'white',
        ]);
        // Les coups sont stockés au format UCI.
        $this->assertDatabaseHas('games', [
            'external_id' => 'g1',
            'moves_uci' => 'e2e4 e7e5 g1f3 b8c6',
        ]);
    }

    // ------------------------------------------------- Incrémental (L3/L4)

    public function test_sync_incrementale_ne_reimporte_rien(): void
    {
        $this->registerUser();
        $client = new InMemoryPlatformClient($this->initialGames());
        $service = $this->syncServiceWith($client);

        $service->sync(1, 'lichess', 'alice');
        $r2 = $service->sync(1, 'lichess', 'alice');

        $this->assertSame(0, $r2->imported);
        $this->assertSame(3, Game::query()->count());
    }

    public function test_le_curseur_est_memorise_et_avance(): void
    {
        $this->registerUser();
        $client = new InMemoryPlatformClient($this->initialGames());
        $service = $this->syncServiceWith($client);
        $states = $this->app->make(SyncStateRepository::class);

        $service->sync(1, 'lichess', 'alice');
        $this->assertSame('2025-03-13T22:00:00Z', $states->cursor(1, 'lichess', 'alice')['last_game_at']);

        $newGame = '[Event "Lichess blitz"]' . "\n" . '[TimeControl "300"]' . "\n\n" . '1. f4 e5 2. g4 Qh4# 0-1';
        $client->games[] = ['external_id' => 'g4', 'pgn' => $newGame, 'played_at' => '2025-03-20T10:00:00Z'];
        $r = $service->sync(1, 'lichess', 'alice');

        $this->assertSame(1, $r->imported);
        $this->assertSame('2025-03-20T10:00:00Z', $states->cursor(1, 'lichess', 'alice')['last_game_at']);
    }

    // ------------------------------------------------- Doublons (L6)

    public function test_les_doublons_sont_ignores(): void
    {
        $this->registerUser();
        $client = new InMemoryPlatformClient($this->initialGames());
        $service = $this->syncServiceWith($client);

        $service->sync(1, 'lichess', 'alice');

        // Curseur réinitialisé (sync complète redemandée) : toutes les parties
        // reviennent de la plateforme, mais aucune n'est réinsérée (L6).
        DB::table('sync_state')->delete();
        $r = $service->sync(1, 'lichess', 'alice');

        $this->assertSame(0, $r->imported);
        $this->assertSame(3, $r->duplicates);
        $this->assertSame(3, Game::query()->count());
    }

    // ------------------------------------------------- Filtres (L5)

    public function test_filtre_de_cadence_nimporte_que_les_correspondantes(): void
    {
        $this->registerUser();
        $client = new InMemoryPlatformClient($this->initialGames());
        $service = $this->syncServiceWith($client);

        $service->sync(1, 'lichess', 'alice');

        // Rien de nouveau en cadence 600.
        $r = $service->sync(1, 'lichess', 'alice', ['time_controls' => ['600']]);
        $this->assertSame(0, $r->imported);

        // Une nouvelle rapide (600) est importée, les blitz (300) ne le sont pas.
        $client->games[] = ['external_id' => 'g5', 'pgn' => self::GAME_RAPIDE, 'played_at' => '2025-03-25T10:00:00Z'];
        $client->games[] = ['external_id' => 'g6', 'pgn' => self::GAME_BLANC, 'played_at' => '2025-03-26T10:00:00Z'];
        $r2 = $service->sync(1, 'lichess', 'alice', ['time_controls' => ['600']]);

        $this->assertSame(1, $r2->imported);
        $this->assertDatabaseHas('games', ['external_id' => 'g5']);
        $this->assertDatabaseMissing('games', ['external_id' => 'g6']);
    }

    public function test_filtre_de_periode_refuse_les_parties_trop_anciennes(): void
    {
        $this->registerUser();
        $service = $this->syncServiceWith(new InMemoryPlatformClient($this->initialGames()));

        $r = $service->sync(1, 'lichess', 'alice', ['period_start' => '2025-03-12T00:00:00Z']);

        $this->assertSame(2, $r->imported);
        $this->assertDatabaseMissing('games', ['external_id' => 'g1']);
    }

    // ------------------------------------------------- Robustesse (L8)

    public function test_plateforme_inconnue_est_refusee(): void
    {
        $this->registerUser();
        $service = $this->syncServiceWith(new InMemoryPlatformClient());

        try {
            $service->sync(1, 'foo', 'alice');
            $this->fail('Un DomainError était attendu.');
        } catch (DomainError $e) {
            $this->assertSame(ErrorKind::Invalid, $e->kind);
            $this->assertSame('plateforme_inconnue', $e->errorCode);
        }
    }

    public function test_pgn_invalide_est_ignore_sans_crash(): void
    {
        $this->registerUser();
        $client = new InMemoryPlatformClient($this->initialGames());
        $service = $this->syncServiceWith($client);

        $service->sync(1, 'lichess', 'alice');
        $client->games[] = ['external_id' => 'g7', 'pgn' => 'pas un pgn', 'played_at' => '2025-03-27T10:00:00Z'];

        $r = $service->sync(1, 'lichess', 'alice');

        $this->assertSame(0, $r->imported);
        $this->assertDatabaseMissing('games', ['external_id' => 'g7']);
    }

    // ------------------------------------------------- Sync auto (L7)

    public function test_une_sync_automatique_est_placee_en_file(): void
    {
        $this->registerUser();
        $scheduler = $this->app->make(SyncScheduler::class);

        Queue::fake();
        $scheduler->schedule(1, 'lichess', 'alice');

        Queue::assertPushed(SyncPlatformJob::class, 1);
        Queue::assertPushed(
            SyncPlatformJob::class,
            fn (SyncPlatformJob $job) => $job->userId === 1 && $job->platform === 'lichess' && $job->username === 'alice',
        );
    }

    public function test_le_rate_limiter_laisse_passer_les_appels_sous_quota(): void
    {
        $limiter = new RateLimiter(3);
        $limiter->wait('lichess');
        $limiter->wait('lichess');
        $limiter->wait('lichess');

        $this->addToAssertionCount(1); // aucun blocage ni exception sous le quota.
    }

    // ------------------------------------------------- API HTTP

    public function test_sync_via_api_enfile_un_job_et_repond_202(): void
    {
        $body = $this->registerUser();
        $this->syncServiceWith(new InMemoryPlatformClient($this->initialGames()));
        Queue::fake();

        $response = $this->withToken($body['token'])->postJson('/api/v1/sync', [
            'platform' => 'lichess',
            'username' => 'alice',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('statut', 'en_file')
            ->assertJsonPath('platform', 'lichess')
            ->assertJsonPath('username', 'alice');

        Queue::assertPushed(SyncPlatformJob::class, 1);
        Queue::assertPushed(
            SyncPlatformJob::class,
            fn (SyncPlatformJob $job) => $job->userId === 1 && $job->platform === 'lichess' && $job->username === 'alice',
        );
    }

    public function test_statut_de_sync_expose_les_curseurs(): void
    {
        $body = $this->registerUser();
        $service = $this->syncServiceWith(new InMemoryPlatformClient($this->initialGames()));
        $service->sync(1, 'lichess', 'alice');

        $response = $this->withToken($body['token'])->getJson('/api/v1/sync/statut');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'synchronisations')
            ->assertJsonPath('synchronisations.0.platform', 'lichess')
            ->assertJsonPath('synchronisations.0.last_game_at', '2025-03-13T22:00:00Z');
    }

    public function test_sync_all_enfile_une_sync_par_compte_lie(): void
    {
        $this->registerUser();
        DB::table('linked_accounts')->insert([
            ['user_id' => 1, 'platform' => 'lichess', 'username' => 'alice', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 1, 'platform' => 'chesscom', 'username' => 'alice', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Queue::fake();
        $this->artisan('korrichess:sync-all')->assertSuccessful();

        Queue::assertPushed(SyncPlatformJob::class, 2);
    }

    public function test_sync_refusee_sans_jeton(): void
    {
        $this->postJson('/api/v1/sync', ['platform' => 'lichess', 'username' => 'alice'])
            ->assertStatus(401);
    }

    public function test_sync_valide_les_parametres(): void
    {
        $body = $this->registerUser();
        $this->syncServiceWith(new InMemoryPlatformClient());

        $this->withToken($body['token'])
            ->postJson('/api/v1/sync', ['platform' => 'lichess'])
            ->assertStatus(422);
    }

    public function test_sync_api_plateforme_inconnue(): void
    {
        $body = $this->registerUser();
        $this->syncServiceWith(new InMemoryPlatformClient());

        $this->withToken($body['token'])
            ->postJson('/api/v1/sync', ['platform' => 'foo', 'username' => 'alice'])
            ->assertStatus(422)
            ->assertJson(['error' => 'plateforme_inconnue']);
    }

    public function test_lier_un_compte_declenche_la_premiere_sync(): void
    {
        $body = $this->registerUser();
        Queue::fake();

        $this->withToken($body['token'])->postJson('/api/v1/me/linked-accounts', [
            'platform' => 'lichess',
            'username' => 'alice',
        ])->assertStatus(201)->assertJson(['message' => 'compte_lie']);

        Queue::assertPushed(SyncPlatformJob::class, 1);
        Queue::assertPushed(
            SyncPlatformJob::class,
            fn (SyncPlatformJob $job) => $job->userId === 1 && $job->platform === 'lichess' && $job->username === 'alice',
        );
    }
}
