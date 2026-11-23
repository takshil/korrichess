<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Curseurs de synchronisation incrémentale, par compte lié. */
final class SyncStateRepository
{
    /**
     * Curseur d'un compte lié.
     *
     * @return array{last_game_at:?string,last_sync_at:?string}
     */
    public function cursor(int $userId, string $platform, string $username): array
    {
        $row = DB::table('sync_state')
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->where('username', $username)
            ->first();

        return [
            'last_game_at' => $row->last_game_at ?? null,
            'last_sync_at' => $row->last_sync_at ?? null,
        ];
    }

    public function save(int $userId, string $platform, string $username, ?string $lastGameAt): void
    {
        DB::table('sync_state')->upsert(
            [
                'user_id' => $userId,
                'platform' => $platform,
                'username' => $username,
                'last_game_at' => $lastGameAt,
                'last_sync_at' => gmdate('c'),
            ],
            ['user_id', 'platform', 'username'],
            ['last_game_at', 'last_sync_at'],
        );
    }

    /**
     * Curseurs de tous les comptes d'un joueur.
     *
     * @return Collection<int,object{platform:string,username:string,last_game_at:?string,last_sync_at:?string}>
     */
    public function forUser(int $userId): Collection
    {
        return DB::table('sync_state')
            ->where('user_id', $userId)
            ->orderBy('platform')
            ->get(['platform', 'username', 'last_game_at', 'last_sync_at']);
    }
}
