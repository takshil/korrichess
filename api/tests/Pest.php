<?php

$bootstrap = __DIR__.'/../vendor/autoload.php';

if (! is_string($bootstrap) || ! file_exists($bootstrap)) {
    $bootstrap = __DIR__.'/../../../../autoload.php';
}

require $bootstrap;

$app = require __DIR__.'/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

uses(Illuminate\Foundation\Testing\TestCase::class)->in('Feature');
