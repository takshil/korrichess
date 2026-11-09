<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AnnotateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route protégée par auth:sanctum
    }

    /**
     * Champs facultatifs (B4) ; les bornes fines (longueur de note,
     * étiquettes vides…) sont vérifiées par le service en clés françaises.
     *
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'personal_note' => ['sometimes', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64'],
            'favorite' => ['sometimes', 'boolean'],
        ];
    }
}
