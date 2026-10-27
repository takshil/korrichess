<?php

declare(strict_types=1);

namespace Korrichess\Api\Migrations;

use PDO;

final class Migration_20260101_0020_library
{
    public static function up(PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE games ADD COLUMN favorite INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE games ADD COLUMN personal_note TEXT NOT NULL DEFAULT ""');
        $pdo->exec('ALTER TABLE games ADD COLUMN tags TEXT NOT NULL DEFAULT ""');
        $pdo->exec('CREATE TABLE IF NOT EXISTS game_positions (
            game_id INTEGER NOT NULL,
            ply INTEGER NOT NULL,
            fen TEXT NOT NULL,
            PRIMARY KEY (game_id, ply)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_game_positions_fen ON game_positions (fen)');
    }
}
