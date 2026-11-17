#!/usr/bin/env bash
# Déploiement FTP mutualisé de l'API Korrichess — appelé par la CI (ADR-003).
# Tous les secrets proviennent des variables CI/CD GitLab, jamais du dépôt.
# Procédure de configuration : docs/deploiement-ftp.md
set -euo pipefail

: "${FTP_HOST:?Variable FTP_HOST manquante}"
: "${FTP_USER:?Variable FTP_USER manquante}"
: "${FTP_PASSWORD:?Variable FTP_PASSWORD manquante}"
: "${APP_KEY:?Variable APP_KEY manquante}"
: "${DB_HOST:?Variable DB_HOST manquante}"
: "${DB_DATABASE:?Variable DB_DATABASE manquante}"
: "${DB_USERNAME:?Variable DB_USERNAME manquant}"
: "${DB_PASSWORD:?Variable DB_PASSWORD manquante}"

FTP_PORT="${FTP_PORT:-21}"
FTP_TLS="${FTP_TLS:-true}"
# L'hébergeur FTP peut ne pas envoyer la chaîne complète (intermédiaire absent).
# Vérification activée par défaut ; FTP_TLS_VERIFY=false la désactive (phase de test).
TLS_VERIFY_CMD=""
FTP_TLS_VERIFY="${FTP_TLS_VERIFY:-true}"
case "$(echo "$FTP_TLS_VERIFY" | tr '[:upper:]' '[:lower:]')" in
    false|no|off|0)
        TLS_VERIFY_CMD="set ssl:verify-certificate no"
        echo ">>> Vérification du certificat FTP : DESACTIVEE (FTP_TLS_VERIFY=$FTP_TLS_VERIFY)"
        ;;
    *)
        echo ">>> Vérification du certificat FTP : active (FTP_TLS_VERIFY=$FTP_TLS_VERIFY)"
        ;;
esac
FTP_API_DIR="${FTP_API_DIR:-korrichess-api}"
FTP_BASE_PATH="${FTP_BASE_PATH:-}"
DOCROOT="${FTP_DOCROOT:-}"
API_DIR="${FTP_BASE_PATH%/}/$FTP_API_DIR"

ENV_FILE="$CI_PROJECT_DIR/env.production"

echo ">>> Génération du .env de production (depuis les variables CI/CD)"
{
    echo "APP_NAME=Korrichess"
    echo "APP_ENV=production"
    echo "APP_KEY=$APP_KEY"
    echo "APP_DEBUG=false"
    echo "APP_TIMEZONE=${APP_TIMEZONE:-UTC}"
    echo "APP_URL=${APP_URL:-}"
    echo "LOG_CHANNEL=stack"
    echo "LOG_LEVEL=${LOG_LEVEL:-debug}"
    echo "DB_CONNECTION=${DB_CONNECTION:-mysql}"
    echo "DB_HOST=$DB_HOST"
    echo "DB_PORT=${DB_PORT:-3306}"
    echo "DB_DATABASE=$DB_DATABASE"
    echo "DB_USERNAME=$DB_USERNAME"
    echo "DB_PASSWORD=$DB_PASSWORD"
    echo "SESSION_DRIVER=database"
    echo "QUEUE_CONNECTION=database"
    echo "CACHE_STORE=database"
    echo "AUTH_TOKEN_TTL_DAYS=${AUTH_TOKEN_TTL_DAYS:-30}"
    echo "KORRI_STOCKFISH_PATH=${KORRI_STOCKFISH_PATH:-stockfish}"
    echo "ANALYSIS_DEFAULT_DEPTH=${ANALYSIS_DEFAULT_DEPTH:-12}"
    echo "DEPLOY_TOKEN=${DEPLOY_TOKEN:-}"
} > "$ENV_FILE"

# Défense en profondeur : si l'application venait à se retrouver sous la
# racine web, bloquer l'accès direct aux fichiers sensibles.
cat > "$CI_PROJECT_DIR/api-protect.htaccess" <<'HTACCESS'
# Korrichess — protection des fichiers sensibles (ADR-003).
<FilesMatch "^\.env(\..*)?$|\.git|composer\.(json|lock)|phpunit\.xml">
    Require all denied
</FilesMatch>
HTACCESS

# Identifiants FTP passés via -u sur chaque appel lftp : sur l'image Alpine
# sans TTY, la variable LFTP_PASSWORD n'est pas relevée (GetPass() failed).

echo ">>> Envoi de l'API vers ftp://$FTP_HOST:$FTP_PORT/$API_DIR"
lftp -u "$FTP_USER","$FTP_PASSWORD" -p "$FTP_PORT" "$FTP_HOST" <<LFTP
set ftp:ssl-allow $FTP_TLS
$TLS_VERIFY_CMD
mirror --reverse --delete --no-perms --verbose --parallel=10 --exclude-glob '^\.env.*' "$CI_PROJECT_DIR/api" "$API_DIR"
put "$CI_PROJECT_DIR/api-protect.htaccess" -o "$API_DIR/.htaccess"
put "$ENV_FILE" -o "$API_DIR/.env"
quit
LFTP

if [ -n "$DOCROOT" ]; then
    echo ">>> Publication de la vitrine dans la racine web ($DOCROOT)"
    # Mirror sans --delete : la racine web peut contenir d'autres fichiers de
    # l'hébergeur ; index.php et .htaccess sont reposés juste après.
    lftp -u "$FTP_USER","$FTP_PASSWORD" -p "$FTP_PORT" "$FTP_HOST" <<LFTP
set ftp:ssl-allow $FTP_TLS
$TLS_VERIFY_CMD
mirror --reverse --no-perms --verbose --parallel=10 --exclude-glob 'README.md' "$CI_PROJECT_DIR/web" "$DOCROOT"
quit
LFTP

    echo ">>> Publication du point d'entrée dans la racine web ($DOCROOT)"
    # L'application vit hors racine web ; ce contrôleur minimal la sert
    # pour toutes les URL /api/* (la vitrine est déjà dans la racine).
    cat > "$CI_PROJECT_DIR/docroot-index.php" <<PHPEOF
<?php

// Korrichess — point d'entrée mutualisé, généré par la CI (ADR-003).
// Le contrôleur frontal Laravel vit hors racine web, dans ../$FTP_API_DIR.
require __DIR__ . '/../$FTP_API_DIR/public/index.php';
PHPEOF
    cat > "$CI_PROJECT_DIR/docroot-htaccess" <<'HTACCESS'
# Korrichess — routes /api/* vers l'application Laravel (ADR-003).
# La vitrine (index.html) est servie en priorité à la racine.
DirectoryIndex index.html index.php
ErrorDocument 404 /404.html
# Passe-plat CGI/FastCGI : sans lui, PHP ne voit pas l'en-tête Authorization.
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/api(/|$)
    RewriteRule ^(.*)$ index.php [L]
</IfModule>
HTACCESS

    lftp -u "$FTP_USER","$FTP_PASSWORD" -p "$FTP_PORT" "$FTP_HOST" <<LFTP
set ftp:ssl-allow $FTP_TLS
$TLS_VERIFY_CMD
put "$CI_PROJECT_DIR/docroot-index.php" -o "$DOCROOT/index.php"
put "$CI_PROJECT_DIR/docroot-htaccess" -o "$DOCROOT/.htaccess"
quit
LFTP
else
    echo ">>> FTP_DOCROOT non définie : racine web non gérée (voir docs/deploiement-ftp.md)"
fi

if [ -n "${APP_URL:-}" ] && [ -n "${DEPLOY_TOKEN:-}" ]; then
    echo ">>> Application des migrations Laravel"
    curl -fsS -X POST "$APP_URL/api/deploy/migrate?token=$DEPLOY_TOKEN" \
        -H "X-Deploy-Token: $DEPLOY_TOKEN" \
        -H "Authorization: Bearer $DEPLOY_TOKEN"
    echo
    echo ">>> Migrations appliquées."
else
    echo ">>> APP_URL ou DEPLOY_TOKEN absent : migrations non déclenchées automatiquement."
fi

echo ">>> Déploiement terminé."
