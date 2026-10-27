<?php

declare(strict_types=1);

require_once __DIR__ . '/run_lib.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Migration;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Account\AccountService;
use Korrichess\Api\Domain\Auth\AuthService;
use Korrichess\Api\Domain\Settings\SettingsService;

freshDb();

$auth = new AuthService();
$accounts = new AccountService();
$settings = new SettingsService();

// Inscription
$r = $auth->register('Alice@Example.com', 'motdepasse123', null, null);
check('register: 201 + token', $r['status'] === 201 && isset($r['body']['token']) && $r['body']['user_id'] === 1);
check('register: email normalisé en minuscules', Database::one('SELECT email FROM users WHERE id = 1')['email'] === 'alice@example.com');
$r2 = $auth->register('alice@example.com', 'autre', null, null);
check('register: doublon refusé 409', $r2['status'] === 409);
$r3 = $auth->register('pas-un-email', 'x', null, null);
check('register: email invalide 422', $r3['status'] === 422);
$r4 = $auth->register('bob@example.com', null, null, null);
check('register: sans authentifiant refusé', $r4['status'] === 422);

// Connexion
$l = $auth->login('alice@example.com', 'motdepasse123');
check('login: 200', $l['status'] === 200);
$bad = $auth->login('alice@example.com', 'faux');
check('login: mauvais mot de passe 401', $bad['status'] === 401);

// Token
$token = $l['body']['token'];
check('token: valide pour le bon utilisateur', $auth->userIdForToken($token) === 1);
check('token: invalide pour un faux', $auth->userIdForToken('faux-token') === null);
$auth->logout($token);
check('logout: token révoqué', $auth->userIdForToken($token) === null);

// Profil
$up = $accounts->updateProfile(1, ['display_name' => 'Alice', 'club' => 'Échecs de Rennes', 'rating' => 1450]);
check('profil: mise à jour', $up['status'] === 200 && $up['body']['name'] === 'Alice' && $up['body']['rating'] === 1450);
$badRating = $accounts->updateProfile(1, ['rating' => -5]);
check('profil: classement négatif refusé', $badRating['status'] === 422);
$badDate = $accounts->updateProfile(1, ['birth_date' => 'pas-une-date']);
check('profil: date invalide refusée', $badDate['status'] === 422);
$okDate = $accounts->updateProfile(1, ['birth_date' => '2010-05-12']);
check('profil: date de naissance facultative acceptée', $okDate['status'] === 200 && $okDate['body']['birth_date'] === '2010-05-12');

// Pseudos liés (C3)
$la = $accounts->linkAccount(1, 'lichess', 'alice_chess');
check('liaison: 201', $la['status'] === 201);
$la2 = $accounts->linkAccount(1, 'Lichess', 'alice_chess');
check('liaison: idempotent', $la2['status'] === 200);
$la3 = $accounts->linkAccount(1, 'chesscom', 'alice_cc');
$la4 = $accounts->linkAccount(1, 'foo', 'x');
check('liaison: plateforme inconnue refusée', $la4['status'] === 422);
$profile = $accounts->profile(1);
check('liaison: deux pseudos listés', count($profile['linked_accounts']) === 2);

// Consentements (RGPD / mineurs)
$accounts->setConsent(1, 'parental_consent', true);
check('consentement: accordé', ($accounts->consents(1))['parental_consent'] === true);
$accounts->setConsent(1, 'parental_consent', false);
check('consentement: révocable', ($accounts->consents(1))['parental_consent'] === false);

// Paramètres (issue #10)
$s = $settings->get(1);
check('paramètres: valeurs par défaut', $s['language'] === 'fr' && $s['theme'] === 'clair');
$u = $settings->update(1, ['theme' => 'sombre', 'notify_analysis_done' => false, 'language' => 'fr']);
check('paramètres: thème sombre + notif désactivée', $u['status'] === 200 && $u['body']['theme'] === 'sombre' && $u['body']['notifications']['analysis_done'] === false);
$u2 = $settings->update(1, ['theme' => 'fluorescent']);
check('paramètres: valeur hors liste refusée', $u2['status'] === 422);

// Export et suppression RGPD (C4)
$carol = $auth->register('carol@example.com', 'pw123456', null, null);
$carolId = $carol['body']['user_id'];
$accounts->linkAccount($carolId, 'lichess', 'carol_l');
$export = $accounts->exportData($carolId);
check('export: contient profil et pseudos', isset($export['profile'][0]['club']) && $export['linked_accounts'][0]['username'] === 'carol_l');
$accounts->deleteAccount($carolId);
check('suppression: utilisateur effacé', Database::one('SELECT id FROM users WHERE id = :i', ['i' => $carolId]) === null);
check('suppression: pseudos effacés', Database::one('SELECT id FROM linked_accounts WHERE user_id = :i', ['i' => $carolId]) === null);
check('suppression: profil effacé', Database::one('SELECT user_id FROM player_profiles WHERE user_id = :i', ['i' => $carolId]) === null);

// Routes
$router = new Router();
(require dirname(__DIR__) . '/routes/auth.php')($router);
$req = new Request('POST', '/api/v1/auth/register', [], ['email' => 'dave@example.com', 'password' => 'pw123456'], []);
$resp = $router->route('POST', '/api/v1/auth/register')($req);
check('route: POST /auth/register', $resp['status'] === 201);
$reqAuth = new Request('GET', '/api/v1/me', [], [], ['authorization' => 'Bearer ' . $resp['body']['token']]);
$respMe = $router->route('GET', '/api/v1/me')($reqAuth);
check('route: GET /me authentifié', $respMe['status'] === 200 && $respMe['body']['email'] === 'dave@example.com');
$reqAnon = new Request('GET', '/api/v1/me', [], [], []);
$respAnon = $router->route('GET', '/api/v1/me')($reqAnon);
check('route: /me sans token 401', $respAnon['status'] === 401);

report();
