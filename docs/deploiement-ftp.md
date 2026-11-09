# Déploiement FTP mutualisé — guide pas à pas

Ce document décrit la mise en production de l'API Korrichess sur un
hébergement mutualisé accessible uniquement par FTP. Résumé des décisions :
voir `docs/adr/003-deploiement-mutualise-ftp.md`.

## 1. Vue d'ensemble

Chaque push sur `main` déclenche :

```
api:tests  →  api:build  →  api:deploy
(PHPUnit)     (vendor       (lftp mirror vers le serveur,
               --no-dev)     .env régénéré, migrations via
                              POST /api/deploy/migrate)
```

Le job `api:deploy` ne tourne que si la variable `FTP_HOST` existe : tant
qu'elle n'est pas configurée, le pipeline s'arrête après les tests, sans
échec.

## 2. Prérequis côté hébergeur

1. **PHP ≥ 8.3** activé pour le domaine (contrainte Laravel 13).
2. **Apache avec `mod_rewrite`** (le `.htaccess` publié route `/api/*`).
3. **Une base de données MySQL** créée depuis le panel, avec son
   utilisateur et son mot de passe. Note le nom d'hôte SQL du panel
   (ex. `mysql51-1pro.example.com` ou une IP), le nom de la base,
   l'utilisateur et le mot de passe : ce sont les futures variables
   `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
4. **Les identifiants FTP** : hôte, port, utilisateur, mot de passe.
5. Repère dans le client FTP **le nom exact de la racine web** (souvent
   `www` ou `public_html`) : ce sera `FTP_DOCROOT`.

> Aucun script ne peut créer la base à ta place : en mutualisé, seule
> l'interface de l'hébergeur le permet. Les **tables**, elles, sont créées
> par les migrations Laravel au premier déploiement.

## 3. Variables CI/CD GitLab

À créer dans **Settings → CI/CD → Variables** (bouton *Add variable*).
Elles sont injectées comme variables d'environnement dans les jobs, sans
jamais apparaître dans le dépôt.

| Variable | Rôle | Exemple | Masquée | Protégée |
|---|---|---|---|---|
| `FTP_HOST` | serveur FTP | `ftp.mon-hebergeur.fr` | non | oui |
| `FTP_PORT` | port FTP (défaut 21) | `21` | non | oui |
| `FTP_USER` | utilisateur FTP | `moncompte` | non | oui |
| `FTP_PASSWORD` | mot de passe FTP | `•••` | **oui** | **oui** |
| `FTP_TLS` | FTPS si le serveur le supporte | `true` | non | oui |
| `FTP_BASE_PATH` | préfixe FTP si la racine n'est pas `/` | *(vide)* | non | oui |
| `FTP_API_DIR` | dossier de l'app, hors racine web | `korrichess-api` | non | oui |
| `FTP_DOCROOT` | racine web | `www` | non | oui |
| `APP_KEY` | clé Laravel | `base64:…` | **oui** | **oui** |
| `APP_URL` | URL publique du site | `https://korrichess.fr` | non | oui |
| `DB_CONNECTION` | pilote (défaut `mysql`) | `mysql` | non | oui |
| `DB_HOST` | hôte SQL du panel | `mysql51-1pro…` | non | oui |
| `DB_PORT` | port SQL (défaut 3306) | `3306` | non | oui |
| `DB_DATABASE` | nom de la base | `moncompte_korrichess` | non | oui |
| `DB_USERNAME` | utilisateur SQL | `moncompte_api` | non | oui |
| `DB_PASSWORD` | mot de passe SQL | `•••` | **oui** | **oui** |
| `DEPLOY_TOKEN` | jeton des migrations | `64 hex` | **oui** | **oui** |

Facultatives : `APP_TIMEZONE`, `LOG_LEVEL`, `AUTH_TOKEN_TTL_DAYS`,
`KORRI_STOCKFISH_PATH`, `ANALYSIS_DEFAULT_DEPTH`.

Générer les deux secrets :

```sh
# APP_KEY
printf 'base64:%s\n' "$(openssl rand -base64 32)"
# DEPLOY_TOKEN
openssl rand -hex 32
```

Notes :
- **Masquée** : la valeur n'apparaît jamais dans les logs des jobs.
  Si GitLab refuse de masquer un mot de passe (caractères exotiques),
  laisse la case décochée mais coche bien **Protégée**.
- **Protégée** : la variable n'est exposée que sur les branches protégées
  (`main`), jamais sur les MR ou les branches de travail — c'est exactement
  ce qu'il faut puisque `api:deploy` ne tourne que sur `main`.
- Le job de tests n'utilise **aucun secret** : il génère sa propre
  `APP_KEY` jetable sur une base SQLite en mémoire.

## 4. Premier déploiement

1. Configurer toutes les variables ci-dessus.
2. Pousser (ou merger) sur `main`.
3. Suivre le pipeline dans **Build → Pipelines** : `api:tests` doit être
   vert (première exécution réelle de la suite complète), puis `api:build`
   et `api:deploy`.
4. Vérifier :

```sh
curl -i https://ton-domaine/up              # → 200 OK
curl -i https://ton-domaine/api/v1/health  # → JSON de santé
```

5. Les migrations ont été appliquées automatiquement en fin de job. Pour
   les relancer à la main à tout moment :

```sh
curl -X POST https://ton-domaine/api/deploy/migrate \
     -H "X-Deploy-Token: <DEPLOY_TOKEN>"
```

## 5. Arborescence obtenue sur le serveur

```
/                        ← racine FTP
├── www/                 ← racine web (FTP_DOCROOT)
│   ├── index.php        ← point d'entrée : requiert ../korrichess-api/public/index.php
│   └── .htaccess        ← route /api/* vers index.php
└── korrichess-api/      ← application complète, hors racine web
    ├── .env             ← régénéré à chaque déploiement depuis les variables CI
    ├── .htaccess        ← défense en profondeur (bloque .env, .git…)
    ├── app/ bootstrap/ config/ database/ routes/ vendor/ storage/ public/
```

La vitrine web (W1–W4) viendra se poser dans `www/` sans toucher au
comportement de `/api/*`.

## 6. Dépannage

| Symptôme | Piste |
|---|---|
| Page blanche / erreur 500 partout | Vérifier la version PHP du domaine (≥ 8.2) et que `storage/` et `bootstrap/cache/` sont présents |
| `lftp: Fatal error: Connection lost` | `FTP_TLS` mal réglé : essayer `false`, ou vérifier le port (21 vs 990) |
| 404 sur `/api/v1/health` | `FTP_DOCROOT` mal nommé, ou `mod_rewrite` désactivé chez l'hébergeur |
| « could not find driver » sur la base | L'hébergeur ne propose pas `pdo_mysql` : changer d'offre |
| Migrations refusées (404) | `DEPLOY_TOKEN` absent du `.env` ou différent de la variable GitLab |
| Déploiement lent | Normal : `vendor/` transite par FTP à chaque fois ; l'option `--delete` ne renvoie que les différences |
