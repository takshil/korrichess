# api/ — Back-end Korrichess (PHP / Laravel 11)

API REST JSON consommée par l'application mobile (`app/`) et le site (`web/`).

## Périmètre actuel (issue #14 + moteur + compte + sync + bibliothèque)

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
- **Synchronisation des parties** (issue #3) :
  - `POST /api/v1/sync` — import incrémental des parties d'un pseudo
    Lichess / Chess.com : seules les parties plus récentes que la dernière
    sync mémorisée sont récupérées, les plus récentes d'abord (L3/L4) ;
  - filtres de cadence (`time_controls`) et de période (`period_start`) (L5) ;
  - import idempotent : clé unique `(user_id, source, external_id)` dans
    `games`, les doublons sont ignorés (L6) ; un PGN invalide est ignoré
    sans faire échouer la sync (L8) ;
  - métadonnées extraites du PGN (adversaire et couleur déduits du pseudo,
    date, cadence, ouverture ECO, résultat, événement) et coups convertis en
    UCI via le moteur maison ;
  - sync automatique en file via `SyncPlatformJob` (L7), limite de débit par
    plateforme (`RateLimiter`) et clients plateforme remplaçables
    (`PlatformClient` ; aucun client HTTP réel pour l'instant, les tests
    injectent un client en mémoire).
- **Bibliothèque de parties** (issue #4) :
  - `GET /api/v1/games` — liste paginée avec source indiquée, filtres
    (adversaire, ouverture, résultat, couleur, cadence, tournoi, période),
    tri et recherche texte (B1–B3) ;
  - `GET /api/v1/games/{id}` — détail d'une partie, PGN et coups UCI inclus ;
  - `PATCH /api/v1/games/{id}` — note personnelle, étiquettes (max 20) et
    favori (B4) ;
  - `POST /api/v1/games/export` — export PGN d'une sélection, note et
    étiquettes injectées en en-têtes `KorriNote` / `KorriTags` (B5) ;
  - `POST /api/v1/games/search-position` — recherche par position FEN : les
    positions de chaque partie sont indexées à la demande dans
    `game_positions` via le moteur maison (B6) ;
  - isolation stricte par utilisateur sur toutes les routes.
- Modèles Eloquent dans `app/Models/` (`User`, `PlayerProfile`,
  `LinkedAccount`, `UserSetting`, `Game`) ; migrations versionnées dans
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
  paramètres, export et suppression RGPD), synchronisation (import
  incrémental, curseur, doublons, filtres, sync auto en file, API `/sync`)
  et bibliothèque (liste, filtres, tri, pagination, recherche texte, détail,
  annotations, export PGN, recherche par position, isolation, routes
  `/games`) — `php artisan test --testsuite Feature`.

## Installation (nécessite PHP ≥ 8.2 et Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```
