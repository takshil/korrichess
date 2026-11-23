<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;

/** Export RGPD de toutes les données détenues sur le joueur (C4 / W8). */
final class PersonalDataExporter
{
    /** @return array<string,mixed> */
    public function export(User $user): array
    {
        $userId = (int) $user->id;
        $games = DB::table('games')->where('user_id', $userId)->get();

        return [
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
            'games' => $games->all(),
            'game_analyses' => DB::table('game_analyses')
                ->whereIn('game_id', $games->pluck('id'))
                ->get()
                ->all(),
        ];
    }
}
