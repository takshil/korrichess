<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON du profil joueur (C2) : ressource = User avec `profile` et
 * `linkedAccounts` chargés.
 *
 * @property User $resource
 */
final class ProfileResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $profile = $user->profile;

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => (string) $profile->display_name,
            'club' => (string) $profile->club,
            'rating' => $profile->rating !== null ? (int) $profile->rating : null,
            'birth_date' => $profile->birth_date?->format('Y-m-d'),
            'linked_accounts' => $user->linkedAccounts
                ->map(static fn (LinkedAccount $account): array => [
                    'platform' => $account->platform,
                    'username' => $account->username,
                ])
                ->values()
                ->all(),
        ];
    }
}
