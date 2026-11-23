<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Analysis\AnalysisReader;
use App\Domain\Analysis\AnalysisRequestOutcome;
use App\Domain\Analysis\AnalysisRequester;
use App\Domain\Analysis\EngineClient;
use App\Domain\Analysis\GameAnalyser;
use App\Domain\Analysis\GameReplayer;
use App\Domain\Analysis\HeuristicEngine;
use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;
use App\Domain\Shared\ErrorKind;
use App\Http\Resources\AnalysisResource;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\GameAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const GAME_PGN = '[Event "Test analyse"]' . "\n" . '[White "alice"]' . "\n" . '[Black "bob"]' . "\n"
        . '[UTCDate "2025.05.10"]' . "\n" . '[ECO "C50"]' . "\n\n"
        . '1. e4 e5 2. Qh5 Nc6 3. Qxf7+ Kxf7 *';

    /**
     * Inscription via l'API ; retourne le corps de la réponse.
     *
     * @return array{user_id:int,token:string}
     */
    private function registerUser(string $email = 'analyse@example.com'): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    /** Sème la partie « gaffe » : blanc donne sa dame (Qh5?? Qxf7+ Kxf7). */
    private function seedGame(int $userId): int
    {
        $repo = new GameRepository();
        $repo->upsertFromPgn($userId, 'tournoi', self::GAME_PGN, 'a1', [
            'opponent' => 'bob',
            'color' => 'white',
            'time_control' => '300',
            'opening' => 'C50',
            'tournament' => 'Test analyse',
            'played_at' => '2025-05-10T18:00:00Z',
        ]);

        return (int) Game::query()->where('user_id', $userId)->first()->id;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Moteur déterministe : les tests ne dépendent pas d'un binaire Stockfish.
        $this->app->instance(EngineClient::class, new HeuristicEngine());
    }

    private function requester(): AnalysisRequester
    {
        return $this->app->make(AnalysisRequester::class);
    }

    private function analyser(): GameAnalyser
    {
        return $this->app->make(GameAnalyser::class);
    }

    private function replayer(): GameReplayer
    {
        return $this->app->make(GameReplayer::class);
    }

    /** Résultat d'analyse tel que le client le reçoit (tableau JSON). */
    private function resultBody(int $uid, int $gameId): array
    {
        $analysis = $this->app->make(AnalysisReader::class)->latest($uid, $gameId);

        return (new AnalysisResource($analysis))->resolve(request());
    }

    /** Exécute $action et renvoie le DomainError qu'elle lève. */
    private function domainError(callable $action): DomainError
    {
        try {
            $action();
        } catch (DomainError $e) {
            return $e;
        }

        $this->fail('Un DomainError était attendu.');
    }

    // ------------------------------------------------- A1 : rejeu

    public function test_a1_rejeu_renvoie_positions_et_coups(): void
    {
        $body = $this->registerUser();
        $gameId = $this->seedGame((int) $body['user_id']);

        $r = $this->replayer()->replay((int) $body['user_id'], $gameId);

        $this->assertCount(7, $r->positions);
        $this->assertSame(['e4', 'e5', 'Qh5', 'Nc6', 'Qxf7+', 'Kxf7'], $r->movesSan);
        $this->assertSame('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', $r->positions[0]);
        $this->assertCount(6, $r->movesUci);
    }

    public function test_a1_partie_inexistante_404(): void
    {
        $body = $this->registerUser();

        $error = $this->domainError(fn () => $this->replayer()->replay((int) $body['user_id'], 999));

        $this->assertSame(ErrorKind::NotFound, $error->kind);
        $this->assertSame('partie_introuvable', $error->errorCode);
    }

    // ------------------------------------------------- A10 : demande d'analyse

    public function test_a10_la_demande_enfile_un_job_sans_doublon(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        Queue::fake();
        $r = $this->requester()->request($uid, $gameId);
        $this->assertSame(AnalysisRequestOutcome::Queued, $r->outcome);
        $this->assertSame('pending', $r->analysis->status);

        $again = $this->requester()->request($uid, $gameId);
        $this->assertSame(AnalysisRequestOutcome::AlreadyPending, $again->outcome);
        $this->assertSame($r->analysis->id, $again->analysis->id);

        Queue::assertPushed(AnalyseGameJob::class, 1);
        Queue::assertPushed(
            AnalyseGameJob::class,
            fn (AnalyseGameJob $job) => $job->gameId === $gameId && $job->userId === $uid,
        );
    }

    public function test_le_job_execute_l_analyse_jusqu_au_bout(): void
    {
        // Sans Queue::fake, le driver sync exécute le job à la réception,
        // comme le ferait le worker en production.
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $r = $this->requester()->request($uid, $gameId);
        $this->assertSame(AnalysisRequestOutcome::Queued, $r->outcome);

        $analysis = GameAnalysis::query()->where('game_id', $gameId)->first();
        $this->assertSame('done', $analysis->status);
        $this->assertSame('heuristic', $analysis->engine);
    }

    public function test_a10_re_demande_apres_analyse_renvoie_l_existante(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        Queue::fake();
        $this->requester()->request($uid, $gameId);
        Queue::assertPushed(AnalyseGameJob::class, 1);

        // Exécution du worker (A10 : asynchrone).
        $this->analyser()->analyse($uid, $gameId);

        $reRequest = $this->requester()->request($uid, $gameId);
        $this->assertSame(AnalysisRequestOutcome::AlreadyDone, $reRequest->outcome);
        $this->assertSame('done', $reRequest->analysis->status);
        Queue::assertPushed(AnalyseGameJob::class, 1);
    }

    public function test_partie_sans_coups_refusee(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];

        Game::create([
            'user_id' => $uid,
            'source' => 'manuel',
            'external_id' => 'vide',
            'pgn' => '[Event "Partie vide"]',
        ]);

        $error = $this->domainError(
            fn () => $this->analyser()->analyse($uid, (int) Game::query()->where('external_id', 'vide')->first()->id),
        );

        $this->assertSame(ErrorKind::Invalid, $error->kind);
        $this->assertSame('partie_sans_coups', $error->errorCode);
    }

    // ------------------------------------------------- A2–A7, A9 : résultats

    public function test_analyse_complete_persiste_les_evaluations(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $analysis = $this->analyser()->analyse($uid, $gameId);
        $this->assertSame('done', $analysis->status);

        $result = $this->resultBody($uid, $gameId);
        $this->assertSame('done', $result['status']);
        // A2 : une évaluation par position (départ + 6 coups).
        $this->assertCount(7, $result['moves']);
        $this->assertIsInt($result['moves'][0]['eval_cp']);
        // A3 : courbe d'évaluation.
        $this->assertCount(7, $result['eval_curve']);
        // A4 : classement de chaque coup dans une catégorie connue.
        $known = ['meilleur', 'bon', 'imprecision', 'erreur', 'gaffe'];
        $this->assertSame(
            count($result['moves']),
            count(array_filter($result['moves'], fn ($m) => in_array($m['classification'], $known, true))),
        );
        $this->assertArrayHasKey('classification_counts', $result);
        // A5 : moment décisif identifié.
        $this->assertNotNull($result['decisive_ply']);
        $this->assertGreaterThanOrEqual(0, $result['decisive_ply']);
        $this->assertLessThanOrEqual(6, $result['decisive_ply']);
        // A6 : meilleur coup et variante suggérés.
        $withBest = array_filter($result['moves'], fn ($m) => $m['best_move_san'] !== null);
        $this->assertNotEmpty($withBest);
        $this->assertNotEmpty($result['moves'][0]['variation']);
    }

    public function test_a7_les_erreurs_sont_expliquees(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $this->analyser()->analyse($uid, $gameId);
        $result = $this->resultBody($uid, $gameId);

        $explained = array_filter($result['moves'], fn ($m) => $m['explanation'] !== null);
        $this->assertNotEmpty($explained);
        $hasSuggestion = (bool) array_filter(
            $explained,
            fn ($m) => str_contains((string) $m['explanation'], 'Le moteur suggère'),
        );
        $this->assertTrue($hasSuggestion);
    }

    public function test_a9_ouverture_et_sortie_de_theorie(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $this->analyser()->analyse($uid, $gameId);
        $result = $this->resultBody($uid, $gameId);

        $this->assertSame('Partie italienne', $result['opening_name']);
        $this->assertSame(2, $result['book_exit_ply']);
    }

    // ------------------------------------------------- A8 : exploration

    public function test_a8_coup_alternatif_accepte(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $explore = $this->replayer()->explore($uid, $gameId, 0, 'd2d4');

        $this->assertSame('d4', $explore->moveSan);
        $this->assertStringStartsWith('rnbqkbnr/pppppppp/8/8/3P4/8/PPP1PPPP', $explore->fen);
    }

    public function test_a8_coups_invalides_refuses(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $illegal = $this->domainError(fn () => $this->replayer()->explore($uid, $gameId, 0, 'e2e5'));
        $this->assertSame(ErrorKind::Invalid, $illegal->kind);
        $this->assertArrayHasKey('fen', $illegal->context);

        $unknown = $this->domainError(fn () => $this->replayer()->explore($uid, 999, 0, 'e2e4'));
        $this->assertSame(ErrorKind::NotFound, $unknown->kind);
    }

    // ------------------------------------------------- Isolation

    public function test_isolation_entre_utilisateurs(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $other = $this->registerUser('autre-analyse@example.com');

        $otherId = (int) $other['user_id'];
        $reader = $this->app->make(AnalysisReader::class);

        foreach ([
            fn () => $this->replayer()->replay($otherId, $gameId),
            fn () => $reader->latest($otherId, $gameId),
            fn () => $this->replayer()->explore($otherId, $gameId, 0, 'd2d4'),
        ] as $attempt) {
            $this->assertSame(ErrorKind::NotFound, $this->domainError($attempt)->kind);
        }
    }

    // ------------------------------------------------- API HTTP

    public function test_api_rejeu_avec_et_sans_jeton(): void
    {
        $body = $this->registerUser();
        $this->seedGame((int) $body['user_id']);

        $response = $this->withToken($body['token'])->getJson('/api/v1/games/1/replay');
        $response->assertStatus(200)->assertJsonCount(7, 'positions');

        // withToken() persiste dans defaultHeaders : on les efface pour que
        // la requête suivante parte réellement sans jeton.
        $this->flushHeaders();
        $this->getJson('/api/v1/games/1/replay')->assertStatus(401);
    }

    public function test_api_demande_et_resultat_d_analyse(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        // Analyse déjà faite → la route renvoie l'existante.
        $this->analyser()->analyse($uid, $gameId);

        $ask = $this->withToken($body['token'])->postJson("/api/v1/games/{$gameId}/analyse", []);
        $ask->assertStatus(200)->assertJson(['already' => true, 'status' => 'done']);

        $result = $this->withToken($body['token'])->getJson("/api/v1/games/{$gameId}/analyse");
        $result->assertStatus(200)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('opening_name', 'Partie italienne')
            ->assertJsonPath('book_exit_ply', 2);
    }

    public function test_api_explore(): void
    {
        $body = $this->registerUser();
        $gameId = $this->seedGame((int) $body['user_id']);

        $response = $this->withToken($body['token'])->postJson("/api/v1/games/{$gameId}/explore", [
            'ply' => 0,
            'move_uci' => 'd2d4',
        ]);

        $response->assertStatus(200)->assertJsonPath('move_san', 'd4');

        // Coup mal formé rejeté par la validation.
        $this->withToken($body['token'])
            ->postJson("/api/v1/games/{$gameId}/explore", ['ply' => 0, 'move_uci' => 'zz99'])
            ->assertStatus(422);
    }
}
