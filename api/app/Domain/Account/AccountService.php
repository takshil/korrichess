<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Account;

use Korrichess\Api\Core\Database;
use Korrichess\Api\Core\Storage;

final class AccountService
{
    public function __construct(private readonly Storage $storage = new Storage())
    {
    }

    /** @return array<string,mixed> */
    public function profile(int $userId): array
    {
        $user = Database::one('SELECT id, email, google_sub, apple_sub, created_at FROM users WHERE id = :id', ['id' => $userId]);
        $profile = Database::one('SELECT * FROM player_profiles WHERE user_id = :id', ['id' => $userId]);
        $linked = Database::all('SELECT platform, username FROM linked_accounts WHERE user_id = :id ORDER BY platform, username', ['id' => $userId]);
        return [
            'user_id' => $userId,
            'email' => $user['email'] ?? null,
            'name' => $profile['display_name'] ?? '',
            'club' => $profile['club'] ?? '',
            'rating' => $profile['rating'] !== null ? (int) $profile['rating'] : null,
            'birth_date' => $profile['birth_date'] ?? null,
            'linked_accounts' => array_map(static fn ($a) => ['platform' => $a['platform'], 'username' => $a['username']], $linked),
        ];
    }

    /** @return array{status:int,body:array} */
    public function updateProfile(int $userId, array $input): array
    {
        $allowed = ['display_name', 'club', 'rating', 'birth_date'];
        $fields = [];
        $params = ['id' => $userId, 'now' => gmdate('c')];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                if ($field === 'rating' && $value !== null && (!is_numeric($value) || (int) $value < 0)) {
                    return ['status' => 422, 'body' => ['error' => 'classement_invalide']];
                }
                if ($field === 'birth_date' && $value !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                    return ['status' => 422, 'body' => ['error' => 'date_de_naissance_invalide']];
                }
                $fields[] = "$field = :$field";
                $params[$field] = $field === 'rating' ? ($value === null ? null : (int) $value) : (string) $value;
            }
        }
        if ($fields !== []) {
            $sql = 'UPDATE player_profiles SET ' . implode(', ', $fields) . ', updated_at = :now WHERE user_id = :id';
            Database::run($sql, $params);
        }
        return ['status' => 200, 'body' => $this->profile($userId)];
    }

    /** @return array{status:int,body:array} */
    public function linkAccount(int $userId, string $platform, string $username): array
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, ['lichess', 'chesscom'], true)) {
            return ['status' => 422, 'body' => ['error' => 'plateforme_inconnue']];
        }
        $username = trim($username);
        if ($username === '' || strlen($username) > 64) {
            return ['status' => 422, 'body' => ['error' => 'pseudo_invalide']];
        }
        if (Database::one(
            'SELECT id FROM linked_accounts WHERE user_id = :u AND platform = :p AND username = :n',
            ['u' => $userId, 'p' => $platform, 'n' => $username],
        ) !== null) {
            return ['status' => 200, 'body' => ['message' => 'deja_lie']];
        }
        Database::run(
            'INSERT INTO linked_accounts (user_id, platform, username, created_at) VALUES (:u, :p, :n, :now)',
            ['u' => $userId, 'p' => $platform, 'n' => $username, 'now' => gmdate('c')],
        );
        return ['status' => 201, 'body' => ['message' => 'compte_lie']];
    }

    public function unlinkAccount(int $userId, string $platform, string $username): bool
    {
        return Database::run(
            'DELETE FROM linked_accounts WHERE user_id = :u AND platform = :p AND username = :n',
            ['u' => $userId, 'p' => strtolower(trim($platform)), 'n' => $username],
        )->rowCount() > 0;
    }

    public function setConsent(int $userId, string $kind, bool $granted): void
    {
        Database::run(
            'INSERT INTO consents (user_id, kind, granted, granted_at) VALUES (:u, :k, :g, :now)
             ON CONFLICT (user_id, kind) DO UPDATE SET granted = :g, granted_at = :now',
            ['u' => $userId, 'k' => $kind, 'g' => $granted ? 1 : 0, 'now' => gmdate('c')],
        );
    }

    /** @return array<string,bool> */
    public function consents(int $userId): array
    {
        $rows = Database::all('SELECT kind, granted FROM consents WHERE user_id = :u', ['u' => $userId]);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['kind']] = ((int) $row['granted']) === 1;
        }
        return $out;
    }

    /** Export RGPD de toutes les données de l'utilisateur (C4 / W8). */
    public function exportData(int $userId): array
    {
        $tables = [
            'profile' => ['player_profiles', 'user_id'],
            'linked_accounts' => ['linked_accounts', 'user_id'],
            'settings' => ['user_settings', 'user_id'],
            'consents' => ['consents', 'user_id'],
            'games' => ['games', 'user_id'],
            'analyses' => ['game_analyses', 'game_id IN (SELECT id FROM games WHERE user_id = :user_id)'],
        ];
        $export = ['exported_at' => gmdate('c'), 'user_id' => $userId];
        foreach ($tables as $key => [$table, $where]) {
            $exists = Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name = :t", ['t' => $table]);
            if ($exists === null) {
                continue;
            }
            $isSubQuery = str_contains($where, 'IN (SELECT');
            $condition = $isSubQuery ? $where : "$where = :user_id";
            $export[$key] = Database::all("SELECT * FROM $table WHERE $condition", ['user_id' => $userId]);
        }
        return $export;
    }

    /** Suppression RGPD du compte et de toutes ses données (C4). */
    public function deleteAccount(int $userId): void
    {
        foreach (Database::all('SELECT storage_key FROM games WHERE user_id = :u', ['u' => $userId]) as $row) {
            if (!empty($row['storage_key'])) {
                $this->storage->delete((string) $row['storage_key']);
            }
        }
        $analysesExists = Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name = 'game_analyses'") !== null;
        if ($analysesExists) {
            Database::run('DELETE FROM game_analyses WHERE game_id IN (SELECT id FROM games WHERE user_id = :u)', ['u' => $userId]);
        }
        foreach (['games', 'player_profiles', 'linked_accounts', 'user_settings', 'consents'] as $table) {
            Database::run("DELETE FROM $table WHERE user_id = :u", ['u' => $userId]);
        }
        Database::run('DELETE FROM auth_tokens WHERE user_id = :u', ['u' => $userId]);
        Database::run('DELETE FROM users WHERE id = :u', ['u' => $userId]);
    }
}
