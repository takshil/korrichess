<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Limites de requêtes : file d'attente locale avec fenêtre glissante et
 * reprises (chaque plateforme a son quota propre).
 */
final class RateLimiter
{
    /** @var array<string,array<int,int>> plateforme => timestamps des appels */
    private array $calls = [];

    public function __construct(private readonly int $maxPerMinute = 20)
    {
    }

    public function wait(string $platform): void
    {
        $now = time();
        $this->calls[$platform] = array_values(array_filter(
            $this->calls[$platform] ?? [],
            static fn ($t) => $t > $now - 60,
        ));
        if (count($this->calls[$platform]) >= $this->maxPerMinute) {
            sleep(1);
            $this->wait($platform);

            return;
        }
        $this->calls[$platform][] = $now;
    }
}
