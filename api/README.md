# api/ — Back-end Korrichess (PHP / Laravel 11)

API REST JSON consommée par l'application mobile (`app/`) et le site (`web/`).

## Périmètre actuel (issue #14 : squelette uniquement)

- Routes versionnées sous **`/api/v1`** (`routes/api.php`).
- Endpoint `GET /api/v1/health` (sonde de disponibilité).
- Migration `users` : compte e-mail + mot de passe (haché), locale par défaut `fr`.
- Modèle `App\Models\User` (hachage automatique du mot de passe, champs masqués).
- Espace de nommage des contrôleurs : `App\Http\Controllers\Api\V1\*`.

L'authentification (enregistrement, connexion, jetons Sanctum) et les
ressources métier (parties, analyses, exercices) arrivent dans leurs
issues dédiées — rien n'est implémenté au-delà du squelette.

## Conventions

- REST + JSON, versionnement de route `/api/v1` (aucune route non versionnée).
- Migrations versionnées dans `database/migrations/` ; jamais de logique
  métier dans les contrôleurs (services dédiés).
- Mots de passe hachés ; minimisation des données RGPD dès la conception.

## Installation (nécessite PHP ≥ 8.2 et Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```
