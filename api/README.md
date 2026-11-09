# api/ — Back-end Korrichess (PHP / Laravel 11)

API REST JSON consommée par l'application mobile (`app/`) et le site (`web/`).

## Périmètre actuel (issue #14 + moteur d'échecs)

- Routes versionnées sous **`/api/v1`** (`routes/api.php`).
- Endpoint `GET /api/v1/health` (sonde de disponibilité).
- Migration `users` : compte e-mail + mot de passe (haché), locale par défaut `fr`.
- Modèle `App\Models\User` (hachage automatique du mot de passe, champs masqués).
- Espace de nommage des contrôleurs : `App\Http\Controllers\Api\V1\*`.
- **Moteur d'échecs maison** dans `app/Domain/Chess/` (`Board`, `Move`, `Pgn`) :
  représentation FEN, génération des coups légaux (roques, prise en passant,
  promotions), conversions SAN ↔ UCI (ambiguïtés incluses), détection
  échec/mat/pat, lecture et génération de PGN (commentaires et variantes
  ignorés, jetons inconnus rejetés). Prérequis technique des issues
  synchronisation (#3), bibliothèque (#4), rejeu/analyse (#5) et scan (#2).

L'authentification (enregistrement, connexion, jetons Sanctum) et les
ressources exposées (parties, analyses, exercices) arrivent dans leurs
issues dédiées.

## Conventions

- REST + JSON, versionnement de route `/api/v1` (aucune route non versionnée).
- Migrations versionnées dans `database/migrations/` ; jamais de logique
  métier dans les contrôleurs (services dédiés).
- Domaine métier dans `app/Domain/` : classes pures, sans dépendance au
  framework (testables isolément).
- Mots de passe hachés ; minimisation des données RGPD dès la conception.

## Tests

- Suite `Unit` : moteur d'échecs (règles, roques, en passant, promotion,
  pat, ambiguïtés SAN, perft Kiwipete, PGN) — `php artisan test --testsuite Unit`.
- Suite `Feature` : API HTTP — `php artisan test --testsuite Feature`.

## Installation (nécessite PHP ≥ 8.2 et Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```
