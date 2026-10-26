<?php

declare(strict_types=1);

namespace Korrichess\Api\Migrations;

use PDO;

final class Migration_20260101_0005
{
    public static function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS player_profiles (
            user_id INTEGER PRIMARY KEY,
            display_name TEXT NOT NULL DEFAULT "",
            club TEXT NOT NULL DEFAULT "",
            rating INTEGER,
            birth_date TEXT,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS linked_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            platform TEXT NOT NULL,
            username TEXT NOT NULL,
            last_sync_at TEXT,
            created_at TEXT NOT NULL,
            UNIQUE (user_id, platform, username)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_tokens (
            token TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            expires_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS consents (
            user_id INTEGER NOT NULL,
            kind TEXT NOT NULL,
            granted INTEGER NOT NULL DEFAULT 0,
            granted_at TEXT,
            PRIMARY KEY (user_id, kind)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_settings (
            user_id INTEGER PRIMARY KEY,
            language TEXT NOT NULL DEFAULT "fr",
            theme TEXT NOT NULL DEFAULT "clair",
            board_style TEXT NOT NULL DEFAULT "classique",
            piece_style TEXT NOT NULL DEFAULT "classique",
            notify_analysis_done INTEGER NOT NULL DEFAULT 1,
            notify_exercise_reminder INTEGER NOT NULL DEFAULT 1,
            notify_games_synced INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT NOT NULL
        )');
    }
}
