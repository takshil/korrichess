<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

use PDO;

final class Migration
{
    /** @return array<string, array{id: string, class: string}> sorted by id */
    public static function discover(): array
    {
        $found = [];
        foreach (glob(__DIR__ . '/../../migrations/*.php') ?: [] as $file) {
            $base = basename($file, '.php');
            if (!preg_match('/^(\d{8}_\d{4})_/', $base, $m)) {
                continue;
            }
            $id = $m[1];
            $src = (string) file_get_contents($file);
            if (!preg_match('/\b(?:class|interface)\s+(\w+)/', $src, $cm)) {
                continue;
            }
            $class = 'Korrichess\\Api\\Migrations\\' . $cm[1];
            $found[$id] = ['id' => $id, 'class' => $class];
        }
        ksort($found);
        return $found;
    }

    public static function migrate(?PDO $pdo = null): array
    {
        $pdo ??= Database::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id TEXT PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $appliedRows = Database::all('SELECT id FROM migrations');
        $applied = array_column($appliedRows, 'id');
        $done = [];
        foreach (self::discover() as $entry) {
            if (in_array($entry['id'], $applied, true)) {
                continue;
            }
            $class = $entry['class'];
            $class::up($pdo);
            Database::run('INSERT INTO migrations (id) VALUES (:id)', ['id' => $entry['id']]);
            $done[] = $entry['id'];
        }
        return $done;
    }
}
