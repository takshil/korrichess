# api/ — Back-end Korrichess (PHP / Laravel 11)

API REST JSON consommée par l'application mobile (`app/`) et le site (`web/`).

## Périmètre actuel (issue #14 + moteur d'échecs + compte joueur)

- Routes versionnées sous **`/api/v1`** (`routes/api.php`).
- Endpoint `GET /api/v1/health` (sonde de disponibilité).
- **Moteur d'échecs maison** dans `app/Domain/Chess/` (`Board`, `Move`, `Pgn`) :
  représentation FEN, génération des coups légaux (roques, prise en passant,
  promotions), conversions SAN ↔ UCI (ambiguïtés incluses), détection
  échec/mat/pat, lecture et génération de PGN (commentaires et variantes
  ignorés, jetons inconnus rejetés). Prérequis technique des issues
  synchronisation (#3), bibliothèque (#4), rejeu/analyse (#5) et scan (#2).
- **Compte joueur, RGPD et paramètres** (issues #1, #18, #10) :
  - `POST /api/v1/auth/register`, `POST /api/v1/auth/login`,
    `POST /api/v1/auth/logout` — jetons opaques Sanctum, expiration 30 j
    (config `korrichess.auth.token_ttl_days`) ;
  - `GET/PATCH /api/v1/me` — profil joueur (nom, club, classement, date de
    naissance facultative) ;
  - `POST/DELETE /api/v1/me/linked-accounts` — plusieurs pseudos Lichess et
    Chess.com par joueur (C3) ;
  - `GET/PUT /api/v1/me/consents` — consentements révocables (`terms`,
    `parental_consent`, `data_retention`, `ocr_improvement`) ;
  - `GET/DELETE /api/v1/me/export` — export RGPD et suppression complète du
    compte (fichiers compris), clés étrangères en cascade ;
  - `GET/PATCH /api/v1/me/settings` — langue (fr/en), thème clair/sombre,
    styles d'échiquier et de pièces, notifications désactivables.
- Modèles Eloquent dans `app/Models/` (`User`, `PlayerProfile`,
  `LinkedAccount`, `UserSetting`) ; migrations versionnées dans
  `database/migrations/`.

## Conventions

- REST + JSON, versionnement de route `/api/v1` (aucune route non versionnée).
- Migrations versionnées dans `database/migrations/` ; jamais de logique
  métier dans les contrôleurs (services dédiés dans `app/Domain/`).
- Validation d'entrée via des FormRequests (`app/Http/Requests/V1/`) ;
  erreurs métier en clés françaises (`email_deja_utilise`, `deja_lie`…).
- Mots de passe hachés ; minimisation des données RGPD dès la conception
  (date de naissance facultative, aucun mot de passe en clair).

## Tests

- Suite `Unit` : moteur d'échecs (règles, roques, en passant, promotion,
  pat, ambiguïtés SAN, perft Kiwipete, PGN) — `php artisan test --testsuite Unit`.
- Suite `Feature` : API HTTP — santé, hachage du mot de passe, compte joueur
  (inscription, connexion, jetons, profil, pseudos liés, consentements,
  paramètres, export et suppression RGPD) — `php artisan test --testsuite Feature`.

## Installation (nécessite PHP ≥ 8.2 et Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```
