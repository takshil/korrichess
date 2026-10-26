<?php

declare(strict_types=1);

namespace Korrichess\Api\Support;

const KORRI_API_AUTOLOAD_DEV = __DIR__ . '/composer.autoload-dev.json';

function korri_classmap_autoload(string $class): void
{
    static $map = null;
    static $files = null;
    if ($map === null) {
        $conf = json_decode((string) file_get_contents(KORRI_API_AUTOLOAD_DEV), true);
        $map = [];
        $files = [];
        foreach ($conf['autoload']['classmap'] ?? [] as $dir) {
            foreach (glob(rtrim(__DIR__, '/') . '/' . $dir . '/*.php') ?: [] as $path) {
                $src = (string) file_get_contents($path);
                if (preg_match('/\bnamespace\s+([^;]+);/', $src, $ns) && preg_match_all('/\b(?:class|interface|trait)\s+(\w+)/', $src, $types)) {
                    foreach ($types[1] as $type) {
                        $map[$ns[1] . '\\' . $type] = $path;
                    }
                }
            }
        }
        foreach ($conf['autoload']['files'] ?? [] as $f) {
            $files[] = rtrim(__DIR__, '/') . '/' . $f;
        }
    }
    if (isset($map[$class])) {
        require_once $map[$class];
        return;
    }
    $bare = str_replace('\\', '/', $class);
    $candidate = __DIR__ . '/app/' . $bare . '.php';
    if (is_file($candidate)) {
        require_once $candidate;
    }
}

spl_autoload_register('Korrichess\Api\Support\korri_classmap_autoload');

foreach (glob(__DIR__ . '/migrations/*.php') ?: [] as $migration) {
    require_once $migration;
}

require_once __DIR__ . '/app/support.php';
