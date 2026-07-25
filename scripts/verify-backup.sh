#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

BACKUP_DIR="${1:-}"

if [ -z "${BACKUP_DIR}" ] || [ ! -d "${BACKUP_DIR}" ]; then
    echo "Usage: ./scripts/verify-backup.sh backups/YYYYMMDD-HHMMSS" >&2
    exit 1
fi

BACKUP_DIR="$(cd "${BACKUP_DIR}" && pwd)"

required_files=(
    active-containers.json
    active-images.json
    checksums.sha256
    iomad-version.txt
    iomaddata.tar.gz
    manifest.env
    postgres.sql
    versions.env
)

for required_file in "${required_files[@]}"; do
    if [ ! -s "${BACKUP_DIR}/${required_file}" ]; then
        echo "Backup verification failed: ${required_file} is missing or empty." >&2
        exit 1
    fi
done

if [ -e "${BACKUP_DIR}/INCOMPLETE" ]; then
    echo "Backup verification failed: recovery set is marked incomplete." >&2
    exit 1
fi

if ! grep -qx 'FORMAT_VERSION=1' "${BACKUP_DIR}/manifest.env"; then
    echo "Backup verification failed: unsupported or missing manifest format." >&2
    exit 1
fi

if ! grep -qx 'STATUS=complete' "${BACKUP_DIR}/manifest.env"; then
    echo "Backup verification failed: manifest status is not complete." >&2
    exit 1
fi

manifest_commit="$(sed -n 's/^IOMAD_COMMIT=//p' "${BACKUP_DIR}/manifest.env" | head -1)"
version_commit="$(sed -n 's/^IOMAD_COMMIT=//p' "${BACKUP_DIR}/iomad-version.txt" | head -1)"
configured_commit="$(sed -n 's/^IOMAD_COMMIT=//p' "${BACKUP_DIR}/versions.env" | head -1)"

if [[ ! "${manifest_commit}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Backup verification failed: manifest has no valid 40-character IOMAD commit." >&2
    exit 1
fi

if [ "${manifest_commit}" != "${version_commit}" ] || [ "${manifest_commit}" != "${configured_commit}" ]; then
    echo "Backup verification failed: IOMAD commit metadata does not match." >&2
    exit 1
fi

(
    cd "${BACKUP_DIR}"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum --check checksums.sha256
    else
        shasum -a 256 --check checksums.sha256
    fi
)

if ! grep -q '^-- PostgreSQL database dump' "${BACKUP_DIR}/postgres.sql"; then
    echo "Backup verification failed: postgres.sql is not a PostgreSQL dump." >&2
    exit 1
fi

archive_listing="$(mktemp)"
trap 'rm -f "${archive_listing}"' EXIT
tar -tzf "${BACKUP_DIR}/iomaddata.tar.gz" > "${archive_listing}"

if ! grep -q '^iomaddata/' "${archive_listing}"; then
    echo "Backup verification failed: dataroot archive does not contain iomaddata/." >&2
    exit 1
fi

if grep -Eq '(^/|(^|/)\.\.(/|$))' "${archive_listing}"; then
    echo "Backup verification failed: dataroot archive contains an unsafe path." >&2
    exit 1
fi

echo "Backup verified: ${BACKUP_DIR}"
