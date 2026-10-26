# API Korrichess

API REST PHP (≥ 8.1) sans framework lourd : routeur maison PSR-4, migrations versionnées, file de tâches, stockage de fichiers.

## Structure

```
api/
├── app/Core/         # Router, Request, Database, Migration, Queue, QueueWorker, Storage
├── app/Domain/       # Logique métier par module (Auth, Games, ...)
├── migrations/       # Migrations versionnées AAAAMMJJ_HHMM_nom.php (auto-découvertes)
├── routes/           # Fichiers de définition de routes (auto-découverts)
├── public/index.php  # Point d'entrée HTTP
├── tests/            # Tests (runner maison : php tests/run.php)
├── bin/worker.php    # Worker de file de tâches
└── data/             # SQLite par défaut (ignoré par git)
```

## Conventions (AGENTS.md)

- REST, JSON, routes versionnées `/api/v1`.
- Migrations versionnées, auto-découvertes par `Migration::migrate()`.
- Aucune logique métier dans les contrôleurs : elle vit dans `app/Domain/`.

## Sans Composer (sandbox / CI)

`bootstrap-dev.php` fournit un autoloader maison (classmap sur `app/` et `migrations/`). En production, `composer install` utilisera `composer.json` (PSR-4 `Korrichess\Api\`).

## Variables d'environnement

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `KORRI_DB_DSN` | `sqlite:.../data/korrichess.sqlite` | DSN PDO (ex. `mysql:host=...;dbname=...`) |
| `KORRI_DB_USER` / `KORRI_DB_PASS` | — | Identifiants BDD |
| `KORRI_STORAGE_ROOT` | `api/data/storage` | Racine du stockage de fichiers |

## Migrations

Chaque migration est un fichier `migrations/AAAAMMJJ_HHMM_nom.php` définissant une classe
`Migration_<AAAAMMJJ>_<HHMM>` avec une méthode statique `up(PDO $pdo)`. Elles sont
auto-découvertes et appliquées dans l'ordre par `php bin/migrate.php`.

## File de tâches

`php bin/worker.php` exécute les jobs (analyses, synchronisations). Les types de jobs et
leurs gestionnaires sont enregistrés par les modules de `routes/` et `app/Domain/`.
