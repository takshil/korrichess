<?php

declare(strict_types=1);

use Korrichess\Api\Core\AuthMiddleware;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Account\AccountService;
use Korrichess\Api\Domain\Auth\AuthService;
use Korrichess\Api\Domain\Settings\SettingsService;

return static function (Router $router): void {
    $auth = new AuthService();
    $accounts = new AccountService();
    $settings = new SettingsService();

    $router->post('/api/v1/auth/register', static function (Request $request) use ($auth): array {
        $email = (string) $request->input('email', '');
        $password = $request->input('password');
        return $auth->register(
            $email,
            is_string($password) && $password !== '' ? $password : null,
            $request->input('google_sub') !== null ? (string) $request->input('google_sub') : null,
            $request->input('apple_sub') !== null ? (string) $request->input('apple_sub') : null,
        );
    });

    $router->post('/api/v1/auth/login', static function (Request $request) use ($auth): array {
        $password = $request->input('password');
        return $auth->login((string) $request->input('email', ''), is_string($password) ? $password : null);
    });

    $router->post('/api/v1/auth/logout', static function (Request $request) use ($auth): array {
        $token = $request->bearerToken();
        if ($token !== null) {
            $auth->logout($token);
        }
        return ['status' => 200, 'body' => ['message' => 'deconnecte']];
    });

    $router->get('/api/v1/me', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return ['status' => 200, 'body' => $accounts->profile(AuthMiddleware::userId($request))];
    });

    $router->patch('/api/v1/me', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $input = $request->input();
        return $accounts->updateProfile(AuthMiddleware::userId($request), is_array($input) ? $input : []);
    });

    $router->post('/api/v1/me/linked-accounts', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return $accounts->linkAccount(
            AuthMiddleware::userId($request),
            (string) $request->input('platform', ''),
            (string) $request->input('username', ''),
        );
    });

    $router->delete('/api/v1/me/linked-accounts', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $removed = $accounts->unlinkAccount(
            AuthMiddleware::userId($request),
            (string) $request->input('platform', ''),
            (string) $request->input('username', ''),
        );
        return ['status' => $removed ? 200 : 404, 'body' => $removed ? ['message' => 'delie'] : ['error' => 'introuvable']];
    });

    $router->get('/api/v1/me/consents', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return ['status' => 200, 'body' => ['consents' => $accounts->consents(AuthMiddleware::userId($request))]];
    });

    $router->put('/api/v1/me/consents', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $input = $request->input();
        $known = ['terms', 'parental_consent', 'data_retention', 'ocr_improvement'];
        foreach (is_array($input) ? $input : [] as $kind => $granted) {
            if (in_array($kind, $known, true)) {
                $accounts->setConsent(AuthMiddleware::userId($request), $kind, (bool) $granted);
            }
        }
        return ['status' => 200, 'body' => ['consents' => $accounts->consents(AuthMiddleware::userId($request))]];
    });

    $router->get('/api/v1/me/settings', static function (Request $request) use ($settings): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return ['status' => 200, 'body' => $settings->get(AuthMiddleware::userId($request))];
    });

    $router->patch('/api/v1/me/settings', static function (Request $request) use ($settings): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $input = $request->input();
        return $settings->update(AuthMiddleware::userId($request), is_array($input) ? $input : []);
    });

    $router->get('/api/v1/me/export', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        return ['status' => 200, 'body' => $accounts->exportData(AuthMiddleware::userId($request))];
    });

    $router->delete('/api/v1/me', static function (Request $request) use ($accounts): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $accounts->deleteAccount(AuthMiddleware::userId($request));
        return ['status' => 200, 'body' => ['message' => 'compte_supprime']];
    });
};
