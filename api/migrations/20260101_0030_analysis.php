<?php

declare(strict_types=1);

namespace Korrichess\Api\Migrations;

use PDO;

final class Migration_20260101_0030_analysis
{
    public static function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS game_analyses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            depth INTEGER NOT NULL,
            engine TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            decisive_ply INTEGER,
            opening_name TEXT,
            book_exit_ply INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_game_analyses_game ON game_analyses (game_id, user_id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS analysis_moves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            analysis_id INTEGER NOT NULL,
            ply INTEGER NOT NULL,
            fen TEXT NOT NULL,
            move_uci TEXT NOT NULL,
            move_san TEXT NOT NULL,
            eval_cp INTEGER NOT NULL,
            best_move_uci TEXT,
            best_move_san TEXT,
            variation_uci TEXT,
            classification TEXT NOT NULL,
            explanation TEXT,
            UNIQUE (analysis_id, ply)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_analysis_moves_analysis ON analysis_moves (analysis_id, ply)');
    }
}
