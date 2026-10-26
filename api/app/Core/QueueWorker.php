<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

final class QueueWorker
{
    /** @var array<string, callable(array):void> */
    private array $handlers = [];

    public function register(string $type, callable $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    /** Traite au plus $max jobs. Retourne le nombre traités. */
    public function work(int $max = 10): int
    {
        $queue = new Queue();
        $queue->releaseStale();
        $count = 0;
        while ($count < $max) {
            $job = $queue->pop();
            if ($job === null) {
                break;
            }
            $type = (string) $job['type'];
            $handler = $this->handlers[$type] ?? null;
            if ($handler === null) {
                $queue->fail((int) $job['id'], 'Aucun gestionnaire pour le type ' . $type);
                continue;
            }
            try {
                $payload = json_decode((string) $job['payload'], true) ?: [];
                $handler($payload);
                $queue->complete((int) $job['id']);
            } catch (\Throwable $e) {
                $queue->fail((int) $job['id'], $e->getMessage());
            }
            $count++;
        }
        return $count;
    }
}
