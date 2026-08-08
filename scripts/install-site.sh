#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ ! -f .env ]; then
    echo ".env is missing. Run ./scripts/bootstrap-iomad.sh first."
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

POSTGRES_DB="${POSTGRES_DB:-iomad}"
POSTGRES_USER="${POSTGRES_USER:-iomad}"
IOMAD_DB_PREFIX="${IOMAD_DB_PREFIX:-mdl_}"
IOMAD_THEME="${IOMAD_THEME:-boost}"
IOMAD_AUTH_PLUGINS="${IOMAD_AUTH_PLUGINS:-email,iomadoidc}"

configure_runtime() {
    docker compose exec -T iomad php admin/cli/cfg.php \
        --name=auth \
        --set="${IOMAD_AUTH_PLUGINS}"
    if docker compose exec -T iomad test -f public/local/iomadconnect/version.php; then
        docker compose exec -T iomad php admin/cli/cfg.php \
            --component=local_iomadconnect \
            --name=authmethod \
            --set=iomadoidc
    fi
    ./scripts/configure-mailpit.sh
}

docker compose up -d --wait db redis mailpit iomad

if ! docker compose exec -T iomad test -f "public/theme/${IOMAD_THEME}/version.php"; then
    echo "Configured theme ${IOMAD_THEME} is not installed; using Boost."
    IOMAD_THEME=boost
fi

if ! docker compose exec -T iomad test -f vendor/autoload.php; then
    docker compose exec -T iomad composer --working-dir=/var/www/iomad install \
        --no-interaction \
        --prefer-dist \
        --no-dev \
        --optimize-autoloader
fi

CONFIG_TABLE="${IOMAD_DB_PREFIX}config"
if docker compose exec -T db psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -tAc "select to_regclass('${CONFIG_TABLE}')" | grep -q "${CONFIG_TABLE}"; then
    echo "IOMAD database already appears to be installed."
    configure_runtime
    docker compose up -d cron
    exit 0
fi

docker compose exec -T iomad php admin/cli/install_database.php \
    --agree-license \
    --adminuser="${IOMAD_ADMIN_USER:-admin}" \
    --adminpass="${IOMAD_ADMIN_PASSWORD:-Admin123!ChangeMe}" \
    --adminemail="${IOMAD_ADMIN_EMAIL:-admin@example.local}" \
    --fullname="${IOMAD_SITE_FULLNAME:-IOMAD Learning Local}" \
    --shortname="${IOMAD_SITE_SHORTNAME:-iomadlearning}"

if [[ "${IOMAD_WWWROOT:-http://localhost:18080}" == http://* ]]; then
    docker compose exec -T iomad php admin/cli/cfg.php --name=cookiesecure --set=0
fi

docker compose exec -T iomad php admin/cli/cfg.php --name=forcelogin --set=0
docker compose exec -T iomad php admin/cli/cfg.php --name=defaulthomepage --set=1
docker compose exec -T iomad php admin/cli/cfg.php --name=enablemyhome --set=1
docker compose exec -T iomad php admin/cli/cfg.php --name=enablemycourses --set=1
docker compose exec -T iomad php admin/cli/upgrade.php --non-interactive
docker compose exec -T iomad php admin/cli/cfg.php --name=theme --set="${IOMAD_THEME}"
docker compose exec -T iomad php admin/cli/build_theme_css.php --themes="${IOMAD_THEME}" --direction=ltr --verbose || true
docker compose exec -T iomad php admin/cli/purge_caches.php
configure_runtime

docker compose up -d cron

echo "IOMAD is installed at ${IOMAD_WWWROOT:-http://localhost:18080}"
