<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Analysis\AnalysisService;
use App\Domain\Analysis\HeuristicEngine;
use App\Domain\Games\GameRepository;
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

    private function service(): AnalysisService
    {
        return new AnalysisService(new HeuristicEngine());
    }

    // ------------------------------------------------- A1 : rejeu

    public function test_a1_rejeu_renvoie_positions_et_coups(): void
    {
        $body = $this->registerUser();
        $gameId = $this->seedGame((int) $body['user_id']);

        $r = $this->service()->replay((int) $body['user_id'], $gameId);

        $this->assertSame(200, $r['status']);
        $this->assertCount(7, $r['body']['positions']);
        $this->assertSame(['e4', 'e5', 'Qh5', 'Nc6', 'Qxf7+', 'Kxf7'], $r['body']['moves_san']);
        $this->assertSame('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', $r['body']['positions'][0]);
        $this->assertSame(6, count($r['body']['moves_uci']));
    }

    public function test_a1_partie_inexistante_404(): void
    {
        $body = $this->registerUser();

        $this->assertSame(404, $this->service()->replay((int) $body['user_id'], 999)['status']);
    }

    // ------------------------------------------------- A10 : demande d'analyse

    public function test_a10_la_demande_enfile_un_job_sans_doublon(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        Queue::fake();
        $r = $this->service()->request($uid, $gameId);
        $this->assertSame(202, $r['status']);
        $this->assertSame('pending', $r['body']['status']);

        $again = $this->service()->request($uid, $gameId);
        $this->assertSame('pending', $again['body']['status']);
        $this->assertSame($r['body']['analysis_id'], $again['body']['analysis_id']);

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

        $r = $this->service()->request($uid, $gameId);
        $this->assertSame(202, $r['status']);

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
        $this->service()->request($uid, $gameId);
        Queue::assertPushed(AnalyseGameJob::class, 1);

        // Exécution du worker (A10 : asynchrone).
        $this->service()->analyse($uid, $gameId);

        $reRequest = $this->service()->request($uid, $gameId);
        $this->assertSame(200, $reRequest['status']);
        $this->assertTrue($reRequest['body']['already']);
        $this->assertSame('done', $reRequest['body']['status']);
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

        $r = $this->service()->analyse($uid, (int) Game::query()->where('external_id', 'vide')->first()->id);
        $this->assertSame(422, $r['status']);
        $this->assertSame('partie_sans_coups', $r['body']['error']);
    }

    // ------------------------------------------------- A2–A7, A9 : résultats

    public function test_analyse_complete_persiste_les_evaluations(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $r = $this->service()->analyse($uid, $gameId);
        $this->assertSame(200, $r['status']);
        $this->assertSame('done', $r['body']['status']);

        $result = $this->service()->result($uid, $gameId);
        $this->assertSame(200, $result['status']);
        $this->assertSame('done', $result['body']['status']);
        // A2 : une évaluation par position (départ + 6 coups).
        $this->assertCount(7, $result['body']['moves']);
        $this->assertIsInt($result['body']['moves'][0]['eval_cp']);
        // A3 : courbe d'évaluation.
        $this->assertCount(7, $result['body']['eval_curve']);
        // A4 : classement de chaque coup dans une catégorie connue.
        $known = ['meilleur', 'bon', 'imprecision', 'erreur', 'gaffe'];
        $this->assertSame(
            count($result['body']['moves']),
            count(array_filter($result['body']['moves'], fn ($m) => in_array($m['classification'], $known, true))),
        );
        $this->assertArrayHasKey('classification_counts', $result['body']);
        // A5 : moment décisif identifié.
        $this->assertNotNull($result['body']['decisive_ply']);
        $this->assertGreaterThanOrEqual(0, $result['body']['decisive_ply']);
        $this->assertLessThanOrEqual(6, $result['body']['decisive_ply']);
        // A6 : meilleur coup et variante suggérés.
        $withBest = array_filter($result['body']['moves'], fn ($m) => $m['best_move_san'] !== null);
        $this->assertNotEmpty($withBest);
        $this->assertNotEmpty($result['body']['moves'][0]['variation']);
    }

    public function test_a7_les_erreurs_sont_expliquees(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $this->service()->analyse($uid, $gameId);
        $result = $this->service()->result($uid, $gameId);

        $explained = array_filter($result['body']['moves'], fn ($m) => $m['explanation'] !== null);
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

        $this->service()->analyse($uid, $gameId);
        $result = $this->service()->result($uid, $gameId);

        $this->assertSame('Partie italienne', $result['body']['opening_name']);
        $this->assertSame(2, $result['body']['book_exit_ply']);
    }

    // ------------------------------------------------- A8 : exploration

    public function test_a8_coup_alternatif_accepte(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $explore = $this->service()->explore($uid, $gameId, 0, 'd2d4');

        $this->assertSame(200, $explore['status']);
        $this->assertSame('d4', $explore['body']['move_san']);
        $this->assertStringStartsWith('rnbqkbnr/pppppppp/8/8/3P4/8/PPP1PPPP', (string) $explore['body']['fen']);
    }

    public function test_a8_coups_invalides_refuses(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $this->assertSame(422, $this->service()->explore($uid, $gameId, 0, 'e2e5')['status']);
        $this->assertSame(404, $this->service()->explore($uid, 999, 0, 'e2e4')['status']);
    }

    // ------------------------------------------------- Isolation

    public function test_isolation_entre_utilisateurs(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $gameId = $this->seedGame($uid);

        $other = $this->registerUser('autre-analyse@example.com');

        $this->assertSame(404, $this->service()->replay((int) $other['user_id'], $gameId)['status']);
        $this->assertSame(404, $this->service()->result((int) $other['user_id'], $gameId)['status']);
        $this->assertSame(404, $this->service()->explore((int) $other['user_id'], $gameId, 0, 'd2d4')['status']);
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
        $this->service()->analyse($uid, $gameId);

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
