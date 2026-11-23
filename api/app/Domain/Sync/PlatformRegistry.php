<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Clients des plateformes disponibles, indexés par nom (« lichess »,
 * « chesscom »). Les services de sync en dépendent, jamais d'un client
 * concret : ajouter une plateforme revient à l'enregistrer ici.
 */
final class PlatformRegistry
{
    /**
     * @param array<string,PlatformClient> $clients plateforme => client
     */
    public function __construct(private readonly array $clients = [])
    {
    }

    public function has(string $platform): bool
    {
        return isset($this->clients[self::normalise($platform)]);
    }

    public function client(string $platform): ?PlatformClient
    {
        return $this->clients[self::normalise($platform)] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->clients);
    }

    public static function normalise(string $platform): string
    {
        return strtolower(trim($platform));
    }
}
