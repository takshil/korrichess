<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ExploreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route protégée par auth:sanctum
    }

    /**
     * Coup UCI bien formé uniquement ; la légalité est vérifiée par le
     * moteur (422 coup_illégal).
     *
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'ply' => ['required', 'integer', 'min:0'],
            'move_uci' => ['required', 'string', 'regex:/^[a-h][1-8][a-h][1-8][qrbn]?$/'],
        ];
    }
}
