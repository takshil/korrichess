<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap-dev.php';

use Korrichess\Api\Core\Migration;

$done = Migration::migrate();
echo $done === [] ? "Rien à migrer.\n" : "Migrations appliquées : " . implode(', ', $done) . "\n";
