<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'club' => ['sometimes', 'nullable', 'string', 'max:128'],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'birth_date' => ['sometimes', 'nullable', 'string', 'date_format:Y-m-d'],
        ];
    }
}
