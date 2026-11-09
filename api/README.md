# api/ — Back-end Korrichess (PHP / Laravel 11)

API REST JSON consommée par l'application mobile (`app/`) et le site (`web/`).

## Périmètre actuel (V1 fonctionnelle : moteur, compte, sync, bibliothèque, saisie manuelle, analyse)

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
- **Saisie manuelle des parties de tournoi** (issue #25, tranches S5/S7) :
  - `POST /api/v1/games/manual/preview` — rejoue une liste de coups (UCI
    `e2e4` ou SAN `Nf3`, mélangés au besoin) et renvoie la position
    (FEN, trait, échec/mat/pat), les coups en SAN et les coups légaux
    suivants en UCI ; `invalid_ply` signale le premier coup injouable,
    le reste est renvoyé tel quel ;
  - `POST /api/v1/games/manual` — enregistre la partie avec sa fiche
    (date, adversaire, classement adverse, couleur, résultat, cadence,
    tournoi, ronde) ; avec `validate_moves: true` un coup illégal est
    refusé (`422 coup_invalide`, numéro du coup en cause) ;
  - avec `validate_moves: false` la partie est enregistrée telle quelle,
    marquée `needs_review` : la saisie d'origine est conservée dans
    `raw_moves` (utile quand la feuille de tournoi contient une erreur,
    S6 tolérant) et `moves_uci` ne garde que le préfixe rejouable ;
  - `PUT /api/v1/games/{id}/manual` — remplace la saisie d'une partie
    manuelle (404 `pas_une_saisie_manuelle` pour une partie importée) ;
  - le PGN de la partie est reconstruit via le moteur maison (`Pgn`).
- **Rejeu et analyse des parties** (issue #5, ADR-002) :
  - `GET /api/v1/games/{id}/replay` — rejeu : SAN, UCI et FEN de chaque
    position, prêts pour un échiquier interactif (A1) ;
  - `POST /api/v1/games/{id}/analyse` — analyse à la demande, asynchrone :
    le job `AnalyseGameJob` est placé en file (`php artisan queue:work`),
    résultats persistés dans `game_analyses` / `analysis_moves` (A10) ;
  - `GET /api/v1/games/{id}/analyse` — résultats : évaluation par position
    en centipions (A2), courbe d'évaluation (A3), classement des coups
    meilleur/bon/imprécision/erreur/gaffe (A4), moment décisif (A5),
    meilleur coup et variante (A6), explications en français (A7),
    ouverture et sortie de théorie (A9) ;
  - `POST /api/v1/games/{id}/explore` — essayer un autre coup depuis une
    position de la partie, sans la modifier (A8) ;
  - moteur d'analyse remplaçable via `EngineClient` : `UciEngine` (Stockfish
    en processus externe UCI, chemin `korrichess.analysis.stockfish_path`,
    jamais embarqué — GPL v3) ou `HeuristicEngine` déterministe (tests et
    environnements sans moteur), choisi dans `AppServiceProvider` ;
  - échecs isolés : une analyse qui plante passe en `failed` sans polluer
    les résultats précédents.
- Modèles Eloquent dans `app/Models/` (`User`, `PlayerProfile`,
  `LinkedAccount`, `UserSetting`, `Game`, `GameAnalysis`, `AnalysisMove`) ;
  migrations versionnées dans `database/migrations/`.

## Conventions

- REST + JSON, versionnement de route `/api/v1` (aucune route non versionnée).
- Migrations versionnées dans `database/migrations/` ; jamais de logique
  métier dans les contrôleurs (services dédiés dans `app/Domain/`).
- Validation d'entrée via des FormRequests (`app/Http/Requests/V1/`) ;
  erreurs métier en clés françaises (`email_deja_utilise`, `deja_lie`…).
- Mots de passe hachés ; minimisation des données RGPD dès la conception
  (date de naissance facultative, aucun mot de passe en clair).
- Travaux différés via les jobs Laravel (`app/Jobs/`), exécutés par
  `php artisan queue:work`.

## Tests

- Suite `Unit` : moteur d'échecs (règles, roques, en passant, promotion,
  pat, ambiguïtés SAN, perft Kiwipete, PGN) — `php artisan test --testsuite Unit`.
- Suite `Feature` : API HTTP — santé, hachage du mot de passe, compte joueur
  (inscription, connexion, jetons, profil, pseudos liés, consentements,
  paramètres, export et suppression RGPD), synchronisation (import
  incrémental, curseur, doublons, filtres, sync auto en file, API `/sync`),
  bibliothèque (liste, filtres, tri, pagination, recherche texte, détail,
  annotations, export PGN, recherche par position, isolation, routes
  `/games`), saisie manuelle (prévisualisation UCI et SAN, coup fautif
  signalé, fiche incomplète refusée, enregistrement vérifié ou tolérant
  `needs_review`, correction, partie d'autrui invisible, routes
  `/games/manual`) et analyse (rejeu, demande et exécution asynchrones, courbe,
  classements, explications, ouverture et sortie de théorie, exploration,
  isolation, routes `/replay`, `/analyse`, `/explore`)
  — `php artisan test --testsuite Feature`. Les tests n'ont pas besoin de
  Stockfish : `HeuristicEngine` garantit des résultats reproductibles
  (ADR-002).

## Installation (nécessite PHP ≥ 8.2 et Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

Analyse en production : installer Stockfish séparément (paquet système ou
téléchargement officiel) et renseigner `KORRI_STOCKFISH_PATH` (ADR-002),
puis lancer `php artisan queue:work` pour exécuter les analyses en file.
