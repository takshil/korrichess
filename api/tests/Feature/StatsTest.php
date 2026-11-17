<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/v1/me/stats (W2) : les compteurs du tableau de bord sont
 * agrégés en base sur TOUTES les parties, pas seulement les 200
 * dernières renvoyées par /games.
 */
final class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_stats_agrege_toutes_les_parties(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'stats@example.com',
            'password' => 'motdepasse123',
        ]);
        $token = $response->json('token');

        $lignes = [
            ['result' => '1-0', 'color' => 'white'],      // victoire
            ['result' => '1-0', 'color' => 'black'],       // défaite
            ['result' => '0-1', 'color' => 'white'],       // défaite
            ['result' => '1/2-1/2', 'color' => 'black'],   // nulle
        ];
        foreach ($lignes as $i => $ligne) {
            DB::table('games')->insert([
                'user_id' => 1,
                'source' => 'lichess',
                'external_id' => 'g' . $i,
                'played_at' => '2025-03-10T00:00:00Z',
                'color' => $ligne['color'],
                'result' => $ligne['result'],
                'pgn' => '*',
            ]);
        }

        $this->withToken($token)
            ->getJson('/api/v1/me/stats')
            ->assertStatus(200)
            ->assertJson([
                'total' => 4,
                'victoires' => 1,
                'nulles' => 1,
                'defaites' => 2,
                'taux_victoires' => 25,
            ]);
    }

    public function test_me_stats_vide_sans_parties(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'vide@example.com',
            'password' => 'motdepasse123',
        ]);
        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/me/stats')
            ->assertStatus(200)
            ->assertJson([
                'total' => 0,
                'taux_victoires' => 0,
            ]);
    }
}
