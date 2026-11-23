<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Nature d'un échec métier. Le domaine décrit *pourquoi* une opération est
 * refusée ; c'est la couche HTTP qui choisit le code de statut (cf.
 * App\Http\Exceptions\DomainErrorRenderer).
 */
enum ErrorKind
{
    case NotFound;
    case Invalid;
    case Conflict;
    case Unauthenticated;
}
