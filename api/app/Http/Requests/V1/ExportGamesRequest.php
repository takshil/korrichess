<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ExportGamesRequest extends FormRequest
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
            'game_ids' => ['present', 'array'],
            'game_ids.*' => ['integer'],
        ];
    }
}
