<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'language' => ['sometimes', 'string', 'in:fr,en'],
            'theme' => ['sometimes', 'string', 'in:clair,sombre'],
            'board_style' => ['sometimes', 'string', 'in:classique,bois,contraste'],
            'piece_style' => ['sometimes', 'string', 'in:classique,minimaliste'],
            'notify_analysis_done' => ['sometimes', 'boolean'],
            'notify_exercise_reminder' => ['sometimes', 'boolean'],
            'notify_games_synced' => ['sometimes', 'boolean'],
        ];
    }
}
