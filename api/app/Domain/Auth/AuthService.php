<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Auth;

use Korrichess\Api\Core\Database;

final class AuthService
{
    private const TOKEN_TTL_DAYS = 30;

    private function now(): string
    {
        return gmdate('c');
    }

    /** @return array{status:int,body:array} */
    public function register(string $email, ?string $password, ?string $googleSub, ?string $appleSub): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 422, 'body' => ['error' => 'email_invalide']];
        }
        if ($password === null && $googleSub === null && $appleSub === null) {
            return ['status' => 422, 'body' => ['error' => 'authentifiant_requis']];
        }
        if (Database::one('SELECT id FROM users WHERE email = :e', ['e' => $email]) !== null) {
            return ['status' => 409, 'body' => ['error' => 'email_deja_utilise']];
        }
        $hash = $password !== null ? password_hash($password, PASSWORD_BCRYPT) : null;
        Database::run(
            'INSERT INTO users (email, password_hash, google_sub, apple_sub, role, created_at, updated_at)
             VALUES (:email, :hash, :google, :apple, "player", :now, :now)',
            ['email' => $email, 'hash' => $hash, 'google' => $googleSub, 'apple' => $appleSub, 'now' => $this->now()],
        );
        $id = Database::lastId();
        Database::run(
            'INSERT INTO player_profiles (user_id, display_name, club, rating, birth_date, updated_at)
             VALUES (:id, :name, "", NULL, NULL, :now)',
            ['id' => $id, 'name' => explode('@', $email)[0], 'now' => $this->now()],
        );
        Database::run(
            'INSERT INTO user_settings (user_id, language, theme, board_style, piece_style, notify_analysis_done, notify_exercise_reminder, notify_games_synced, updated_at)
             VALUES (:id, "fr", "clair", "classique", "classique", 1, 1, 1, :now)',
            ['id' => $id, 'now' => $this->now()],
        );
        return ['status' => 201, 'body' => ['user_id' => $id, 'token' => $this->issueToken($id)]];
    }

    /** @return array{status:int,body:array} */
    public function login(string $email, ?string $password): array
    {
        $user = Database::one('SELECT * FROM users WHERE email = :e', ['e' => strtolower(trim($email))]);
        if ($user === null || $user['password_hash'] === null) {
            return ['status' => 401, 'body' => ['error' => 'identifiants_invalides']];
        }
        if (!password_verify((string) $password, (string) $user['password_hash'])) {
            return ['status' => 401, 'body' => ['error' => 'identifiants_invalides']];
        }
        return ['status' => 200, 'body' => ['user_id' => (int) $user['id'], 'token' => $this->issueToken((int) $user['id'])]];
    }

    public function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        Database::run(
            'INSERT INTO auth_tokens (token, user_id, created_at, expires_at) VALUES (:t, :u, :c, :e)',
            [
                't' => $token,
                'u' => $userId,
                'c' => $this->now(),
                'e' => gmdate('c', time() + self::TOKEN_TTL_DAYS * 86400),
            ],
        );
        return $token;
    }

    public function userIdForToken(?string $token): ?int
    {
        if ($token === null) {
            return null;
        }
        $row = Database::one('SELECT user_id, expires_at FROM auth_tokens WHERE token = :t', ['t' => $token]);
        if ($row === null || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return (int) $row['user_id'];
    }

    public function logout(string $token): void
    {
        Database::run('DELETE FROM auth_tokens WHERE token = :t', ['t' => $token]);
    }
}
