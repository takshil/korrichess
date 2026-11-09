<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Chess\Board;
use App\Domain\Games\GameRepository;
use App\Domain\Library\LibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private const GAME_TOURNOI = '[Event "Open de Rennes"]' . "\n" . '[White "alice"]' . "\n" . '[Black "carol"]' . "\n"
        . '[UTCDate "2025.04.10"]' . "\n" . '[TimeControl "300"]' . "\n" . '[ECO "C50"]' . "\n\n"
        . '1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5 *';

    private const GAME_BLITZ = '[Event "Lichess blitz"]' . "\n" . '[White "dave"]' . "\n" . '[Black "alice"]' . "\n"
        . '[UTCDate "2025.04.12"]' . "\n" . '[TimeControl "60"]' . "\n" . '[ECO "B00"]' . "\n\n"
        . '1. d4 d5 2. c4 e6 *';

    private const GAME_RAPIDE = '[Event "Lichess rapid"]' . "\n" . '[White "alice"]' . "\n" . '[Black "eve"]' . "\n"
        . '[UTCDate "2025.04.15"]' . "\n" . '[TimeControl "600"]' . "\n" . '[ECO "C50"]' . "\n\n"
        . '1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5 4. b4 Bxb4 *';

    private LibraryService $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = new LibraryService();
    }

    /**
     * Inscription via l'API ; retourne le corps de la réponse.
     *
     * @return array{user_id:int,token:string}
     */
    private function registerUser(string $email = 'biblio@example.com'): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    /** Sème trois parties : tournoi (carol), blitz (dave), rapide (eve). */
    private function seedGames(int $userId): void
    {
        $repo = new GameRepository();
        $repo->upsertFromPgn($userId, 'tournoi', self::GAME_TOURNOI, 't1', [
            'opponent' => 'carol', 'color' => 'white', 'time_control' => '300',
            'opening' => 'C50', 'tournament' => 'Open de Rennes', 'played_at' => '2025-04-10T18:00:00Z',
        ]);
        $repo->upsertFromPgn($userId, 'lichess', self::GAME_BLITZ, 't2', [
            'opponent' => 'dave', 'color' => 'black', 'time_control' => '60',
            'opening' => 'B00', 'played_at' => '2025-04-12T20:00:00Z',
        ]);
        $repo->upsertFromPgn($userId, 'lichess', self::GAME_RAPIDE, 't3', [
            'opponent' => 'eve', 'color' => 'white', 'time_control' => '600',
            'opening' => 'C50', 'played_at' => '2025-04-15T21:00:00Z',
        ]);
    }

    // ------------------------------------------------- B1 : liste

    public function test_b1_liste_toutes_les_parties_avec_source(): void
    {
        $body = $this->registerUser();
        $this->seedGames($body['user_id']);

        $r = $this->library->list((int) $body['user_id']);

        $this->assertSame(200, $r['status']);
        $this->assertSame(3, $r['body']['total']);
        $sources = array_column($r['body']['games'], 'source');
        $this->assertContains('tournoi', $sources);
        $this->assertContains('lichess', $sources);
        $this->assertFalse($r['body']['games'][0]['favorite']);
    }

    // ------------------------------------------------- B2 : filtres, tri, pagination

    public function test_b2_filtres_par_colonnes(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $this->assertSame(1, $this->library->list($uid, ['opponent' => 'dave'])['body']['total']);
        $this->assertSame(2, $this->library->list($uid, ['opening' => 'C50'])['body']['total']);
        $this->assertSame(2, $this->library->list($uid, ['source' => 'lichess'])['body']['total']);
        $this->assertSame(1, $this->library->list($uid, ['time_control' => '600'])['body']['total']);

        $black = $this->library->list($uid, ['color' => 'black']);
        $this->assertSame(1, $black['body']['total']);
        $this->assertSame('dave', $black['body']['games'][0]['opponent']);
    }

    public function test_b2_filtre_de_periode(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $r = $this->library->list($uid, ['period_start' => '2025-04-11T00:00:00Z']);

        $this->assertSame(2, $r['body']['total']);
    }

    public function test_b2_tri_et_pagination(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $sorted = $this->library->list($uid, [], sort: 'played_at', order: 'asc');
        $this->assertSame('carol', $sorted['body']['games'][0]['opponent']);

        $page = $this->library->list($uid, [], limit: 2, offset: 1);
        $this->assertCount(2, $page['body']['games']);
        $this->assertSame(3, $page['body']['total']);
    }

    // ------------------------------------------------- B3 : recherche texte

    public function test_b3_recherche_texte(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $byTournament = $this->library->list($uid, ['q' => 'rennes']);
        $this->assertSame(1, $byTournament['body']['total']);
        $this->assertSame('Open de Rennes', $byTournament['body']['games'][0]['tournament']);

        $this->assertSame(1, $this->library->list($uid, ['q' => 'eve'])['body']['total']);
    }

    // ------------------------------------------------- B1 : détail

    public function test_detail_contient_le_pgn_et_les_coups(): void
    {
        $body = $this->registerUser();
        $this->seedGames((int) $body['user_id']);

        $detail = $this->library->detail(1, 1);
        $this->assertSame(200, $detail['status']);
        $this->assertStringContainsString('Open de Rennes', (string) $detail['body']['pgn']);
        $this->assertSame(['e2e4', 'e7e5', 'g1f3', 'b8c6', 'f1c4', 'f8c5'], $detail['body']['moves_uci']);

        $this->assertSame(404, $this->library->detail(1, 999)['status']);
    }

    // ------------------------------------------------- B4 : annotations

    public function test_b4_note_etiquettes_et_favori(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $ann = $this->library->annotate($uid, 1, [
            'personal_note' => 'Belle attaque du fou',
            'tags' => ['favori', 'italienne'],
            'favorite' => true,
        ]);

        $this->assertSame(200, $ann['status']);
        $this->assertSame('Belle attaque du fou', $ann['body']['personal_note']);
        $this->assertTrue($ann['body']['favorite']);
        $this->assertSame(['favori', 'italienne'], $ann['body']['tags']);

        $this->assertSame(1, $this->library->list($uid, ['favorite' => true])['body']['total']);
        $this->assertSame(1, $this->library->list($uid, ['tag' => 'italienne'])['body']['total']);
    }

    public function test_b4_refuse_les_annotations_invalides(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $this->assertSame(422, $this->library->annotate($uid, 1, ['personal_note' => str_repeat('x', 2001)])['status']);
        $this->assertSame(422, $this->library->annotate($uid, 1, ['tags' => 'pas-un-tableau'])['status']);
        $this->assertSame(422, $this->library->annotate($uid, 1, ['tags' => ['']])['status']);
        $this->assertSame(422, $this->library->annotate($uid, 1, [])['status']);
        $this->assertSame(404, $this->library->annotate($uid, 999, ['favorite' => true])['status']);
    }

    // ------------------------------------------------- B5 : export PGN

    public function test_b5_export_pgn_d_une_selection(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);
        $this->library->annotate($uid, 1, ['personal_note' => 'Belle attaque du fou']);

        $export = $this->library->exportPgn($uid, [1, 2]);

        $this->assertSame(200, $export['status']);
        $this->assertSame(2, $export['body']['count']);
        $this->assertSame(2, substr_count((string) $export['body']['pgn'], '[Event'));
        $this->assertStringContainsString('KorriNote "Belle attaque du fou"', (string) $export['body']['pgn']);

        $this->assertSame(422, $this->library->exportPgn($uid, [])['status']);
        $this->assertSame(404, $this->library->exportPgn($uid, [42])['status']);
    }

    // ------------------------------------------------- B6 : recherche par position

    public function test_b6_recherche_par_position(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $board = Board::start();
        $board->applySan('e4');
        $board->applySan('e5');
        $board->applySan('Nf3');
        $board->applySan('Nc6');
        $board->applySan('Bc4');
        $fen = $board->fen();

        $pos = $this->library->searchByPosition($uid, $fen);

        $this->assertSame(200, $pos['status']);
        $this->assertSame(2, $pos['body']['total']);
        $this->assertContains(5, array_column($pos['body']['games'], 'ply'));
    }

    public function test_b6_position_absente_et_fen_invalide(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $board = Board::start();
        $board->applySan('d4');

        // FEN valide mais jamais atteinte (suffixe pour la différencier).
        $absent = $this->library->searchByPosition($uid, $board->fen() . ' w');
        $this->assertSame(200, $absent['status']);
        $this->assertSame(0, $absent['body']['total']);

        $this->assertSame(422, $this->library->searchByPosition($uid, 'pas un fen')['status']);
    }

    // ------------------------------------------------- Isolation entre utilisateurs

    public function test_isolation_entre_utilisateurs(): void
    {
        $body = $this->registerUser();
        $uid = (int) $body['user_id'];
        $this->seedGames($uid);

        $other = $this->registerUser('autre@example.com');
        $otherId = (int) $other['user_id'];

        $this->assertSame(0, $this->library->list($otherId)['body']['total']);
        $this->assertSame(404, $this->library->detail($otherId, 1)['status']);
        $this->assertSame(404, $this->library->exportPgn($otherId, [1, 2])['status']);
    }

    // ------------------------------------------------- API HTTP

    public function test_api_get_games_avec_filtre_et_sans_jeton(): void
    {
        $body = $this->registerUser();
        $this->seedGames((int) $body['user_id']);

        $response = $this->withToken($body['token'])->getJson('/api/v1/games?opponent=dave');
        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('games.0.opponent', 'dave');

        // withToken() persiste dans defaultHeaders : on les efface pour que
        // la requête suivante parte réellement sans jeton.
        $this->flushHeaders();
        $this->getJson('/api/v1/games')->assertStatus(401);
    }

    public function test_api_get_game_detail(): void
    {
        $body = $this->registerUser();
        $this->seedGames((int) $body['user_id']);

        $response = $this->withToken($body['token'])->getJson('/api/v1/games/1');

        $response->assertStatus(200)->assertJsonStructure(['id', 'source', 'pgn', 'moves_uci']);
    }

    public function test_api_patch_game_favori(): void
    {
        $body = $this->registerUser();
        $this->seedGames((int) $body['user_id']);

        $response = $this->withToken($body['token'])->patchJson('/api/v1/games/2', ['favorite' => true]);

        $response->assertStatus(200)->assertJsonPath('favorite', true);
    }

    public function test_api_post_export(): void
    {
        $body = $this->registerUser();
        $this->seedGames((int) $body['user_id']);

        $response = $this->withToken($body['token'])->postJson('/api/v1/games/export', [
            'game_ids' => [1, 2, 3],
        ]);

        $response->assertStatus(200)->assertJsonPath('count', 3);
    }

    public function test_api_post_search_position(): void
    {
        $body = $this->registerUser();
        $this->seedGames((int) $body['user_id']);

        $board = Board::start();
        $board->applySan('e4');
        $board->applySan('e5');
        $board->applySan('Nf3');
        $board->applySan('Nc6');
        $board->applySan('Bc4');

        $response = $this->withToken($body['token'])->postJson('/api/v1/games/search-position', [
            'fen' => $board->fen(),
        ]);

        $response->assertStatus(200)->assertJsonPath('total', 2);
    }
}
