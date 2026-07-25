#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMP_ROOT="$(mktemp -d)"
BACKUP_ROOT="${TEMP_ROOT}/backups"
ARCHIVE_ROOT="${TEMP_ROOT}/archive"
COMMIT="55b3128b8058d27f6cc4320850ca709ed5a792a9"

cleanup() {
    rm -rf "${TEMP_ROOT}"
}
trap cleanup EXIT

mkdir -p "${BACKUP_ROOT}" "${ARCHIVE_ROOT}/iomaddata"
printf 'retention fixture\n' > "${ARCHIVE_ROOT}/iomaddata/sentinel.txt"

checksum_files() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$@"
    else
        shasum -a 256 "$@"
    fi
}

create_recovery_set() {
    local name="$1"
    local path="${BACKUP_ROOT}/${name}"
    mkdir -p "${path}"

    printf '{}\n' > "${path}/active-containers.json"
    printf '{}\n' > "${path}/active-images.json"
    printf 'IOMAD_COMMIT=%s\n' "${COMMIT}" > "${path}/iomad-version.txt"
    printf '%s\n' '-- PostgreSQL database dump' > "${path}/postgres.sql"
    printf 'IOMAD_COMMIT=%s\n' "${COMMIT}" > "${path}/versions.env"
    tar -czf "${path}/iomaddata.tar.gz" -C "${ARCHIVE_ROOT}" iomaddata
    {
        printf 'FORMAT_VERSION=1\n'
        printf 'STATUS=complete\n'
        printf 'IOMAD_COMMIT=%s\n' "${COMMIT}"
    } > "${path}/manifest.env"

    (
        cd "${path}"
        checksum_files \
            active-containers.json \
            active-images.json \
            iomad-version.txt \
            iomaddata.tar.gz \
            manifest.env \
            postgres.sql \
            versions.env > checksums.sha256
    )
}

create_recovery_set "20260723-010101"
create_recovery_set "20260724-010101"
create_recovery_set "20260725-010101"
cat > "${BACKUP_ROOT}/latest.env" <<EOF
STATUS=complete
RECOVERY_SET=20260725-010101
IOMAD_COMMIT=${COMMIT}
EOF

dry_run="$(
    BACKUP_ROOT="${BACKUP_ROOT}" \
        "${ROOT_DIR}/scripts/prune-backups.sh" --keep=1
)"
grep -q 'Would remove superseded recovery set: 20260724-010101' <<< "${dry_run}"
grep -q 'Would remove superseded recovery set: 20260723-010101' <<< "${dry_run}"
test -d "${BACKUP_ROOT}/20260725-010101"
test -d "${BACKUP_ROOT}/20260724-010101"
test -d "${BACKUP_ROOT}/20260723-010101"

BACKUP_ROOT="${BACKUP_ROOT}" \
    "${ROOT_DIR}/scripts/prune-backups.sh" --keep=1 --apply >/dev/null

test -d "${BACKUP_ROOT}/20260725-010101"
test ! -e "${BACKUP_ROOT}/20260724-010101"
test ! -e "${BACKUP_ROOT}/20260723-010101"
grep -qx 'RECOVERY_SET=20260725-010101' "${BACKUP_ROOT}/latest.env"

echo "Backup retention validation passed."
