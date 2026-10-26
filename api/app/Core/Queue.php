<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

final class Queue
{
    private const MAX_ATTEMPTS = 5;

    public function enqueue(string $type, array $payload, int $priority = 0): int
    {
        Database::run(
            'INSERT INTO jobs (type, payload, priority, status, attempts, created_at)
             VALUES (:type, :payload, :priority, :status, 0, :created_at)',
            [
                'type' => $type,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
                'priority' => $priority,
                'status' => 'pending',
                'created_at' => gmdate('c'),
            ],
        );
        return Database::lastId();
    }

    /** @return array<string,mixed>|null */
    public function pop(): ?array
    {
        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            Database::run('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        $pdo->beginTransaction();
        try {
            $job = Database::one(
                "SELECT * FROM jobs WHERE status = :status ORDER BY priority DESC, id ASC LIMIT 1",
                ['status' => 'pending'],
            );
            if ($job === null) {
                $pdo->rollBack();
                return null;
            }
            Database::run("UPDATE jobs SET status = 'running', started_at = :now WHERE id = :id", [
                'now' => gmdate('c'),
                'id' => $job['id'],
            ]);
            $pdo->commit();
            $job['status'] = 'running';
            return $job;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function complete(int $id): void
    {
        Database::run("UPDATE jobs SET status = 'done', finished_at = :now WHERE id = :id", [
            'now' => gmdate('c'),
            'id' => $id,
        ]);
    }

    public function fail(int $id, string $error): void
    {
        $job = Database::one('SELECT attempts FROM jobs WHERE id = :id', ['id' => $id]);
        $attempts = (int) ($job['attempts'] ?? 0) + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            Database::run(
                "UPDATE jobs SET status = 'failed', attempts = :attempts, last_error = :error, finished_at = :now WHERE id = :id",
                ['attempts' => $attempts, 'error' => $error, 'now' => gmdate('c'), 'id' => $id],
            );
            return;
        }
        Database::run(
            "UPDATE jobs SET status = 'pending', attempts = :attempts, last_error = :error WHERE id = :id",
            ['attempts' => $attempts, 'error' => $error, 'id' => $id],
        );
    }

    public function releaseStale(int $olderThanMinutes = 30): void
    {
        Database::run(
            "UPDATE jobs SET status = 'pending' WHERE status = 'running' AND started_at < :limit",
            ['limit' => gmdate('c', time() - $olderThanMinutes * 60)],
        );
    }
}
