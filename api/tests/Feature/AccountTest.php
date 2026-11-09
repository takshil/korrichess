<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LinkedAccount;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inscription via l'API ; retourne le corps de la réponse
     * (identifiant + jeton Sanctum).
     *
     * @return array{user_id:int,token:string}
     */
    private function registerUser(string $email = 'alice@example.com', string $password = 'motdepasse123'): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    // ---------------------------------------------------------------- C1

    public function test_inscription_cree_un_compte_et_un_jeton(): void
    {
        $body = $this->registerUser();

        $this->assertSame(1, $body['user_id']);
        $this->assertNotSame('', $body['token']);
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_inscription_normalise_email_en_minuscules(): void
    {
        $this->registerUser('Alice@Example.com');

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_inscription_refuse_les_doublons(): void
    {
        $this->registerUser();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'alice@example.com',
            'password' => 'autre-mot-de-passe',
        ]);

        $response->assertStatus(409)->assertJson(['error' => 'email_deja_utilise']);
    }

    public function test_inscription_refuse_un_email_invalide(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'pas-un-email',
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(422);
    }

    public function test_inscription_exige_un_authentifiant(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'bob@example.com',
        ]);

        $response->assertStatus(422)->assertJson(['error' => 'authentifiant_requis']);
    }

    public function test_inscription_cree_profil_et_parametres_par_defaut(): void
    {
        $this->registerUser();

        $this->assertDatabaseHas('player_profiles', ['user_id' => 1, 'display_name' => 'alice', 'club' => '']);
        $this->assertDatabaseHas('user_settings', ['user_id' => 1, 'language' => 'fr', 'theme' => 'clair']);
    }

    public function test_le_mot_de_passe_est_hache_et_jamais_stocke_en_clair(): void
    {
        $this->registerUser();

        $user = User::query()->first();
        $this->assertNotSame('motdepasse123', $user->password);
        $this->assertTrue(password_verify('motdepasse123', $user->password));
    }

    public function test_connexion_renvoie_un_jeton(): void
    {
        $this->registerUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'motdepasse123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user_id', 'token']);
    }

    public function test_connexion_refuse_un_mauvais_mot_de_passe(): void
    {
        $this->registerUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'faux',
        ]);

        $response->assertStatus(401)->assertJson(['error' => 'identifiants_invalides']);
    }

    public function test_profil_accessible_avec_un_jeton(): void
    {
        $body = $this->registerUser();

        $response = $this->withToken($body['token'])->getJson('/api/v1/me');

        $response->assertStatus(200)->assertJson([
            'user_id' => $body['user_id'],
            'email' => 'alice@example.com',
        ]);
    }

    public function test_profil_inaccessible_sans_jetons(): void
    {
        $this->registerUser();

        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_deconnexion_revoque_le_jetons(): void
    {
        $body = $this->registerUser();

        $this->withToken($body['token'])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJson(['message' => 'deconnecte']);

        $this->withToken($body['token'])->getJson('/api/v1/me')->assertStatus(401);
    }

    // ---------------------------------------------------------------- C2

    public function test_mise_a_jour_du_profil(): void
    {
        $body = $this->registerUser();

        $response = $this->withToken($body['token'])->patchJson('/api/v1/me', [
            'display_name' => 'Alice',
            'club' => 'Échecs de Rennes',
            'rating' => 1450,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Alice')
            ->assertJsonPath('club', 'Échecs de Rennes')
            ->assertJsonPath('rating', 1450);
    }

    public function test_profil_refuse_un_classement_negatif(): void
    {
        $body = $this->registerUser();

        $this->withToken($body['token'])
            ->patchJson('/api/v1/me', ['rating' => -5])
            ->assertStatus(422);
    }

    public function test_profil_refuse_une_date_invalide(): void
    {
        $body = $this->registerUser();

        $this->withToken($body['token'])
            ->patchJson('/api/v1/me', ['birth_date' => 'pas-une-date'])
            ->assertStatus(422);
    }

    public function test_date_de_naissance_facultative_acceptee(): void
    {
        $body = $this->registerUser();

        $response = $this->withToken($body['token'])->patchJson('/api/v1/me', [
            'birth_date' => '2010-05-12',
        ]);

        $response->assertStatus(200)->assertJsonPath('birth_date', '2010-05-12');
    }

    // ---------------------------------------------------------------- C3

    public function test_liaison_de_pseudos_lichess_et_chesscom(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];

        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'lichess', 'username' => 'alice_chess'])
            ->assertStatus(201)
            ->assertJson(['message' => 'compte_lie']);

        // Idempotent : la plateforme est normalisée, la liaison existe déjà.
        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'Lichess', 'username' => 'alice_chess'])
            ->assertStatus(200)
            ->assertJson(['message' => 'deja_lie']);

        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'chesscom', 'username' => 'alice_cc'])
            ->assertStatus(201);

        // Plateforme inconnue refusée par la validation.
        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'foo', 'username' => 'x'])
            ->assertStatus(422);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertStatus(200)
            ->assertJsonCount(2, 'linked_accounts');
    }

    public function test_deliaison_d_un_pseudo(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];

        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'lichess', 'username' => 'alice_chess'])
            ->assertStatus(201);

        $this->withToken($token)
            ->deleteJson('/api/v1/me/linked-accounts', ['platform' => 'lichess', 'username' => 'alice_chess'])
            ->assertStatus(200)
            ->assertJson(['message' => 'delie']);

        $this->withToken($token)
            ->deleteJson('/api/v1/me/linked-accounts', ['platform' => 'lichess', 'username' => 'alice_chess'])
            ->assertStatus(404)
            ->assertJson(['error' => 'introuvable']);
    }

    // ------------------------------------------------- RGPD (#18 / C4)

    public function test_consentements_sont_accordables_et_revocables(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];

        $this->withToken($token)
            ->putJson('/api/v1/me/consents', ['parental_consent' => true])
            ->assertStatus(200)
            ->assertJsonPath('consents.parental_consent', true);

        $this->withToken($token)
            ->putJson('/api/v1/me/consents', ['parental_consent' => false])
            ->assertStatus(200)
            ->assertJsonPath('consents.parental_consent', false);
    }

    public function test_export_rgpd_contient_profil_et_pseudos(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];

        $this->withToken($token)->patchJson('/api/v1/me', [
            'club' => 'Échecs de Rennes',
        ]);
        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'lichess', 'username' => 'carol_l']);

        $response = $this->withToken($token)->getJson('/api/v1/me/export');

        $response->assertStatus(200);
        $export = $response->json();
        $this->assertSame('Échecs de Rennes', $export['profile'][0]['club']);
        $this->assertSame('carol_l', $export['linked_accounts'][0]['username']);
        $this->assertArrayHasKey('settings', $export);
        $this->assertArrayHasKey('consents', $export);
    }

    public function test_suppression_rgpd_efface_toutes_les_donnees(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];

        $this->withToken($token)->patchJson('/api/v1/me', ['club' => 'Club']);
        $this->withToken($token)
            ->postJson('/api/v1/me/linked-accounts', ['platform' => 'lichess', 'username' => 'alice_l']);
        $this->withToken($token)
            ->putJson('/api/v1/me/consents', ['terms' => true]);

        $this->withToken($token)
            ->deleteJson('/api/v1/me')
            ->assertStatus(200)
            ->assertJson(['message' => 'compte_supprime']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('player_profiles', 0);
        $this->assertDatabaseCount('linked_accounts', 0);
        $this->assertDatabaseCount('consents', 0);
        $this->assertDatabaseCount('user_settings', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Le jeton du compte supprimé ne fonctionne plus.
        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    // ------------------------------------------------- Paramètres (#10)

    public function test_parametres_avec_valeurs_par_defaut(): void
    {
        $body = $this->registerUser();

        $this->withToken($body['token'])
            ->getJson('/api/v1/me/settings')
            ->assertStatus(200)
            ->assertJson([
                'language' => 'fr',
                'theme' => 'clair',
                'board_style' => 'classique',
                'piece_style' => 'classique',
                'notifications' => [
                    'analysis_done' => true,
                    'exercise_reminder' => true,
                    'games_synced' => true,
                ],
            ]);
    }

    public function test_mise_a_jour_des_parametres(): void
    {
        $body = $this->registerUser();
        $token = $body['token'];

        $response = $this->withToken($token)->patchJson('/api/v1/me/settings', [
            'theme' => 'sombre',
            'notify_analysis_done' => false,
            'language' => 'fr',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('theme', 'sombre')
            ->assertJsonPath('notifications.analysis_done', false);

        // Valeur hors liste refusée.
        $this->withToken($token)
            ->patchJson('/api/v1/me/settings', ['theme' => 'fluorescent'])
            ->assertStatus(422);
    }
}
