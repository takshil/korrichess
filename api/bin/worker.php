<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap-dev.php';

use Korrichess\Api\Core\QueueWorker;

$worker = new QueueWorker();
foreach (glob(__DIR__ . '/../queue-handlers/*.php') ?: [] as $file) {
    $register = require $file;
    $register($worker);
}

$max = isset($argv[1]) ? max(1, (int) $argv[1]) : 10;
$count = $worker->work($max);
echo "Jobs traités : $count\n";
