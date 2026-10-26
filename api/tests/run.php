<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap-dev.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Migration;
use Korrichess\Api\Core\Queue;
use Korrichess\Api\Core\QueueWorker;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Core\Storage;

$failures = 0;
$tests = 0;

function check(string $name, bool $ok): void
{
    global $failures, $tests;
    $tests++;
    if ($ok) {
        echo "ok    $name\n";
    } else {
        $failures++;
        echo "FAIL  $name\n";
    }
}

function freshDb(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    Database::setPdo($pdo);
    Migration::migrate($pdo);
    return $pdo;
}

// --- Router ---
$router = new Router();
$seen = [];
$router->get('/api/v1/games/{id}', function ($req) use (&$seen) {
    $seen = $req->pathParams;
    return ['status' => 200, 'body' => []];
});
$handler = $router->route('GET', '/api/v1/games/42');
check('router: route paramétrée trouvée', $handler !== null);
$request = new Korrichess\Api\Core\Request('GET', '/api/v1/games/42', [], [], []);
$handler($request);
check('router: paramètre extrait', ($seen['id'] ?? '') === '42');
check('router: route inconnue', $router->route('GET', '/api/v1/inexistant') === null);
check('router: méthode inconnue', $router->route('DELETE', '/api/v1/games/42') === null);

// --- Router match statique vs paramétré priorité ---
$router2 = new Router();
$router2->get('/api/v1/health', fn () => ['status' => 200, 'body' => 'static']);
check('router: chemin exact avec slash final', $router2->route('GET', '/api/v1/health/') !== null);

// --- Migrations idempotentes ---
$pdo = freshDb();
$first = Migration::migrate($pdo);
check('migrations: deuxième passage sans changement', $first === []);
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
check('migrations: tables créées', in_array('users', $tables, true) && in_array('jobs', $tables, true));

// --- Queue ---
$queue = new Queue();
$id = $queue->enqueue('analyse', ['game_id' => 7], priority: 5);
$job = $queue->pop();
check('queue: job récupéré avec bon type', $job !== null && $job['type'] === 'analyse');
check('queue: payload intact', json_decode((string) $job['payload'], true)['game_id'] === 7);
$queue->complete((int) $job['id']);
check('queue: plus rien en attente', $queue->pop() === null);

// --- Queue: échecs et reprises ---
$id = $queue->enqueue('test_fail', []);
$attempts = [];
$worker = new QueueWorker();
$worker->register('test_fail', function () {
    throw new RuntimeException('boom');
});
$processed = 0;
for ($i = 0; $i < 6; $i++) {
    $processed += $worker->work(1);
    $row = Database::one('SELECT status, attempts FROM jobs WHERE id = :id', ['id' => $id]);
    $attempts[] = $row['status'];
}
check('queue: job reprogrammé après échec', in_array('pending', $attempts, true));

// --- Worker exécute un handler ---
$queue->enqueue('test_ok', ['x' => 1]);
$worker->register('test_ok', function ($payload) {
    Database::run('INSERT INTO users (email, created_at, updated_at) VALUES (:e, :c, :c)', [
        'e' => 'worker@korrichess.test', 'c' => gmdate('c'),
    ]);
});
$worker->work(1);
$row = Database::one('SELECT email FROM users WHERE email = :e', ['e' => 'worker@korrichess.test']);
check('worker: handler exécuté', $row !== null);

// --- Storage ---
$tmp = sys_get_temp_dir() . '/korri-storage-test-' . uniqid();
$storage = new Storage($tmp);
$storage->put('feuilles/abc.jpg', 'binaire');
check('storage: écriture et lecture', $storage->get('feuilles/abc.jpg') === 'binaire');
$storage->delete('feuilles/abc.jpg');
check('storage: suppression', $storage->get('feuilles/abc.jpg') === null);
$traversalBlocked = false;
try {
    $storage->get('../../etc/passwd');
} catch (InvalidArgumentException) {
    $traversalBlocked = true;
}
check('storage: traversée hors racine bloquée', $traversalBlocked);

// --- bin/migrate.php s'exécute ---
$output = shell_exec('php ' . escapeshellarg(__DIR__ . '/../bin/migrate.php') . ' 2>&1');
$envDsn = getenv('KORRI_DB_DSN');
putenv('KORRI_DB_DSN=sqlite:' . sys_get_temp_dir() . '/korri-migrate-test.sqlite');
$last = shell_exec('php ' . escapeshellarg(__DIR__ . '/../bin/migrate.php') . ' 2>&1');
$second = shell_exec('php ' . escapeshellarg(__DIR__ . '/../bin/migrate.php') . ' 2>&1');
check('bin/migrate: idempotent en fichier', str_contains((string) $second, 'Rien à migrer'));
if ($envDsn === false) {
    putenv('KORRI_DB_DSN');
} else {
    putenv('KORRI_DB_DSN=' . $envDsn);
}
@unlink(sys_get_temp_dir() . '/korri-migrate-test.sqlite');

echo "\n$tests tests, $failures échec(s)\n";
exit($failures === 0 ? 0 : 1);
