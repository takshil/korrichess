# ADR-001 — Choix de l'architecture back-end et du monorepo

- Statut : accepté
- Date : 2025 (issue #14 — Architecture back-end et infrastructure)
- Contexte : cahier des charges Korrichess v1.1, section 6.1

## Contexte

Korrichess couvre une application mobile (Android + iOS, base unique), un
site web connecté à la même API, une base SQL (parties PGN + données
structurées, analyses, statistiques, exercices), un stockage de fichiers
(photos de feuilles de partie), une file de tâches (analyses moteur,
synchronisations) et un service OCR. Le public cible est majoritairement
mineur : sécurité et RGPD par défaut.

## Options envisagées

1. **BFF par plateforme** (API mobile + API web séparées) : rejété —
   duplication de la logique métier et des migrations pour un MVP tenu par
   une petite équipe.
2. **Microservices dès le départ** : rejeté — coût opérationnel injustifié
   avant la V1 ; la file de tâches et l'OCR peuvent être isolés plus tard.
3. **API unique + monorepo** : une seule API REST versionnée consommée par
   mobile et web ; un dépôt unique avec `app/`, `api/`, `web/`, `docs/`.

## Décision

- **Monorepo** avec `app/` (Flutter), `api/` (PHP), `web/`, `docs/` (ADR).
- **PHP / Laravel 11** pour l'API : écosystème mature (migrations
  versionnées, file de tâches via base de données, Eloquent), aligné avec la
  contrainte « PHP » du cahier des charges. Symfony a été écarté pour la
  productivité « batteries incluses » de Laravel sur un MVP.
- **API REST + JSON**, versionnée sous `/api/v1` ; mobile et web sont des
  clients de cette unique API.
- **Authentification e-mail + mot de passe** via Laravel Sanctum (jetons) ;
  migration `users` dès maintenant, endpoints dans l'issue C1.
- **File de tâches** : `queue_connection=database` au départ — pas de Redis
  tant que la charge ne le justifie pas. Les analyses moteur et les
  synchronisations Lichess/Chess.com passeront par cette file (arrière-plan,
  reprises).

## Conséquences

- La montée en charge passe d'abord par le VPS (analyses Stockfish), pas par
  un découpage en services.
- Toute évolution de route passe par une nouvelle version (`/api/v2`), pas
  par des ruptures dans `/api/v1`.
- Le monorepo impose de documenter les conventions par dossier (README de
  `app/`, `api/`, `web/`) — fait dès cette issue.
- L'ajout d'un service OCR dédié reste possible plus tard (V2, issue #20)
  sans toucher à l'API publique.
