<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Models\User;

/**
 * Profil joueur (C2). La validation des champs est faite en amont par
 * UpdateProfileRequest : ce service ne fait que lire et appliquer.
 */
final class ProfileService
{
    /** Champs de profil modifiables, avec leur normalisation. */
    private const TEXT_FIELDS = ['display_name', 'club'];

    /** Profil complet du joueur (créé à la volée s'il n'existe pas encore). */
    public function show(User $user): User
    {
        $user->profile()->firstOrCreate([]);

        return $user->load([
            'profile',
            'linkedAccounts' => static fn ($q) => $q->orderBy('platform')->orderBy('username'),
        ]);
    }

    /**
     * @param array<string,mixed> $input champs validés
     */
    public function update(User $user, array $input): User
    {
        $profile = $user->profile()->firstOrCreate([]);

        $changes = [];
        foreach (['display_name', 'club', 'rating', 'birth_date'] as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = $this->normalise($field, $input[$field]);
            }
        }
        if ($changes !== []) {
            $profile->fill($changes)->save();
        }

        return $this->show($user);
    }

    private function normalise(string $field, mixed $value): int|string|null
    {
        return match (true) {
            $field === 'rating' => $value === null ? null : (int) $value,
            in_array($field, self::TEXT_FIELDS, true) => $value === null ? '' : (string) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
