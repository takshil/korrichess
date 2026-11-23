<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Filtres de synchronisation (L5) : cadences retenues et début de période.
 */
final class SyncFilters
{
    /** @param list<string> $timeControls */
    public function __construct(
        public readonly array $timeControls = [],
        public readonly ?string $periodStart = null,
    ) {
    }

    /** @param array{time_controls?:array<string>,period_start?:?string} $filters */
    public static function fromArray(array $filters): self
    {
        return new self(
            array_values(array_map('strval', $filters['time_controls'] ?? [])),
            ($filters['period_start'] ?? null) ?: null,
        );
    }

    /**
     * @param array<string,mixed> $game partie brute renvoyée par la plateforme
     * @param array<string,mixed> $meta métadonnées extraites du PGN
     */
    public function accepts(array $game, array $meta): bool
    {
        if ($this->timeControls !== [] && !in_array((string) ($meta['time_control'] ?? ''), $this->timeControls, true)) {
            return false;
        }

        $playedAt = $game['played_at'] ?? null;
        if ($this->periodStart !== null && $playedAt !== null && (string) $playedAt < $this->periodStart) {
            return false;
        }

        return true;
    }
}
