<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Authentification e-mail + mot de passe (C1), jetons Sanctum opaques
 * avec expiration (30 jours par défaut, cf. config/korrichess.php).
 */
final class AuthService
{
    /** @return array{status:int,body:array} */
    public function register(string $email, ?string $password, ?string $googleSub = null, ?string $appleSub = null): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 422, 'body' => ['error' => 'email_invalide']];
        }
        if (($password === null || $password === '') && $googleSub === null && $appleSub === null) {
            return ['status' => 422, 'body' => ['error' => 'authentifiant_requis']];
        }
        if (User::query()->where('email', $email)->exists()) {
            return ['status' => 409, 'body' => ['error' => 'email_deja_utilise']];
        }

        $name = explode('@', $email)[0];
        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->google_sub = $googleSub;
        $user->apple_sub = $appleSub;
        if ($password !== null && $password !== '') {
            $user->password = $password; // cast « hashed » du modèle
        }
        $user->save();

        $user->profile()->create(['display_name' => $name]);
        $user->setting()->create([]);

        return ['status' => 201, 'body' => ['user_id' => $user->id, 'token' => $this->issueToken($user)]];
    }

    /** @return array{status:int,body:array} */
    public function login(string $email, ?string $password): array
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();
        if ($user === null || $user->password === null || !Hash::check((string) $password, $user->password)) {
            return ['status' => 401, 'body' => ['error' => 'identifiants_invalides']];
        }

        return ['status' => 200, 'body' => ['user_id' => $user->id, 'token' => $this->issueToken($user)]];
    }

    /**
     * Révoque le jeton courant (le jeton porté par la requête).
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Jeton Sanctum opaque, expire après le nombre de jours configuré.
     */
    public function issueToken(User $user): string
    {
        $ttlDays = max(1, (int) config('korrichess.auth.token_ttl_days', 30));

        return $user->createToken('mobile', ['*'], now()->addDays($ttlDays))->plainTextToken;
    }
}
