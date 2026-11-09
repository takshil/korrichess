# ADR-004 — Passage à Laravel 13

- **Statut** : accepté (9 novembre 2026)
- **Décideur** : Cyril
- **Remplace partiellement** : ADR-001 (choix de Laravel 11)
- **Associée aux issues** : #23 (passage à Laravel 13), #22 (CI)

## Contexte

ADR-001 avait retenu **Laravel 11** pour l'API. La mise en place de la CI
(#22) a révélé un fait nouveau : **Laravel 11 est hors support de sécurité
depuis le 12 mars 2026** et ne recevra plus jamais de correctif. Composer
2.11 bloque désormais l'installation de toutes les versions 11.x, affectées
par des avis de sécurité non corrigés, dont :

- **CVE-2026-114509** (CVSS 9.2) : contournement d'authentification du
  TokenGuard sur MySQL/MariaDB — corrigé uniquement en 12.68.0 et 13.29.0 ;
- **CVE-2026-48019** : injection CRLF dans la règle de validation `email`
  — corrigé uniquement en 12.60.0 et 13.10.0.

L'hébergeur mutualisé cible propose PHP ≥ 8.3, ce qui ouvre la branche 13.

## Décision

Passer l'API à **Laravel 13** :

- `laravel/framework ^13.29` — le plancher correspond au correctif de la
  CVE-2026-114509 ; les versions supérieures sont autorisées ;
- `php ^8.3` (contrainte de Laravel 13 ; PHP 8.2 est lui-même en fin de
  support de sécurité le 31 décembre 2026) ;
- `laravel/sanctum ^5.0`, `laravel/tinker ^3.0`, `phpunit/phpunit ^12.0`.

Laravel 12 a été écartée : elle ne reçoit plus que des correctifs de
sécurité et passe en fin de vie le 24 février 2027 — un second passage
aurait été nécessaire presque immédiatement.

## Conséquences

- **Positives** : correctifs de sécurité actifs jusqu'au 17 mars 2028 ;
  Composer cesse de bloquer l'installation ; le job `api:tests` de la CI
  valide la montée en version.
- **Négatives** : l'hébergeur doit fournir PHP ≥ 8.3 ; évolutions de
  rupture mineures à surveiller (le code métier étant jeune et la suite de
  tests complète, le risque est faible).
- **Suivi** : `composer.lock` sera versionné dès la première installation
  validée par la CI, pour des builds reproductibles.

## Relation avec ADR-001

Toutes les autres décisions d'ADR-001 (monorepo, API REST versionnée,
Sanctum, file de tâches en base) sont inchangées. Seule la version du
  cadre est révisée.
