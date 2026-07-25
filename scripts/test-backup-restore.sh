#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ "${1:-}" != "--yes" ]; then
    cat <<'USAGE'
Usage:
  ./scripts/test-backup-restore.sh --yes

Creates a temporary recovery set, mutates the local no-reply address and dataroot,
restores the recovery set, verifies both mutations were rolled back, and removes
the temporary recovery set.
USAGE
    exit 1
fi

TEMP_BACKUP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/iomad-backup-restore-test.XXXXXX")"
BACKUP_DIR_FILE="$(mktemp)"
ORIGINAL_NOREPLY_ADDRESS="$(
    docker compose exec -T iomad php admin/cli/cfg.php --name=noreplyaddress | tr -d '\r'
)"
TEST_NOREPLY_ADDRESS="restore-test-$RANDOM-$$@example.invalid"
SENTINEL="${ROOT_DIR}/iomaddata/restore-pipeline-sentinel-$$"

cleanup() {
    exit_code=$?
    rm -f "${BACKUP_DIR_FILE}"
    if [ -d "${TEMP_BACKUP_ROOT}" ]; then
        find "${TEMP_BACKUP_ROOT}" -depth -delete
    fi
    if [ -f "${SENTINEL}" ]; then
        rm -f "${SENTINEL}"
    fi
    exit "${exit_code}"
}
trap cleanup EXIT

BACKUP_ROOT="${TEMP_BACKUP_ROOT}" \
BACKUP_REASON=backup-restore-acceptance \
BACKUP_DIR_FILE="${BACKUP_DIR_FILE}" \
    ./scripts/backup.sh

RECOVERY_SET="$(cat "${BACKUP_DIR_FILE}")"
./scripts/verify-backup.sh "${RECOVERY_SET}"

docker compose exec -T iomad php admin/cli/cfg.php --name=noreplyaddress --set="${TEST_NOREPLY_ADDRESS}"
printf 'This file must disappear after restore.\n' > "${SENTINEL}"

./scripts/restore-backup.sh "${RECOVERY_SET}" --yes

RESTORED_NOREPLY_ADDRESS="$(
    docker compose exec -T iomad php admin/cli/cfg.php --name=noreplyaddress | tr -d '\r'
)"

if [ "${RESTORED_NOREPLY_ADDRESS}" != "${ORIGINAL_NOREPLY_ADDRESS}" ]; then
    echo "Backup/restore test failed: database setting was not restored." >&2
    exit 1
fi

if [ -e "${SENTINEL}" ]; then
    echo "Backup/restore test failed: dataroot mutation survived restore." >&2
    exit 1
fi

./scripts/tenant-smoke-test.sh
echo "Backup/restore acceptance test passed."
