<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/** Issue d'une demande d'analyse (A10). */
enum AnalysisRequestOutcome
{
    /** Une analyse vient d'être mise en file. */
    case Queued;
    /** Une analyse est déjà en attente : on la réutilise. */
    case AlreadyPending;
    /** Une analyse terminée existe déjà : rien à refaire. */
    case AlreadyDone;
}
