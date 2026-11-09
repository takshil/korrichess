<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Games\GameRepository;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saisie manuelle des parties de tournoi (S5 + S7, issue #25) :
 * prévisualisation des coups, enregistrement avec ou sans validation,
 * fiche de la partie et correction a posteriori.
 */
final class ManualEntryTest extends TestCase
{
    use RefreshDatabase;

    private const FICHE = [
        'played_at' => '2026-11-09',
        'opponent' => 'carol',
        'color' => 'white',
        'result' => '1-0',
    ];

    /**
     * Inscription via l'API ; retourne le corps de la réponse.
     *
     * @return array{user_id:int,token:string}
     */
    private function registerUser(string $email = 'saisie@example.com'): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    /**
     * @return array<int,string>
     */
    private function coupsValides(): array
    {
        return ['e2e4', 'e7e5', 'g1f3', 'b8c6'];
    }

    /**
     * Enregistre une saisie via l'API ; retourne le corps de la réponse.
     *
     * @param array<string,mixed> $surcharge
     * @return array<string,mixed>
     */
    private function saisir(array $body, array $surcharge = []): array
    {
        $response = $this->withToken($body['token'])
            ->postJson('/api/v1/games/manual', $surcharge + ['moves' => $this->coupsValides(), 'validate_moves' => true] + self::FICHE);

        $response->assertStatus(201);

        return $response->json();
    }

    // ------------------------------------------------- S5 : prévisualisation

    public function test_preview_rejoue_san_et_uci_melanges(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])
            ->postJson('/api/v1/games/manual/preview', ['moves' => ['e2e4', 'e7e5', 'Nf3']]);

        $r->assertStatus(200);
        $this->assertSame(['e4', 'e5', 'Nf3'], $r->json('moves_san'));
        $this->assertSame(['e2e4', 'e7e5', 'g1f3'], $r->json('moves_uci'));
        $this->assertNull($r->json('invalid_ply'));
        $this->assertSame('black', $r->json('turn'));
        $this->assertContains('b8c6', $r->json('legal_uci'));
        $this->assertStringContainsString(' b ', $r->json('fen')); // trait aux noirs
    }

    public function test_preview_part_de_la_position_initiale(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])
            ->postJson('/api/v1/games/manual/preview', ['moves' => []]);

        $r->assertStatus(200);
        $this->assertSame('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', $r->json('fen'));
        $this->assertSame([], $r->json('moves_san'));
        $this->assertContains('e2e4', $r->json('legal_uci'));
        $this->assertSame('white', $r->json('turn'));
    }

    public function test_preview_signale_le_premier_coup_impossible(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])
            ->postJson('/api/v1/games/manual/preview', ['moves' => ['e2e4', 'e7e5', 'Ke3']]);

        $r->assertStatus(200);
        $this->assertSame(2, $r->json('invalid_ply'));
        $this->assertSame('Ke3', $r->json('invalid_move'));
        $this->assertSame(['e4', 'e5'], $r->json('moves_san'));
        $this->assertStringContainsString(' w ', $r->json('fen')); // position avant le coup fautif : aux blancs
    }

    // ------------------------------------------------- S5 : enregistrement vérifié

    public function test_store_enregistre_une_saisie_verifiee(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])->postJson('/api/v1/games/manual', [
            'moves' => $this->coupsValides(),
            'validate_moves' => true,
            'opponent_rating' => 1450,
            'time_control' => '300',
            'tournament' => 'Open de Vannes',
            'round' => '3',
        ] + self::FICHE);

        $r->assertStatus(201);
        $this->assertSame('carol', $r->json('opponent'));
        $this->assertSame(1450, $r->json('opponent_rating'));
        $this->assertSame('manual', $r->json('source'));
        $this->assertFalse($r->json('needs_review'));
        $this->assertSame(['e2e4', 'e7e5', 'g1f3', 'b8c6'], $r->json('moves_uci'));
        $this->assertNull($r->json('raw_moves'));

        $this->assertDatabaseHas('games', [
            'user_id' => $body['user_id'],
            'source' => 'manual',
            'opponent' => 'carol',
            'tournament' => 'Open de Vannes',
            'needs_review' => false,
        ]);
        $this->assertStringContainsString('1. e4 e5 2. Nf3 Nc6', (string) $r->json('pgn'));
        $this->assertStringContainsString('[Event "Open de Vannes"]', (string) $r->json('pgn'));
        $this->assertStringContainsString('[White "saisie"]', (string) $r->json('pgn'));
        $this->assertStringContainsString('[Black "carol"]', (string) $r->json('pgn'));
    }

    public function test_store_refuse_un_coup_impossible_quand_la_validation_est_active(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])->postJson('/api/v1/games/manual', [
            'moves' => ['e2e4', 'e2e5'], // e2 est vide : impossible
            'validate_moves' => true,
        ] + self::FICHE);

        $r->assertStatus(422);
        $this->assertSame('coup_invalide', $r->json('error'));
        $this->assertSame(1, $r->json('ply'));
        $this->assertDatabaseCount('games', 0);
    }

    public function test_store_sans_validation_garde_la_saisie_telle_quelle(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])->postJson('/api/v1/games/manual', [
            'moves' => ['e2e4', 'e7e5', 'Ke3', 'Qd8'], // Ke3 : roi e1 -> e3, impossible
            'validate_moves' => false,
        ] + self::FICHE);

        $r->assertStatus(201);
        $this->assertTrue($r->json('needs_review'));
        $this->assertSame(['e2e4', 'e7e5'], $r->json('moves_uci'));
        $this->assertSame(['e2e4', 'e7e5', 'Ke3', 'Qd8'], $r->json('raw_moves'));

        // Le rejeu s'arrête au premier coup douteux.
        $rejeu = $this->withToken($body['token'])->getJson('/api/v1/games/' . $r->json('id') . '/replay');
        $rejeu->assertStatus(200);
        $this->assertCount(3, $rejeu->json('positions'));
        $this->assertSame(['e4', 'e5'], $rejeu->json('moves_san'));

        // La bibliothèque signale la partie à vérifier.
        $liste = $this->withToken($body['token'])->getJson('/api/v1/games');
        $this->assertTrue($liste->json('games.0.needs_review'));
    }

    public function test_store_sans_validation_tout_valide_n_est_pas_marquee(): void
    {
        $body = $this->registerUser();

        $r = $this->withToken($body['token'])->postJson('/api/v1/games/manual', [
            'moves' => $this->coupsValides(),
            'validate_moves' => false,
        ] + self::FICHE);

        $r->assertStatus(201);
        $this->assertFalse($r->json('needs_review'));
        $this->assertNull($r->json('raw_moves'));
    }

    public function test_store_rejette_une_fiche_incomplete(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];
        $base = ['moves' => $this->coupsValides(), 'validate_moves' => true] + self::FICHE;

        $sansAdversaire = $base;
        unset($sansAdversaire['opponent']);
        $this->withToken($token)->postJson('/api/v1/games/manual', $sansAdversaire)->assertStatus(422);

        $sansCoups = $base;
        unset($sansCoups['moves']);
        $this->withToken($token)->postJson('/api/v1/games/manual', $sansCoups)->assertStatus(422);

        $sansDate = $base;
        unset($sansDate['played_at']);
        $this->withToken($token)->postJson('/api/v1/games/manual', $sansDate)->assertStatus(422);

        $couleurInvalide = $base;
        $couleurInvalide['color'] = 'rouge';
        $this->withToken($token)->postJson('/api/v1/games/manual', $couleurInvalide)->assertStatus(422);

        $resultatInvalide = $base;
        $resultatInvalide['result'] = 'victoire';
        $this->withToken($token)->postJson('/api/v1/games/manual', $resultatInvalide)->assertStatus(422);

        $this->assertDatabaseCount('games', 0);
    }

    public function test_store_sans_jeton_refuse(): void
    {
        $this->postJson('/api/v1/games/manual', ['moves' => ['e2e4']] + self::FICHE)->assertStatus(401);
    }

    // ------------------------------------------------- S7 : fiche, détail, correction

    public function test_detail_expose_les_coups_bruts(): void
    {
        $body = $this->registerUser();
        $partie = $this->saisir($body, ['validate_moves' => false, 'moves' => ['e2e4', 'Ke5']]);

        $r = $this->withToken($body['token'])->getJson('/api/v1/games/' . $partie['id']);

        $r->assertStatus(200);
        $this->assertTrue($r->json('needs_review'));
        $this->assertSame(['e2e4', 'Ke5'], $r->json('raw_moves'));
        $this->assertSame(['e2e4'], $r->json('moves_uci'));
    }

    public function test_update_remplace_une_saisie_manuelle(): void
    {
        $body = $this->registerUser();
        $partie = $this->saisir($body);

        $r = $this->withToken($body['token'])->putJson('/api/v1/games/' . $partie['id'] . '/manual', [
            'moves' => ['d2d4', 'd7d5', 'c2c4'],
            'validate_moves' => true,
            'played_at' => '2026-11-09',
            'opponent' => 'dave',
            'color' => 'black',
            'result' => '0-1',
        ]);

        $r->assertStatus(200);
        $this->assertSame('dave', $r->json('opponent'));
        $this->assertSame('black', $r->json('color'));
        $this->assertSame(['d2d4', 'd7d5', 'c2c4'], $r->json('moves_uci'));
        $this->assertStringContainsString('[White "dave"]', (string) $r->json('pgn'));
        $this->assertStringContainsString('[Black "saisie"]', (string) $r->json('pgn'));

        $this->assertDatabaseHas('games', ['id' => $partie['id'], 'opponent' => 'dave']);
        $this->assertDatabaseCount('games', 1);
    }

    public function test_update_corrige_une_partie_a_verifier(): void
    {
        $body = $this->registerUser();
        $partie = $this->saisir($body, ['validate_moves' => false, 'moves' => ['e2e4', 'Ke5']]);
        $this->assertTrue($partie['needs_review']);

        $r = $this->withToken($body['token'])->putJson('/api/v1/games/' . $partie['id'] . '/manual', [
            'moves' => ['e2e4', 'e7e5'],
            'validate_moves' => true,
        ] + self::FICHE);

        $r->assertStatus(200);
        $this->assertFalse($r->json('needs_review'));
        $this->assertNull($r->json('raw_moves'));
        $this->assertSame(['e2e4', 'e7e5'], $r->json('moves_uci'));
    }

    public function test_update_refuse_une_partie_importee(): void
    {
        $body = $this->registerUser();
        (new GameRepository())->upsertFromPgn(
            (int) $body['user_id'],
            'lichess',
            '[Event "Blitz"]' . "\n\n" . '1. d4 d5 *',
            'x1',
            ['opponent' => 'eve'],
        );
        $id = (int) Game::query()->first()->id;

        $r = $this->withToken($body['token'])->putJson('/api/v1/games/' . $id . '/manual', [
            'moves' => ['e2e4'],
            'validate_moves' => true,
        ] + self::FICHE);

        $r->assertStatus(422);
        $this->assertSame('pas_une_saisie_manuelle', $r->json('error'));
    }

    public function test_update_une_partie_d_autrui_est_introuvable(): void
    {
        $alice = $this->registerUser('alice@example.com');
        $bob = $this->registerUser('bob@example.com');
        $partie = $this->saisir($bob);

        $r = $this->withToken($alice['token'])->putJson('/api/v1/games/' . $partie['id'] . '/manual', [
            'moves' => ['e2e4'],
            'validate_moves' => true,
        ] + self::FICHE);

        $r->assertStatus(404);
    }
}
