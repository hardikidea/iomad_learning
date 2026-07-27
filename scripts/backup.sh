#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

POSTGRES_DB="${POSTGRES_DB:-iomad}"
POSTGRES_USER="${POSTGRES_USER:-iomad}"
IOMAD_DB_PREFIX="${IOMAD_DB_PREFIX:-mdl_}"
BACKUP_ROOT="${BACKUP_ROOT:-${ROOT_DIR}/backups}"
BACKUP_REASON="${BACKUP_REASON:-manual}"

if [ -f versions.env ]; then
    set -a
    # shellcheck disable=SC1091
    source versions.env
    set +a
fi

if [ ! -d iomad/.git ] || [ ! -f versions.env ]; then
    echo "Refusing to create backup: iomad/.git and versions.env are required." >&2
    exit 1
fi

IOMAD_COMMIT_ACTUAL="$(git -C iomad rev-parse HEAD)"
IOMAD_WAS_RUNNING=false
CRON_WAS_RUNNING=false
MAINTENANCE_WAS_ENABLED=false
MAINTENANCE_ENABLED_BY_BACKUP=false
BACKUP_DIR=""
ACTIVE_IMAGE_ID="unknown"

if docker compose ps --status running --quiet iomad | grep -q .; then
    IOMAD_WAS_RUNNING=true
    active_container_id="$(docker compose ps --status running --quiet iomad | head -1)"
    ACTIVE_IMAGE_ID="$(docker inspect "${active_container_id}" --format '{{ .Image }}')"
    active_image_commit="$(
        docker inspect "${active_container_id}" \
            --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}'
    )"
    if [ "${active_image_commit}" != "${IOMAD_COMMIT_ACTUAL}" ]; then
        echo "Refusing to create backup: running image commit ${active_image_commit} does not match checkout ${IOMAD_COMMIT_ACTUAL}." >&2
        exit 1
    fi
fi

if docker compose ps --status running --quiet cron | grep -q .; then
    CRON_WAS_RUNNING=true
fi

if [ -f iomaddata/climaintenance.html ]; then
    MAINTENANCE_WAS_ENABLED=true
fi

restore_runtime_state() {
    exit_code=$?

    if [ "${MAINTENANCE_ENABLED_BY_BACKUP}" = "true" ] && [ "${IOMAD_WAS_RUNNING}" = "true" ]; then
        docker compose exec -T iomad php admin/cli/maintenance.php --disable >/dev/null 2>&1 || true
    fi

    if [ "${CRON_WAS_RUNNING}" = "true" ]; then
        docker compose start cron >/dev/null 2>&1 || true
    fi

    if [ "${exit_code}" -ne 0 ] && [ -n "${BACKUP_DIR}" ] && [ -d "${BACKUP_DIR}" ]; then
        printf 'STATUS=incomplete\n' > "${BACKUP_DIR}/INCOMPLETE"
        echo "Incomplete backup retained for diagnosis: ${BACKUP_DIR}" >&2
    fi

    exit "${exit_code}"
}

trap restore_runtime_state EXIT

if [ "${CRON_WAS_RUNNING}" = "true" ]; then
    docker compose stop cron
fi

if [ "${IOMAD_WAS_RUNNING}" = "true" ] && [ "${MAINTENANCE_WAS_ENABLED}" = "false" ]; then
    docker compose exec -T iomad php admin/cli/maintenance.php --enable
    MAINTENANCE_ENABLED_BY_BACKUP=true
fi

docker compose up -d db

for attempt in {1..30}; do
    if docker compose exec -T db pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" >/dev/null; then
        break
    fi

    if [ "${attempt}" -eq 30 ]; then
        echo "PostgreSQL did not become ready for backup."
        exit 1
    fi

    sleep 1
done

IOMAD_TABLE_COUNT="$(
    docker compose exec -T db psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -tAc \
        "select count(*) from information_schema.tables where table_schema = 'public' and table_name like '${IOMAD_DB_PREFIX}%';" \
        | tr -d '[:space:]'
)"

if [ "${IOMAD_TABLE_COUNT:-0}" = "0" ] && [ "${ALLOW_EMPTY_IOMAD_BACKUP:-false}" != "true" ]; then
    echo "Refusing to create backup: database ${POSTGRES_DB} has no IOMAD tables with prefix ${IOMAD_DB_PREFIX}." >&2
    echo "Check that the correct Docker Compose project is running and port ${POSTGRES_PORT:-15440} is not owned by an old stack." >&2
    echo "Set ALLOW_EMPTY_IOMAD_BACKUP=true only if you intentionally need an empty database backup." >&2
    exit 1
fi

STAMP="$(date -u +%Y%m%d-%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/${STAMP}"
if [ -e "${BACKUP_DIR}" ]; then
    BACKUP_DIR="${BACKUP_ROOT}/${STAMP}-$$"
fi
mkdir -p "${BACKUP_DIR}"

docker compose exec -T db pg_dump -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" > "${BACKUP_DIR}/postgres.sql"

if [ -d iomaddata ]; then
    tar -czf "${BACKUP_DIR}/iomaddata.tar.gz" iomaddata
else
    echo "Refusing to create backup: iomaddata/ is missing." >&2
    exit 1
fi

{
    printf 'IOMAD_COMMIT=%s\n' "${IOMAD_COMMIT_ACTUAL}"
    printf 'IOMAD_DESCRIBE=%s\n' "$(git -C iomad describe --tags --always --dirty || true)"
    git -C iomad log --oneline -1 || true
} > "${BACKUP_DIR}/iomad-version.txt"

awk -v target="${IOMAD_COMMIT_ACTUAL}" '
    BEGIN { done = 0 }
    /^IOMAD_COMMIT=/ { print "IOMAD_COMMIT=" target; done = 1; next }
    { print }
    END { if (done == 0) print "IOMAD_COMMIT=" target }
' versions.env > "${BACKUP_DIR}/versions.env"

if ! docker compose images --format json > "${BACKUP_DIR}/active-images.json" 2>/dev/null \
    || [ ! -s "${BACKUP_DIR}/active-images.json" ]; then
    active_container_ids="$(docker compose ps --all --quiet)"
    if [ -z "${active_container_ids}" ]; then
        echo "Refusing to create backup: no Compose containers are available for image metadata." >&2
        exit 1
    fi
    # Container IDs are generated by Docker and contain no shell metacharacters.
    # shellcheck disable=SC2086
    docker inspect ${active_container_ids} > "${BACKUP_DIR}/active-images.json"
fi
docker compose ps --format json > "${BACKUP_DIR}/active-containers.json" 2>/dev/null || true

CREATED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
{
    printf 'FORMAT_VERSION=1\n'
    printf 'STATUS=complete\n'
    printf 'CREATED_AT=%s\n' "${CREATED_AT}"
    printf 'REASON=%s\n' "${BACKUP_REASON}"
    printf 'IOMAD_COMMIT=%s\n' "${IOMAD_COMMIT_ACTUAL}"
    printf 'IOMAD_REF=%s\n' "${IOMAD_REF:-unknown}"
    printf 'IOMAD_SERIES=%s\n' "${IOMAD_SERIES:-unknown}"
    printf 'ACTIVE_IMAGE_ID=%s\n' "${ACTIVE_IMAGE_ID}"
    printf 'POSTGRES_IMAGE=%s\n' "${POSTGRES_IMAGE:-unknown}"
    printf 'POSTGRES_DB=%s\n' "${POSTGRES_DB}"
    printf 'IOMAD_DB_PREFIX=%s\n' "${IOMAD_DB_PREFIX}"
    printf 'DATABASE_TABLE_COUNT=%s\n' "${IOMAD_TABLE_COUNT}"
    printf 'WEB_WAS_RUNNING=%s\n' "${IOMAD_WAS_RUNNING}"
    printf 'CRON_WAS_RUNNING=%s\n' "${CRON_WAS_RUNNING}"
    printf 'MAINTENANCE_WAS_ENABLED=%s\n' "${MAINTENANCE_WAS_ENABLED}"
} > "${BACKUP_DIR}/manifest.env"

(
    cd "${BACKUP_DIR}"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum \
            active-containers.json \
            active-images.json \
            iomad-version.txt \
            iomaddata.tar.gz \
            manifest.env \
            postgres.sql \
            versions.env > checksums.sha256
    else
        shasum -a 256 \
            active-containers.json \
            active-images.json \
            iomad-version.txt \
            iomaddata.tar.gz \
            manifest.env \
            postgres.sql \
            versions.env > checksums.sha256
    fi
)

./scripts/verify-backup.sh "${BACKUP_DIR}"

LATEST_STATUS="${BACKUP_ROOT}/latest.env"
LATEST_STATUS_TEMP="${LATEST_STATUS}.tmp.$$"
if command -v sha256sum >/dev/null 2>&1; then
    MANIFEST_SHA256="$(sha256sum "${BACKUP_DIR}/manifest.env" | awk '{print $1}')"
else
    MANIFEST_SHA256="$(shasum -a 256 "${BACKUP_DIR}/manifest.env" | awk '{print $1}')"
fi
{
    printf 'STATUS=complete\n'
    printf 'CREATED_EPOCH=%s\n' "$(date -u +%s)"
    printf 'RECOVERY_SET=%s\n' "$(basename "${BACKUP_DIR}")"
    printf 'IOMAD_COMMIT=%s\n' "${IOMAD_COMMIT_ACTUAL}"
    printf 'MANIFEST_SHA256=%s\n' "${MANIFEST_SHA256}"
} > "${LATEST_STATUS_TEMP}"
mv "${LATEST_STATUS_TEMP}" "${LATEST_STATUS}"

if [ -n "${BACKUP_DIR_FILE:-}" ]; then
    printf '%s\n' "${BACKUP_DIR}" > "${BACKUP_DIR_FILE}"
fi

echo "Backup written to ${BACKUP_DIR}"
