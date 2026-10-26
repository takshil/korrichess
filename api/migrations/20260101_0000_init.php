<?php

declare(strict_types=1);

namespace Korrichess\Api\Migrations;

use PDO;

final class Migration_20260101_0000
{
    public static function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT,
            google_sub TEXT,
            apple_sub TEXT,
            role TEXT NOT NULL DEFAULT "player",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            payload TEXT NOT NULL,
            priority INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "pending",
            attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT,
            created_at TEXT NOT NULL,
            started_at TEXT,
            finished_at TEXT
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs (status, priority DESC, id)');
    }
}
