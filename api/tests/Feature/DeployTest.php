<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class DeployTest extends TestCase
{
    private const JETON = 'jeton-de-test-ci';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('korrichess.deploy_token', self::JETON);
    }

    public function test_requete_sans_jeton_repond_404(): void
    {
        $this->postJson('/api/deploy/migrate')->assertNotFound();
    }

    public function test_requete_avec_mauvais_jeton_repond_404(): void
    {
        $this->postJson('/api/deploy/migrate', [], ['X-Deploy-Token' => 'mauvais-jeton'])
            ->assertNotFound();
    }

    public function test_bon_jeton_applique_les_migrations(): void
    {
        $this->postJson('/api/deploy/migrate', [], ['X-Deploy-Token' => self::JETON])
            ->assertOk()
            ->assertJsonPath('message', 'migrations_appliquees');
    }

    public function test_route_indisponible_sans_jeton_configure(): void
    {
        Config::set('korrichess.deploy_token', '');

        $this->postJson('/api/deploy/migrate', [], ['X-Deploy-Token' => 'peu-importe'])
            ->assertNotFound();
    }
}
