<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fiche S7 + coups S5 d'une saisie manuelle (création et correction).
 *
 * Un coup est une chaîne courte : UCI (ex. e2e4) produite par l'échiquier
 * interactif, ou notation algébrique (ex. Nf3) tapée depuis la feuille de
 * partie. Le serveur fait la différence et rejoue ; quand la validation
 * est désactivée, un coup impossible est conservé tel quel.
 */
final class ManualGameRequest extends FormRequest
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
            'moves' => ['required', 'array', 'min:1', 'max:500'],
            'moves.*' => ['required', 'string', 'max:12'],
            'validate_moves' => ['sometimes', 'boolean'],
            'played_at' => ['required', 'string', 'max:40', 'regex:/^\d{4}-\d{2}-\d{2}(T.*)?$/'],
            'opponent' => ['required', 'string', 'max:64'],
            'opponent_rating' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5000'],
            'color' => ['required', Rule::in(['white', 'black'])],
            'result' => ['required', Rule::in(['1-0', '0-1', '1/2-1/2', '*'])],
            'time_control' => ['sometimes', 'nullable', 'string', 'max:24'],
            'tournament' => ['sometimes', 'nullable', 'string', 'max:160'],
            'round' => ['sometimes', 'nullable', 'string', 'max:24'],
        ];
    }
}
