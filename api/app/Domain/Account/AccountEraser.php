<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Suppression RGPD du compte : fichiers éventuels, jetons, puis
 * l'utilisateur (les clés étrangères en cascade emportent profil, pseudos,
 * paramètres, consentements, parties et analyses).
 */
final class AccountEraser
{
    public function erase(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $storageKeys = DB::table('games')->where('user_id', $user->id)->pluck('storage_key');
            foreach ($storageKeys as $key) {
                if ($key !== null && $key !== '') {
                    Storage::disk()->delete((string) $key);
                }
            }

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
