<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel résout les guards une seule fois par process : en production,
     * chaque requête HTTP part d'un process neuf, mais dans un test le
     * conteneur — et donc le guard Sanctum (RequestGuard) — survit aux
     * requêtes successives. Sans réinitialisation, l'utilisateur reste en
     * cache dans le guard et un jeton révoqué (ou un appel sans jeton)
     * « passe » encore dans le même test.
     *
     * On oublie donc les guards résolus avant chaque requête HTTP, comme le
     * fait Octane entre deux requêtes. Les tests s'authentifient avec
     * withToken() ; Sanctum::actingAs() n'est pas utilisable ici car son
     * utilisateur serait également oublié.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (isset($this->app) && $this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
