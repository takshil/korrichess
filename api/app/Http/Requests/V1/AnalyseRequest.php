<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AnalyseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route protégée par auth:sanctum
    }

    /**
     * Profondeur bornée 4–20 (le service reborne par sécurité).
     *
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'depth' => ['sometimes', 'integer', 'min:4', 'max:20'],
        ];
    }
}
