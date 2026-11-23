<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ListGamesRequest extends FormRequest
{
    private const FILTER_KEYS = [
        'source', 'opponent', 'opening', 'result', 'color', 'tournament',
        'time_control', 'period_start', 'period_end', 'q', 'favorite', 'tag',
    ];

    public function authorize(): bool
    {
        return true; // route protégée par auth:sanctum
    }

    /**
     * Filtres de liste blanche : les tris non connus retombent sur played_at
     * côté dépôt (GameRepository).
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

    /**
     * Filtres renseignés (les valeurs vides sont ignorées).
     *
     * @return array<string,bool|string>
     */
    public function filters(): array
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $this->query($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $key === 'favorite' ? (bool) $value : (string) $value;
            }
        }

        return $filters;
    }

    public function limit(): int
    {
        return (int) $this->query('limit', 50);
    }

    public function offset(): int
    {
        return (int) $this->query('offset', 0);
    }

    public function sortColumn(): string
    {
        return (string) $this->query('sort', 'played_at');
    }

    public function sortOrder(): string
    {
        return (string) $this->query('order', 'desc');
    }
}
