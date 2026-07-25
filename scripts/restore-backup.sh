#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

BACKUP_DIR="${1:-}"
CONFIRM="${2:-}"
stale_dataroot=""

on_error() {
    exit_code=$?
    trap - ERR
    echo "Restore failed. Cron remains stopped; do not disable maintenance until recovery is resolved." >&2
    if [ -n "${stale_dataroot}" ] && [ -d "${stale_dataroot}" ]; then
        echo "The pre-restore dataroot is preserved at ${stale_dataroot}." >&2
    fi
    exit "${exit_code}"
}

trap on_error ERR

if [ -z "${BACKUP_DIR}" ] || [ "${CONFIRM}" != "--yes" ]; then
    cat <<'USAGE'
Usage:
  ./scripts/restore-backup.sh backups/YYYYMMDD-HHMMSS --yes

This restores the local Docker IOMAD stack from a backup created by scripts/backup.sh.
It stops IOMAD, recreates the local PostgreSQL database, replaces iomaddata/,
checks out the recorded IOMAD commit when available, syncs iomad-overrides/, and purges caches.
USAGE
    exit 1
fi

if [ ! -f .env ]; then
    echo ".env is missing. Run ./scripts/bootstrap-iomad.sh first."
    exit 1
fi

if [ ! -d "${BACKUP_DIR}" ]; then
    echo "Backup directory not found: ${BACKUP_DIR}"
    exit 1
fi

BACKUP_DIR="$(cd "${BACKUP_DIR}" && pwd)"
./scripts/verify-backup.sh "${BACKUP_DIR}"

set -a
# shellcheck disable=SC1091
source .env
set +a

if [ -f versions.env ]; then
    set -a
    # shellcheck disable=SC1091
    source versions.env
    set +a
fi

POSTGRES_DB="${POSTGRES_DB:-iomad}"
POSTGRES_USER="${POSTGRES_USER:-iomad}"
IOMAD_REPO="${IOMAD_REPO:-https://github.com/iomad/iomad.git}"

recorded_commit=""
if [ -f "${BACKUP_DIR}/iomad-version.txt" ]; then
    recorded_commit="$(sed -n 's/^IOMAD_COMMIT=//p' "${BACKUP_DIR}/iomad-version.txt" | head -1 | tr -d '[:space:]')"
fi

docker compose stop cron || true
if docker compose ps --status running --quiet iomad | grep -q .; then
    docker compose exec -T iomad php admin/cli/maintenance.php --enable || true
fi
docker compose stop iomad || true

cp "${BACKUP_DIR}/versions.env" versions.env

set -a
# shellcheck disable=SC1091
source versions.env
set +a

if [ -n "${recorded_commit}" ]; then
    if [ ! -d iomad/.git ]; then
        ./scripts/bootstrap-iomad.sh
    fi

    git -C iomad remote set-url origin "${IOMAD_REPO}" || true
    git -C iomad fetch --depth 1 origin "${recorded_commit}" \
        || git -C iomad fetch --depth 1000 origin "${IOMAD_REF:-IOMAD_501_STABLE}"
    git -C iomad checkout --detach "${recorded_commit}"

    configured_commit="$(sed -n 's/^IOMAD_COMMIT=//p' versions.env | head -1 | tr -d '[:space:]')"
    if [ "${configured_commit}" != "${recorded_commit}" ]; then
        echo "Backup metadata mismatch: versions.env and iomad-version.txt record different commits." >&2
        exit 1
    fi
fi

./scripts/sync-iomad-overrides.sh

docker compose up -d db redis mailpit

for attempt in {1..30}; do
    if docker compose exec -T db pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" >/dev/null 2>&1; then
        break
    fi

    if [ "${attempt}" -eq 30 ]; then
        echo "PostgreSQL did not become ready for restore."
        exit 1
    fi

    sleep 1
done

docker compose exec -T db psql -v ON_ERROR_STOP=1 -U "${POSTGRES_USER}" -d postgres -c \
    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${POSTGRES_DB}' AND pid <> pg_backend_pid();"
docker compose exec -T db dropdb --if-exists -U "${POSTGRES_USER}" --maintenance-db=postgres "${POSTGRES_DB}"
docker compose exec -T db createdb -U "${POSTGRES_USER}" --maintenance-db=postgres "${POSTGRES_DB}"
docker compose exec -T db psql --quiet -v ON_ERROR_STOP=1 -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" \
    < "${BACKUP_DIR}/postgres.sql"

if [ -d iomaddata ]; then
    stale_dataroot="$(mktemp -d "${ROOT_DIR}/.restore-old-iomaddata.XXXXXX")"
    mv iomaddata "${stale_dataroot}/iomaddata"
fi

if [ -f "${BACKUP_DIR}/iomaddata.tar.gz" ]; then
    tar -xzf "${BACKUP_DIR}/iomaddata.tar.gz"
else
    echo "Backup is missing iomaddata.tar.gz after verification." >&2
    exit 1
fi

docker compose build iomad cron
docker compose up -d --wait --force-recreate iomad
docker compose exec -T iomad composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
docker compose exec -T iomad php admin/cli/upgrade.php --non-interactive
docker compose exec -T iomad php admin/cli/check_database_schema.php
docker compose exec -T iomad php admin/cli/purge_caches.php
docker compose exec -T iomad php admin/cli/maintenance.php --disable
./scripts/configure-mailpit.sh
./scripts/tenant-smoke-test.sh
docker compose up -d --force-recreate cron

if [ -n "${stale_dataroot:-}" ] && [ -d "${stale_dataroot}" ]; then
    find "${stale_dataroot}" -depth -delete
fi

trap - ERR
echo "Restore completed from ${BACKUP_DIR}"
