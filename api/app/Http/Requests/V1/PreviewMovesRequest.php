<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Prévisualisation d'une liste de coups (S5) : elle peut être vide —
 * le client demande alors la position de départ et ses coups légaux.
 */
final class PreviewMovesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route protégée par auth:sanctum
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'moves' => ['present', 'array', 'max:500'],
            'moves.*' => ['required', 'string', 'max:12'],
        ];
    }
}
