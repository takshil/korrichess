<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SyncRequest extends FormRequest
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
            'platform' => ['required', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:64'],
            'time_controls' => ['sometimes', 'array'],
            'time_controls.*' => ['string', 'max:24'],
            'period_start' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Filtres de synchronisation (L5 : cadences, période).
     *
     * @return array{time_controls?:array<string>,period_start?:string}
     */
    public function filters(): array
    {
        $filters = [];
        $timeControls = $this->input('time_controls');
        if (is_array($timeControls)) {
            $filters['time_controls'] = array_values(array_map('strval', $timeControls));
        }
        $periodStart = $this->input('period_start');
        if (is_string($periodStart) && $periodStart !== '') {
            $filters['period_start'] = $periodStart;
        }

        return $filters;
    }
}
