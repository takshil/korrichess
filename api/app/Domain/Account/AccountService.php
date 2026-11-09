<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Models\LinkedAccount;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Compte joueur : profil (C2), comptes liés (C3), RGPD (C4 / #18) —
 * consentements, export et suppression complète des données.
 */
final class AccountService
{
    /** Champs de profil modifiables (C2). */
    private const PROFILE_FIELDS = ['display_name', 'club', 'rating', 'birth_date'];

    /** Plateformes de comptes liés autorisées (C3). */
    public const PLATFORMS = ['lichess', 'chesscom'];

    /** @return array<string,mixed> */
    public function profile(int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $profile = $user->profile()->firstOrCreate([]);

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => (string) $profile->display_name,
            'club' => (string) $profile->club,
            'rating' => $profile->rating !== null ? (int) $profile->rating : null,
            'birth_date' => $profile->birth_date?->format('Y-m-d'),
            'linked_accounts' => $user->linkedAccounts()
                ->orderBy('platform')
                ->orderBy('username')
                ->get()
                ->map(static fn (LinkedAccount $account): array => [
                    'platform' => $account->platform,
                    'username' => $account->username,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array{status:int,body:array} */
    public function updateProfile(int $userId, array $input): array
    {
        $user = User::query()->findOrFail($userId);
        $profile = $user->profile()->firstOrCreate([]);

        $changes = [];
        foreach (self::PROFILE_FIELDS as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            if ($field === 'rating' && $value !== null && (!is_numeric($value) || (int) $value < 0)) {
                return ['status' => 422, 'body' => ['error' => 'classement_invalide']];
            }
            if ($field === 'birth_date' && $value !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                return ['status' => 422, 'body' => ['error' => 'date_de_naissance_invalide']];
            }
            $changes[$field] = match ($field) {
                'rating' => $value === null ? null : (int) $value,
                'display_name', 'club' => $value === null ? '' : (string) $value,
                default => $value === null ? null : (string) $value,
            };
        }

        if ($changes !== []) {
            $profile->fill($changes)->save();
        }

        return ['status' => 200, 'body' => $this->profile($userId)];
    }

    /** @return array{status:int,body:array} */
    public function linkAccount(int $userId, string $platform, string $username): array
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, self::PLATFORMS, true)) {
            return ['status' => 422, 'body' => ['error' => 'plateforme_inconnue']];
        }
        $username = trim($username);
        if ($username === '' || strlen($username) > 64) {
            return ['status' => 422, 'body' => ['error' => 'pseudo_invalide']];
        }

        $user = User::query()->findOrFail($userId);
        $already = $user->linkedAccounts()
            ->where('platform', $platform)
            ->where('username', $username)
            ->exists();
        if ($already) {
            return ['status' => 200, 'body' => ['message' => 'deja_lie']];
        }

        $user->linkedAccounts()->create([
            'platform' => $platform,
            'username' => $username,
        ]);

        return ['status' => 201, 'body' => ['message' => 'compte_lie']];
    }

    public function unlinkAccount(int $userId, string $platform, string $username): bool
    {
        return LinkedAccount::query()
            ->where('user_id', $userId)
            ->where('platform', strtolower(trim($platform)))
            ->where('username', $username)
            ->delete() > 0;
    }

    public function setConsent(int $userId, string $kind, bool $granted): void
    {
        DB::table('consents')->updateOrInsert(
            ['user_id' => $userId, 'kind' => $kind],
            ['granted' => $granted, 'granted_at' => now()],
        );
    }

    /** @return array<string,bool> */
    public function consents(int $userId): array
    {
        return DB::table('consents')
            ->where('user_id', $userId)
            ->orderBy('kind')
            ->get()
            ->mapWithKeys(static fn ($row): array => [(string) $row->kind => ((int) $row->granted) === 1])
            ->all();
    }

    /**
     * Export RGPD de toutes les données détenues sur le joueur (C4 / W8).
     *
     * @return array<string,mixed>
     */
    public function exportData(int $userId): array
    {
        $user = User::query()->findOrFail($userId);

        $export = [
            'exported_at' => now()->toIso8601String(),
            'user_id' => $userId,
            'email' => $user->email,
            'profile' => [$user->profile()->firstOrCreate([])->toArray()],
            'linked_accounts' => $user->linkedAccounts()
                ->orderBy('platform')
                ->orderBy('username')
                ->get()
                ->toArray(),
            'settings' => [UserSetting::query()->firstOrCreate(['user_id' => $userId])->toArray()],
            'consents' => DB::table('consents')->where('user_id', $userId)->orderBy('kind')->get()->all(),
        ];

        // Les parties et analyses (V2) ne sont pas encore migrées : l'export
        // les englobera dès que les tables existent.
        if (Schema::hasTable('games')) {
            $games = DB::table('games')->where('user_id', $userId)->get();
            $export['games'] = $games->all();
            $export['game_analyses'] = DB::table('game_analyses')
                ->whereIn('game_id', $games->pluck('id'))
                ->get()
                ->all();
        }

        return $export;
    }

    /**
     * Suppression RGPD du compte : fichiers éventuels, jetons, puis l'utilisateur
     * (les clés étrangères en cascade emportent profil, pseudos, paramètres
     * et consentements).
     */
    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if (Schema::hasTable('games')) {
                foreach (DB::table('games')->where('user_id', $user->id)->pluck('storage_key') as $key) {
                    if ($key !== null && $key !== '') {
                        Storage::disk()->delete((string) $key);
                    }
                }
            }
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
