<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:200'],
            'google_sub' => ['nullable', 'string', 'max:255'],
            'apple_sub' => ['nullable', 'string', 'max:255'],
        ];
    }
}
