<?php

declare(strict_types=1);

namespace App\Domain\Account;

use Illuminate\Support\Facades\DB;

/** Consentements RGPD, révocables (#18, public majoritairement mineur). */
final class ConsentService
{
    /** Consentements connus. */
    public const KINDS = ['terms', 'parental_consent', 'data_retention', 'ocr_improvement'];

    /**
     * Accorde ou révoque des consentements ; les types inconnus sont ignorés.
     *
     * @param array<string,mixed> $choices type => accordé ?
     */
    public function update(int $userId, array $choices): void
    {
        foreach ($choices as $kind => $granted) {
            if (in_array((string) $kind, self::KINDS, true)) {
                DB::table('consents')->updateOrInsert(
                    ['user_id' => $userId, 'kind' => (string) $kind],
                    ['granted' => (bool) $granted, 'granted_at' => now()],
                );
            }
        }
    }

    /** @return array<string,bool> */
    public function all(int $userId): array
    {
        return DB::table('consents')
            ->where('user_id', $userId)
            ->orderBy('kind')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->kind => ((int) $row->granted) === 1])
            ->all();
    }
}
