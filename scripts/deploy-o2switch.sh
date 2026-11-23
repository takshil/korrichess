#!/usr/bin/env bash
# Installation et mise à jour de Korrichess sur o2switch (cPanel / Git Version Control).
# Crée / met à jour deux répertoires :
#   - <DEPLOY_BASE>/korrichess-api : API Laravel (hors racine web)
#   - <DEPLOY_BASE>/www            : site public (web/) + point d'entrée /api/*
#
# Utilisation :
#   bash scripts/deploy-o2switch.sh
#   bash scripts/deploy-o2switch.sh --base=/home/moncompte/mon-site
#   DEPLOY_BASE=/home/moncompte/mon-site bash scripts/deploy-o2switch.sh
#
# Configuration persistante optionnelle (hors Git) : ~/.korrichess-deploy.env

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Charger une config serveur locale si présente (utile avec cPanel Git Version Control)
if [ -f "${HOME:-}/.korrichess-deploy.env" ]; then
    # shellcheck disable=SC1091
    source "${HOME}/.korrichess-deploy.env"
fi

DEPLOY_BASE="${DEPLOY_BASE:-${HOME:-$(dirname "$REPO_DIR")}}"
API_DIR="${API_DIR:-}"
WEB_DIR="${WEB_DIR:-}"
PHP_BIN="${PHP_BIN:-}"
SKIP_COMPOSER="${SKIP_COMPOSER:-false}"
SKIP_MIGRATE="${SKIP_MIGRATE:-false}"

usage() {
    cat <<'EOF'
Usage: deploy-o2switch.sh [options]

Options :
  --base=CHEMIN       Dossier parent contenant korrichess-api et www (défaut: $HOME)
  --api-dir=CHEMIN    Dossier cible de l'API (défaut: <base>/korrichess-api)
  --web-dir=CHEMIN    Dossier cible du site public (défaut: <base>/www)
  --php=BINAIRE       Chemin explicite vers PHP >= 8.3
  --skip-composer     Ne pas exécuter composer install
  --skip-migrate      Ne pas exécuter php artisan migrate --force
  -h, --help          Afficher cette aide
EOF
}

for arg in "$@"; do
    case "$arg" in
        --base=*)
            DEPLOY_BASE="${arg#*=}"
            ;;
        --api-dir=*)
            API_DIR="${arg#*=}"
            ;;
        --web-dir=*)
            WEB_DIR="${arg#*=}"
            ;;
        --php=*)
            PHP_BIN="${arg#*=}"
            ;;
        --skip-composer)
            SKIP_COMPOSER="true"
            ;;
        --skip-migrate)
            SKIP_MIGRATE="true"
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Option inconnue : $arg" >&2
            usage >&2
            exit 1
            ;;
    esac
done

API_DIR="${API_DIR:-${DEPLOY_BASE%/}/korrichess-api}"
WEB_DIR="${WEB_DIR:-${DEPLOY_BASE%/}/www}"

echo "=== Déploiement Korrichess (o2switch) ==="
echo "Dépôt source : $REPO_DIR"
echo "Dossier API  : $API_DIR"
echo "Dossier web  : $WEB_DIR"

# 1. Détection de PHP >= 8.3
is_php_83_or_newer() {
    local bin="$1"
    [ -n "$bin" ] && [ -x "$bin" ] || return 1
    "$bin" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' >/dev/null 2>&1
}

detect_php() {
    local candidates=()
    if [ -n "$PHP_BIN" ]; then
        candidates+=("$PHP_BIN")
    fi
    candidates+=(
        "/opt/cpanel/ea-php84/root/usr/bin/php"
        "/opt/cpanel/ea-php83/root/usr/bin/php"
        "/opt/alt/php84/usr/bin/php"
        "/opt/alt/php83/usr/bin/php"
        "/usr/local/bin/ea-php83"
        "$(command -v php84 2>/dev/null || true)"
        "$(command -v php83 2>/dev/null || true)"
        "$(command -v php8.3 2>/dev/null || true)"
        "$(command -v php 2>/dev/null || true)"
    )

    local candidate
    for candidate in "${candidates[@]}"; do
        if is_php_83_or_newer "$candidate"; then
            echo "$candidate"
            return 0
        fi
    done
    return 1
}

if ! PHP_BIN="$(detect_php)"; then
    echo "ERREUR : aucun binaire PHP >= 8.3 trouvé." >&2
    echo "Précise le chemin via --php=/opt/cpanel/ea-php83/root/usr/bin/php ou PHP_BIN." >&2
    exit 1
fi
echo "PHP utilisé  : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# 2. Préparation et synchronisation de korrichess-api
echo ">>> Synchronisation de l'API vers $API_DIR"
mkdir -p "$API_DIR"

if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
        --exclude='.git/' \
        --exclude='.env' \
        --exclude='.env.*' \
        --exclude='storage/' \
        --exclude='vendor/' \
        --exclude='tests/' \
        --exclude='phpunit.xml' \
        --exclude='database/*.sqlite' \
        --exclude='.htaccess' \
        --exclude='.composer.phar' \
        "$REPO_DIR/api/" "$API_DIR/"
else
    echo ">>> rsync absent : copie via tar (les anciens fichiers supprimés ne seront pas purgés)"
    tar -C "$REPO_DIR/api" \
        --exclude='.git' \
        --exclude='.env' \
        --exclude='.env.*' \
        --exclude='storage' \
        --exclude='vendor' \
        --exclude='tests' \
        --exclude='phpunit.xml' \
        --exclude='database/*.sqlite' \
        -cf - . | tar -C "$API_DIR" -xf -
fi

mkdir -p \
    "$API_DIR/storage/app/public" \
    "$API_DIR/storage/framework/cache/data" \
    "$API_DIR/storage/framework/sessions" \
    "$API_DIR/storage/framework/views" \
    "$API_DIR/storage/logs" \
    "$API_DIR/bootstrap/cache"

# Défense en profondeur si korrichess-api venait à être exposé sous la racine web
cat > "$API_DIR/.htaccess" <<'HTACCESS'
# Korrichess — protection des fichiers sensibles.
<FilesMatch "^\.env(\..*)?$|\.git|composer\.(json|lock)|phpunit\.xml|\.composer\.phar">
    Require all denied
</FilesMatch>
HTACCESS

# 3. Initialisation du .env uniquement s'il n'existe pas déjà sur le serveur
ENV_CREATED="false"
if [ -f "$API_DIR/.env" ]; then
    echo ">>> Fichier .env existant conservé : $API_DIR/.env"
else
    echo ">>> Création initiale de $API_DIR/.env (mode production)"
    cp "$REPO_DIR/api/.env.example" "$API_DIR/.env"
    "$PHP_BIN" -r '
        $file = $argv[1];
        $content = file_get_contents($file);
        $replacements = [
            "/^APP_ENV=.*/m" => "APP_ENV=production",
            "/^APP_DEBUG=.*/m" => "APP_DEBUG=false",
            "/^DB_CONNECTION=.*/m" => "DB_CONNECTION=mysql",
            "/^DB_DATABASE=.*/m" => "DB_DATABASE=",
        ];
        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        if (!preg_match("/^DB_HOST=/m", $content)) {
            $content = preg_replace("/^DB_CONNECTION=.*/m", "$0\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_USERNAME=\nDB_PASSWORD=", $content);
        }
        file_put_contents($file, $content);
    ' "$API_DIR/.env"
    ENV_CREATED="true"
fi

# 4. Dépendances Composer (--no-dev)
if [ "$SKIP_COMPOSER" != "true" ]; then
    COMPOSER_CMD=()
    for bin in \
        "$(command -v composer 2>/dev/null || true)" \
        "/opt/cpanel/composer/bin/composer" \
        "/usr/local/bin/composer" \
        "${HOME:-}/bin/composer"
    do
        if [ -n "$bin" ] && [ -x "$bin" ]; then
            COMPOSER_CMD=("$PHP_BIN" "$bin")
            break
        fi
    done

    if [ ${#COMPOSER_CMD[@]} -eq 0 ]; then
        if [ ! -f "$API_DIR/.composer.phar" ]; then
            echo ">>> Composer introuvable : téléchargement de composer.phar dans $API_DIR/.composer.phar"
            "$PHP_BIN" -r "copy('https://getcomposer.org/installer', '$API_DIR/.composer-setup.php');"
            "$PHP_BIN" "$API_DIR/.composer-setup.php" --install-dir="$API_DIR" --filename=.composer.phar
            rm -f "$API_DIR/.composer-setup.php"
        fi
        COMPOSER_CMD=("$PHP_BIN" "$API_DIR/.composer.phar")
    fi

    echo ">>> Installation des dépendances PHP (--no-dev)"
    (
        cd "$API_DIR"
        "${COMPOSER_CMD[@]}" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    )
fi

# Générer APP_KEY uniquement s'il est vide dans le .env
if ! grep -qE '^APP_KEY=.+' "$API_DIR/.env"; then
    if [ -f "$API_DIR/vendor/autoload.php" ]; then
        echo ">>> Génération de APP_KEY"
        (
            cd "$API_DIR"
            "$PHP_BIN" artisan key:generate --force
        )
    else
        echo ">>> Génération de APP_KEY (sans vendor)"
        NEW_KEY="base64:$("$PHP_BIN" -r 'echo base64_encode(random_bytes(32));')"
        "$PHP_BIN" -r '
            $f = $argv[1]; $k = $argv[2];
            $c = file_get_contents($f);
            $c = preg_replace("/^APP_KEY=.*/m", "APP_KEY=" . $k, $c);
            file_put_contents($f, $c);
        ' "$API_DIR/.env" "$NEW_KEY"
    fi
fi

# 5. Migrations et caches Laravel
DB_NAME_VAL="$(grep -E '^DB_DATABASE=' "$API_DIR/.env" | head -n1 | cut -d'=' -f2- | tr -d '\r"' || true)"
DB_CONN_VAL="$(grep -E '^DB_CONNECTION=' "$API_DIR/.env" | head -n1 | cut -d'=' -f2- | tr -d '\r"' || true)"

if [ -f "$API_DIR/vendor/autoload.php" ]; then
    if [ "$SKIP_MIGRATE" != "true" ]; then
        if [ "$DB_CONN_VAL" = "mysql" ] && [ -z "$DB_NAME_VAL" ]; then
            echo ">>> AVERTISSEMENT : DB_DATABASE est vide dans $API_DIR/.env."
            echo "    Renseigne tes identifiants MySQL cPanel dans $API_DIR/.env puis relance :"
            echo "    $PHP_BIN $API_DIR/artisan migrate --force"
        else
            echo ">>> Application des migrations Laravel"
            if ! (cd "$API_DIR" && "$PHP_BIN" artisan migrate --force); then
                echo ">>> AVERTISSEMENT : échec de 'artisan migrate --force' (vérifie la config BDD dans $API_DIR/.env)." >&2
            fi
        fi
    fi

    echo ">>> Mise en cache de la configuration et des routes Laravel"
    (
        cd "$API_DIR"
        "$PHP_BIN" artisan config:cache
        "$PHP_BIN" artisan route:cache
    )
else
    echo ">>> vendor/autoload.php absent : étapes artisan ignorées."
fi

chmod -R u+rwX,go+rX "$API_DIR/storage" "$API_DIR/bootstrap/cache" 2>/dev/null || true

# 6. Synchronisation du site public vers www/
echo ">>> Publication du site public vers $WEB_DIR"
mkdir -p "$WEB_DIR"

if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
        --exclude='.well-known/' \
        --exclude='cgi-bin/' \
        --exclude='error_log' \
        --exclude='index.php' \
        --exclude='.htaccess' \
        --exclude='README.md' \
        "$REPO_DIR/web/" "$WEB_DIR/"
else
    tar -C "$REPO_DIR/web" \
        --exclude='README.md' \
        -cf - . | tar -C "$WEB_DIR" -xf -
fi

# Calcul du chemin relatif entre WEB_DIR et API_DIR/public/index.php
REL_API_ENTRY="$("$PHP_BIN" -r '
    $web = realpath($argv[1]);
    $api = realpath($argv[2]) . "/public/index.php";
    if ($web === false || realpath($argv[2]) === false) {
        echo "../korrichess-api/public/index.php";
        exit(0);
    }
    $from = explode("/", trim($web, "/"));
    $to = explode("/", trim($api, "/"));
    while (count($from) && count($to) && $from[0] === $to[0]) {
        array_shift($from);
        array_shift($to);
    }
    echo str_repeat("../", count($from)) . implode("/", $to);
' "$WEB_DIR" "$API_DIR")"

cat > "$WEB_DIR/index.php" <<PHPEOF
<?php

// Korrichess — point d'entrée o2switch généré par scripts/deploy-o2switch.sh.
require __DIR__ . '/$REL_API_ENTRY';
PHPEOF

cat > "$WEB_DIR/.htaccess" <<'HTACCESS'
# Korrichess — routes /api/* et /up vers l'application Laravel, vitrine servie en priorité.
DirectoryIndex index.html index.php
ErrorDocument 404 /404.html

# Passe-plat CGI/FastCGI pour l'en-tête Authorization (jetons Sanctum).
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_URI} ^/(api(/|$)|up$)
    RewriteRule ^(.*)$ index.php [L]
</IfModule>
HTACCESS

echo "=== Déploiement terminé ==="
if [ "$ENV_CREATED" = "true" ]; then
    echo "PROCHAINE ÉTAPE (première installation) :"
    echo "1. Édite $API_DIR/.env pour renseigner APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD."
    echo "2. Lance les migrations : $PHP_BIN $API_DIR/artisan migrate --force"
fi
