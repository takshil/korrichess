# ADR-003 — Déploiement de l'API sur un hébergement mutualisé FTP

- **Statut** : accepté (9 novembre 2026)
- **Décideur** : Cyril
- **Associée à l'issue** : CI de tests automatisés et de déploiement FTP

## Contexte

L'API Korrichess doit être testable et déployable sur un hébergement mutualisé
accessible uniquement par FTP (pas de SSH, pas de conteneurs, pas d'accès à la
configuration Apache globale). Les éléments contraignants :

- pas de connexion SSH : impossible de lancer `php artisan migrate` ou
  `composer install` sur le serveur ;
- la base de données se crée uniquement depuis le panel de l'hébergeur ;
- la racine web (souvent `www/` ou `public_html/`) est imposée, on ne peut pas
  la pointer vers `api/public/` ;
- l'hébergeur doit fournir PHP ≥ 8.2 (contrainte `composer.json`) et Apache
  avec `mod_rewrite`.

## Décision

1. **Pipeline GitLab CI en trois étapes** (`.gitlab-ci.yml`) :
   - `api:tests` : `composer install` + `php artisan test` sur image
     `php:8.3-cli`, base SQLite en mémoire — tourne sur chaque MR ;
   - `api:build` : `composer install --no-dev --optimize-autoloader`,
     artefact `api/` — uniquement sur `main` ;
   - `api:deploy` : déploiement de l'artefact — uniquement sur `main` et
     seulement si les secrets FTP sont configurés.
2. **Transfert par `lftp mirror --reverse --delete`** (FTPS si le serveur le
   supporte, contrôlé par `FTP_TLS`). L'application vit dans un dossier
   **hors racine web** (défaut `korrichess-api/`).
3. **Le `.env` de production est régénéré à chaque déploiement** à partir des
   variables CI/CD GitLab (Settings → CI/CD → Variables, masquées). Aucun
   secret ne transite par le dépôt.
4. **Point d'entrée minimal à la racine web** : la CI publie un `index.php`
   qui requiert `../korrichess-api/public/index.php`, plus un `.htaccess`
   qui route toutes les URL `/api/*` vers ce point d'entrée. La vitrine web
   (W1–W4) pourra donc vivre à la racine sans conflit.
5. **Migrations via une route protégée** : `POST /api/deploy/migrate`
   (entête `X-Deploy-Token`) appelle `Artisan::call('migrate --force')`.
   Sans jeton configuré ou avec un jeton invalide → 404. La CI l'appelle
   automatiquement après le transfert si `APP_URL` et `DEPLOY_TOKEN` sont
   définis. La base elle-même (schéma vierge) reste à créer dans le panel.
6. **Défense en profondeur** : un `.htaccess` à la racine de l'application
   bloque l'accès direct à `.env`, `.git`, `composer.json/lock` si elle se
   retrouve un jour sous la racine web.

## Conséquences

**Positives**

- chaque MR est validée par la suite de tests avant merge (première exécution
  réelle de la suite complète) ;
- déploiement reproductible en une seule commande : merge sur `main` ;
- tous les secrets restent côté GitLab, masqués dans les logs ;
- migrations applicables sans SSH, de façon contrôlée et auditable.

**Négatives / risques acceptés**

- pas de rollback atomique ni de zero-downtime : un transfert interrompu peut
  laisser l'application dans un état transitoire (acceptable pour un MVP) ;
- `composer.lock` n'est pas encore versionné : le job `api:tests` l'expose en
  artefact pour pouvoir le committer ensuite ;
- FTP(S) transmet les fichiers en clair côté TLS sur les serveurs qui ne
  supportent pas FTPS ;
- le jeton de déploiement permet de lancer les migrations à tout moment
  (limité par le throttling standard du groupe `api`) ; il doit rester
  masqué et protégé dans GitLab.

## Alternatives écartées

- **Déploiement par SSH/rsync** : pas disponible en mutualisé FTP seul.
- **Exécution des migrations depuis la CI vers MySQL distant** : l'accès
  distant à la base n'est pas garanti chez l'hébergeur.
- **Import manuel d'un dump SQL via le panel** : non reproductible et
  divergent des migrations Laravel.
