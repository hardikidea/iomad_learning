#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

BACKUP_ROOT="${BACKUP_ROOT:-${ROOT_DIR}/backups}"
KEEP=3
APPLY=false

for argument in "$@"; do
    case "${argument}" in
        --apply)
            APPLY=true
            ;;
        --keep=*)
            KEEP="${argument#--keep=}"
            ;;
        *)
            echo "Usage: ./scripts/prune-backups.sh [--keep=N] [--apply]" >&2
            exit 1
            ;;
    esac
done

if [[ ! "${KEEP}" =~ ^[1-9][0-9]*$ ]] || [ "${KEEP}" -gt 100 ]; then
    echo "Backup retention count must be between 1 and 100." >&2
    exit 1
fi

mkdir -p "${BACKUP_ROOT}"
latest=""
if [ -f "${BACKUP_ROOT}/latest.env" ]; then
    latest="$(sed -n 's/^RECOVERY_SET=//p' "${BACKUP_ROOT}/latest.env" | head -1)"
fi

sets=()
while IFS= read -r path; do
    sets+=("${path}")
done < <(find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d \
    -name '20????????-??????*' -print | sort -r)

if [ "${#sets[@]}" -le "${KEEP}" ]; then
    echo "No recovery sets are outside the keep window."
    exit 0
fi

replacement="${sets[0]}"
"${ROOT_DIR}/scripts/verify-backup.sh" "${replacement}" >/dev/null

deleted=0
for ((index = KEEP; index < ${#sets[@]}; index++)); do
    path="${sets[index]}"
    name="$(basename "${path}")"
    if [ "${name}" = "${latest}" ]; then
        echo "Refusing to prune the recovery set referenced by latest.env: ${name}" >&2
        exit 1
    fi
    if [ ! -f "${path}/manifest.env" ] || ! grep -qx 'STATUS=complete' "${path}/manifest.env"; then
        echo "Refusing to prune an incomplete or legacy directory automatically: ${name}" >&2
        exit 1
    fi
    if [ "${APPLY}" = "true" ]; then
        rm -rf -- "${path:?}"
        echo "Removed superseded recovery set: ${name}"
    else
        echo "Would remove superseded recovery set: ${name}"
    fi
    deleted=$((deleted + 1))
done

if [ "${APPLY}" = "false" ]; then
    echo "Dry run only. Re-run with --apply after review."
else
    echo "Removed ${deleted} superseded recovery set(s); retained ${KEEP}."
fi
