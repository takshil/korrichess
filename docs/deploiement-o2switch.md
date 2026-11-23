# Déploiement sur o2switch via Git™ Version Control

Ce guide explique comment installer et mettre à jour Korrichess sur un hébergement **o2switch** en utilisant **cPanel → Git™ Version Control** et le script [`scripts/deploy-o2switch.sh`](../scripts/deploy-o2switch.sh).

## 1. Architecture installée sur le serveur

Le script déploie l'application en deux répertoires séparés :

```text
<DEPLOY_BASE>/              (par défaut : /home/<utilisateur>)
├── korrichess-api/         ← API Laravel complète (hors racine web)
│   ├── .env                ← conservé entre les déploiements
│   ├── .htaccess           ← bloque l'accès direct (.env, composer.json…)
│   ├── app/ bootstrap/ config/ database/ public/ routes/ storage/ vendor/
└── www/                    ← site public (vitrine + espace joueur)
    ├── index.html          ← page d'accueil vitrine
    ├── espace/             ← espace joueur web
    ├── index.php           ← requiert ../korrichess-api/public/index.php
    └── .htaccess           ← route /api/* et /up vers index.php
```

> Sur o2switch, `www` est généralement un lien symbolique vers `public_html` pour le domaine principal : le script préserve ce lien et synchronise directement son contenu.

## 2. Prérequis dans cPanel (à faire une seule fois)

1. **Version PHP** : dans **cPanel → Sélectionner une version de PHP** (ou MultiPHP Manager), activer **PHP 8.3** (ou 8.4) pour le domaine.
2. **Base de données MySQL** : dans **cPanel → Bases de données MySQL**, créer :
   - une base de données (ex. `abcd1234_korrichess`),
   - un utilisateur SQL avec son mot de passe,
   - associer l'utilisateur à la base avec **Tous les privilèges**.

## 3. Cloner le dépôt dans Git™ Version Control

1. Ouvrir **cPanel → Git™ Version Control → Create**.
2. Activer **Clone a Repository**.
3. **Clone URL** : `https://github.com/takshil/korrichess.git`
4. **Repository Path** : par exemple `/home/<utilisateur>/repositories/korrichess`.
5. **Repository Name** : `korrichess`.
6. Valider avec **Create**.

## 4. Premier déploiement

1. Dans **cPanel → Git™ Version Control**, cliquer sur **Manage** sur le dépôt `korrichess`.
2. Aller dans l'onglet **Pull or Deploy** puis cliquer sur **Deploy HEAD Commit**.
   cPanel exécute alors [`.cpanel.yml`](../.cpanel.yml), qui lance `scripts/deploy-o2switch.sh`.
3. *(Alternative en SSH / Terminal cPanel)* :
   ```bash
   cd ~/repositories/korrichess
   bash scripts/deploy-o2switch.sh
   ```

Lors de ce premier passage, le script :
- crée `korrichess-api/` et installe les dépendances PHP (`composer install --no-dev`),
- génère `korrichess-api/.env` avec une `APP_KEY` toute neuve,
- copie le site public dans `www/` avec `index.php` et `.htaccess`.

## 5. Configurer `.env` et lancer les migrations

1. Éditer `~/korrichess-api/.env` (via **Gestionnaire de fichiers** ou SSH) et renseigner :
   ```dotenv
   APP_URL=https://ton-domaine.fr
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=abcd1234_korrichess
   DB_USERNAME=abcd1234_api
   DB_PASSWORD=ton-mot-de-passe
   ```
2. Appliquer les migrations (en SSH / Terminal cPanel) :
   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php ~/korrichess-api/artisan migrate --force
   ```
   *(Ou relancer **Deploy HEAD Commit** dans cPanel : les migrations s'appliquent automatiquement dès que `DB_DATABASE` est renseigné.)*

3. Vérifier le bon fonctionnement :
   - `https://ton-domaine.fr/` → vitrine Korrichess
   - `https://ton-domaine.fr/up` → `200 OK`
   - `https://ton-domaine.fr/api/v1/health` → JSON de santé

## 6. Mises à jour suivantes

À chaque mise à jour sur GitHub :
1. Ouvrir **cPanel → Git™ Version Control → Manage → Pull or Deploy**.
2. Cliquer sur **Update from Remote**, puis **Deploy HEAD Commit**.

Le script met à jour `korrichess-api/` et `www/`, conserve `.env` et `storage/`, exécute `composer install --no-dev`, applique les nouvelles migrations (`artisan migrate --force`) et régénère le cache Laravel.

## 7. Cas d'un domaine compagnon / sous-domaine

Si le site vit dans un dossier spécifique (ex. `/home/<utilisateur>/korrichess.mondomaine.fr/`), créer le fichier `~/.korrichess-deploy.env` sur le serveur (hors Git) :

```bash
DEPLOY_BASE="/home/<utilisateur>/korrichess.mondomaine.fr"
# Ou explicitement :
# API_DIR="/home/<utilisateur>/korrichess-api"
# WEB_DIR="/home/<utilisateur>/korrichess.mondomaine.fr/www"
```

Le script lit automatiquement `~/.korrichess-deploy.env` lors de chaque **Deploy HEAD Commit**.

## 8. Tâche cron (synchronisations et file d'attente)

Pour activer le scheduler Laravel (sync nocturne et traitement des jobs en arrière-plan), voir [`docs/hebergement-cron.md`](./hebergement-cron.md).
