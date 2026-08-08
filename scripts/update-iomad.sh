#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ ! -f .env ]; then
    echo ".env is missing. Run ./scripts/bootstrap-iomad.sh first." >&2
    exit 1
fi

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

RESTORE_ON_FAIL=0
if [ "${1:-}" = "--restore-on-fail" ]; then
    RESTORE_ON_FAIL=1
    shift
fi

TARGET_INPUT="${1:-${IOMAD_COMMIT:-}}"
IOMAD_REPO="${IOMAD_REPO:-https://github.com/iomad/iomad.git}"
IOMAD_REF="${IOMAD_REF:-IOMAD_501_STABLE}"
BACKUP_DIR=""
CURRENT_COMMIT=""
ORIGINAL_VERSIONS=""
IOMAD_WAS_RUNNING=0
CRON_WAS_RUNNING=0
MAINTENANCE_WAS_ENABLED=0
NEW_IMAGE_ACTIVATED=0

on_error() {
    exit_code=$?
    trap - ERR
    echo "IOMAD update failed." >&2

    if [ "${NEW_IMAGE_ACTIVATED}" -eq 0 ] && [ -n "${CURRENT_COMMIT}" ]; then
        git -C iomad checkout --detach "${CURRENT_COMMIT}" >/dev/null 2>&1 || true
        if [ -n "${ORIGINAL_VERSIONS}" ] && [ -f "${ORIGINAL_VERSIONS}" ]; then
            cp "${ORIGINAL_VERSIONS}" versions.env
        fi
        ./scripts/sync-iomad-overrides.sh >/dev/null 2>&1 || true

        if [ "${IOMAD_WAS_RUNNING}" -eq 1 ] && [ "${MAINTENANCE_WAS_ENABLED}" -eq 0 ]; then
            docker compose exec -T iomad php admin/cli/maintenance.php --disable >/dev/null 2>&1 || true
        fi
        if [ "${CRON_WAS_RUNNING}" -eq 1 ]; then
            docker compose start cron >/dev/null 2>&1 || true
        fi
        echo "The pre-update checkout, versions file, and runtime state were restored." >&2
    fi

    if [ -n "${BACKUP_DIR}" ]; then
        cat >&2 <<EOF
Backup is available at: ${BACKUP_DIR}
Rollback after a schema migration must restore this matching database, iomaddata, and previous immutable image.
Restore manually with:
  ./scripts/restore-backup.sh "${BACKUP_DIR}" --yes
EOF
        if [ "${RESTORE_ON_FAIL}" -eq 1 ] && [ "${NEW_IMAGE_ACTIVATED}" -eq 1 ]; then
            echo "Attempting local automatic restore from ${BACKUP_DIR}" >&2
            ./scripts/restore-backup.sh "${BACKUP_DIR}" --yes || true
        fi
    else
        echo "No restore backup was created by this run. The failure happened before backup completed." >&2
    fi
    if [ -n "${ORIGINAL_VERSIONS}" ]; then
        rm -f "${ORIGINAL_VERSIONS}"
    fi
    exit "${exit_code}"
}

trap on_error ERR

if [ -z "${TARGET_INPUT}" ]; then
    echo "Usage: ./scripts/update-iomad.sh [--restore-on-fail] <commit-sha|IOMAD_ref|tag>" >&2
    exit 1
fi

if [ ! -d iomad/.git ]; then
    echo "iomad/ Git checkout is missing. Run ./scripts/bootstrap-iomad.sh first." >&2
    exit 1
fi

if ! git -C iomad diff --quiet || ! git -C iomad diff --cached --quiet; then
    echo "Refusing to update with tracked changes in ignored upstream checkout iomad/." >&2
    exit 1
fi

resolve_target() {
    local input="$1"
    if [[ "${input}" =~ ^[0-9a-fA-F]{40}$ ]]; then
        printf '%s\n' "${input}"
        return 0
    fi

    git ls-remote "${IOMAD_REPO}" "refs/heads/${input}" "refs/tags/${input}" \
        | awk 'NR == 1 { print $1 }'
}

TARGET_COMMIT="$(resolve_target "${TARGET_INPUT}")"
if [ -z "${TARGET_COMMIT}" ]; then
    echo "Unable to resolve target ${TARGET_INPUT} from ${IOMAD_REPO}" >&2
    exit 1
fi

if [[ "${TARGET_INPUT}" =~ ^[0-9a-fA-F]{40}$ ]]; then
    NEXT_IOMAD_REF="${IOMAD_REF:-IOMAD_501_STABLE}"
else
    NEXT_IOMAD_REF="${TARGET_INPUT}"
fi

if [[ "${NEXT_IOMAD_REF}" =~ ^[0-9a-fA-F]{40}$ ]]; then
    NEXT_IOMAD_REF="IOMAD_501_STABLE"
fi

./scripts/validate-iomad-compatibility.sh "${TARGET_COMMIT}"

git -C iomad remote set-url origin "${IOMAD_REPO}" || true
git -C iomad fetch --depth 1 origin "${TARGET_COMMIT}" \
    || git -C iomad fetch --depth 1000 origin "${TARGET_INPUT}"
git -C iomad cat-file -e "${TARGET_COMMIT}^{commit}"

CURRENT_COMMIT="$(git -C iomad rev-parse HEAD)"
if [ "${TARGET_COMMIT}" != "${CURRENT_COMMIT}" ] \
    && git -C iomad merge-base --is-ancestor "${TARGET_COMMIT}" "${CURRENT_COMMIT}" >/dev/null 2>&1; then
    cat >&2 <<EOF
Refusing to move from ${CURRENT_COMMIT} back to older commit ${TARGET_COMMIT}.
IOMAD database downgrades are not supported. Restore a matching database, dataroot, and immutable image instead.
EOF
    exit 1
fi

# A branch or tag may resolve to the commit that is already installed. Validate
# project assumptions, but do not create a backup or disturb maintenance/cron.
if [ "${TARGET_COMMIT}" = "${CURRENT_COMMIT}" ]; then
    ./scripts/validate-iomad-operational-baseline.sh
    echo "IOMAD is already pinned at ${TARGET_COMMIT}; no update was required."
    exit 0
fi

ORIGINAL_VERSIONS="$(mktemp)"
cp versions.env "${ORIGINAL_VERSIONS}"

if docker compose ps --status running --quiet iomad | grep -q .; then
    IOMAD_WAS_RUNNING=1
fi
if docker compose ps --status running --quiet cron | grep -q .; then
    CRON_WAS_RUNNING=1
fi
if [ -f iomaddata/climaintenance.html ]; then
    MAINTENANCE_WAS_ENABLED=1
fi

backup_marker="$(mktemp)"
BACKUP_REASON="pre-upgrade-${TARGET_COMMIT}" \
BACKUP_DIR_FILE="${backup_marker}" \
    ./scripts/backup.sh
BACKUP_DIR="$(cat "${backup_marker}")"
rm -f "${backup_marker}"
echo "Pre-upgrade backup: ${BACKUP_DIR}"

if docker compose ps --status running --quiet cron | grep -q .; then
    docker compose stop cron
fi

if docker compose ps --status running --quiet iomad | grep -q .; then
    docker compose exec -T iomad php admin/cli/maintenance.php --enable || true
fi

git -C iomad checkout --detach "${TARGET_COMMIT}"
./scripts/validate-iomad-operational-baseline.sh
./scripts/sync-iomad-overrides.sh

tmp_versions="$(mktemp)"
awk -v target="${TARGET_COMMIT}" -v ref="${NEXT_IOMAD_REF}" '
    BEGIN { commit_done = 0; ref_done = 0 }
    /^IOMAD_COMMIT=/ { print "IOMAD_COMMIT=" target; commit_done = 1; next }
    /^IOMAD_REF=/ { print "IOMAD_REF=" ref; ref_done = 1; next }
    { print }
    END {
        if (ref_done == 0) print "IOMAD_REF=" ref
        if (commit_done == 0) print "IOMAD_COMMIT=" target
    }
' versions.env > "${tmp_versions}"
mv "${tmp_versions}" versions.env

docker compose build iomad cron
# From this point a failure may leave target code active against the database;
# recovery must use the matching backup instead of merely toggling services.
NEW_IMAGE_ACTIVATED=1
docker compose up -d --wait --force-recreate db redis mailpit iomad
docker compose exec -T iomad composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
docker compose exec -T iomad php admin/cli/upgrade.php --non-interactive
docker compose exec -T iomad php admin/cli/purge_caches.php
docker compose exec -T iomad php admin/cli/maintenance.php --disable || true
./scripts/tenant-smoke-test.sh
docker compose up -d --force-recreate cron

rm -f "${ORIGINAL_VERSIONS}"
ORIGINAL_VERSIONS=""
trap - ERR

git -C iomad log --oneline -1
echo "IOMAD updated to ${TARGET_COMMIT}"
echo "Rollback backup retained at ${BACKUP_DIR}"
