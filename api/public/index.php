<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap-dev.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Migration;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;

$router = new Router();

$router->get('/api/v1/health', static function (): array {
    return ['status' => 200, 'body' => ['status' => 'ok', 'service' => 'korrichess-api']];
});

$loader = glob(__DIR__ . '/../routes/*.php') ?: [];
foreach ($loader as $file) {
    $register = require $file;
    $register($router);
}

$request = Request::fromGlobals();
$handler = $router->route($request->method, $request->path);
if ($handler === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'route_introuvable', 'path' => $request->path]);
    exit;
}

$response = $handler($request);
http_response_code($response['status']);
header('Content-Type: application/json');
echo json_encode($response['body'], JSON_UNESCAPED_UNICODE);
