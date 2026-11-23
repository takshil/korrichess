<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use RuntimeException;

/**
 * Refus métier (partie introuvable, saisie invalide, doublon…).
 *
 * Les services du domaine la lèvent au lieu de renvoyer un couple
 * {status, body} : ils ne connaissent plus rien du protocole HTTP. Le code
 * d'erreur (`partie_introuvable`…) est le contrat vu par les clients ; le
 * contexte est fusionné tel quel dans le corps de la réponse d'erreur.
 */
final class DomainError extends RuntimeException
{
    /**
     * @param array<string,mixed> $context
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly ErrorKind $kind,
        public readonly array $context = [],
    ) {
        parent::__construct($errorCode);
    }

    /** @param array<string,mixed> $context */
    public static function notFound(string $code, array $context = []): self
    {
        return new self($code, ErrorKind::NotFound, $context);
    }

    /** @param array<string,mixed> $context */
    public static function invalid(string $code, array $context = []): self
    {
        return new self($code, ErrorKind::Invalid, $context);
    }

    /** @param array<string,mixed> $context */
    public static function conflict(string $code, array $context = []): self
    {
        return new self($code, ErrorKind::Conflict, $context);
    }

    /** @param array<string,mixed> $context */
    public static function unauthenticated(string $code, array $context = []): self
    {
        return new self($code, ErrorKind::Unauthenticated, $context);
    }
}
