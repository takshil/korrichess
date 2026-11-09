<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ListGamesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route protégée par auth:sanctum
    }

    /**
     * Filtres de liste blanche : les tris non connus retombent sur played_at
     * côté service.
     *
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'nullable', 'string', 'max:20'],
            'opponent' => ['sometimes', 'nullable', 'string', 'max:64'],
            'opening' => ['sometimes', 'nullable', 'string', 'max:120'],
            'result' => ['sometimes', 'nullable', 'string', 'max:12'],
            'color' => ['sometimes', 'nullable', 'string', 'max:8'],
            'tournament' => ['sometimes', 'nullable', 'string', 'max:160'],
            'time_control' => ['sometimes', 'nullable', 'string', 'max:24'],
            'period_start' => ['sometimes', 'nullable', 'string', 'max:40'],
            'period_end' => ['sometimes', 'nullable', 'string', 'max:40'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:32'],
            'favorite' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'sort' => ['sometimes', 'string', 'in:played_at,created_at,opponent,opening'],
            'order' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
