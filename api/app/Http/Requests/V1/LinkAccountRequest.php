<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class LinkAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La plateforme est normalisée en minuscules avant validation
     * (Lichess ≡ lichess, cf. comportement idempotent de la liaison).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'platform' => strtolower(trim((string) $this->input('platform', ''))),
            'username' => trim((string) $this->input('username', '')),
        ]);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:lichess,chesscom'],
            'username' => ['required', 'string', 'min:1', 'max:64'],
        ];
    }
}
