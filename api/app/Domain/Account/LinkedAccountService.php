<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Shared\DomainError;
use App\Domain\Sync\SyncScheduler;
use App\Models\LinkedAccount;
use App\Models\User;

/**
 * Pseudos Lichess / Chess.com liés au compte (C3). La validation du
 * format (plateforme connue, pseudo non vide) est faite par
 * LinkAccountRequest.
 */
final class LinkedAccountService
{
    public function __construct(private readonly SyncScheduler $sync)
    {
    }

    /**
     * Lie un pseudo ; la première synchronisation part aussitôt en file.
     *
     * @return bool true si le lien vient d'être créé, false s'il existait déjà
     */
    public function link(User $user, string $platform, string $username): bool
    {
        $exists = $user->linkedAccounts()
            ->where('platform', $platform)
            ->where('username', $username)
            ->exists();
        if ($exists) {
            return false;
        }

        $user->linkedAccounts()->create(['platform' => $platform, 'username' => $username]);
        $this->sync->schedule((int) $user->id, $platform, $username);

        return true;
    }

    /**
     * @throws DomainError le lien n'existe pas
     */
    public function unlink(User $user, string $platform, string $username): void
    {
        $removed = LinkedAccount::query()
            ->where('user_id', $user->id)
            ->where('platform', strtolower(trim($platform)))
            ->where('username', $username)
            ->delete();

        if ($removed === 0) {
            throw DomainError::notFound('introuvable');
        }
    }
}
