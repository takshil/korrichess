<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-dev.php';

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Migration;

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

function report(): void
{
    global $failures, $tests;
    echo "\n$tests tests, $failures échec(s)\n";
    exit($failures === 0 ? 0 : 1);
}
