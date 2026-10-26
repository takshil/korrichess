<?php

declare(strict_types=1);

namespace Korrichess\Api\Migrations;

use PDO;

final class Migration_20260101_0010_games
{
    public static function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            source TEXT NOT NULL,
            external_id TEXT,
            played_at TEXT,
            opponent TEXT,
            opponent_rating INTEGER,
            color TEXT,
            result TEXT,
            time_control TEXT,
            opening TEXT,
            tournament TEXT,
            round TEXT,
            pgn TEXT NOT NULL,
            moves_uci TEXT,
            status TEXT NOT NULL DEFAULT "final",
            storage_key TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (user_id, source, external_id)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_games_user ON games (user_id, played_at DESC)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sync_state (
            user_id INTEGER NOT NULL,
            platform TEXT NOT NULL,
            username TEXT NOT NULL,
            last_game_at TEXT,
            last_sync_at TEXT,
            PRIMARY KEY (user_id, platform, username)
        )');
    }
}
