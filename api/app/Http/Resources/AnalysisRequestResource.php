<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Analysis\AnalysisRequestOutcome;
use App\Domain\Analysis\AnalysisRequestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON d'une demande d'analyse (A10) ; le statut HTTP découle de l'issue :
 * 202 si une analyse vient d'être mise en file, 200 sinon.
 *
 * @property AnalysisRequestResult $resource
 */
final class AnalysisRequestResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $analysis = $this->resource->analysis;

        $body = ['analysis_id' => (int) $analysis->id, 'status' => $analysis->status];
        if ($this->resource->outcome === AnalysisRequestOutcome::AlreadyDone) {
            $body['already'] = true;
        }

        return $body;
    }

    public function toResponse($request): JsonResponse
    {
        $status = $this->resource->outcome === AnalysisRequestOutcome::Queued ? 202 : 200;

        return parent::toResponse($request)->setStatusCode($status);
    }
}
