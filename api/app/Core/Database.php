<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = (string) (getenv('KORRI_DB_DSN') ?: 'sqlite:' . __DIR__ . '/../../data/korrichess.sqlite');
            if (str_starts_with($dsn, 'sqlite:')) {
                $dir = dirname(substr($dsn, 7));
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
            }
            self::$pdo = new PDO($dsn, getenv('KORRI_DB_USER') ?: null, getenv('KORRI_DB_PASS') ?: null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /** @param array<string,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @param array<string,mixed> $params @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed> $params @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
