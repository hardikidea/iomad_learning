#!/usr/bin/env bash
set -euo pipefail

PACK_DIR="${1:-}"

if [ -z "${PACK_DIR}" ] || [ ! -d "${PACK_DIR}" ]; then
    echo "Usage: ./scripts/validate-pack-files.sh institution-packs/school/sample" >&2
    exit 1
fi

manifest="${PACK_DIR}/manifest.yml"
if [ ! -f "${manifest}" ]; then
    echo "Missing manifest.yml in ${PACK_DIR}" >&2
    exit 1
fi

required_files="$(
    awk '
        $1 == "files:" { in_files = 1; next }
        in_files && /^[^ ]/ { in_files = 0 }
        in_files && /^[ ]+[a-z_]+:/ {
            gsub(":", "", $1)
            print $2
        }
    ' "${manifest}"
)"

while IFS= read -r csv; do
    [ -z "${csv}" ] && continue
    if [ ! -f "${PACK_DIR}/${csv}" ]; then
        echo "Manifest references missing file: ${PACK_DIR}/${csv}" >&2
        exit 1
    fi
    if [ "$(wc -l < "${PACK_DIR}/${csv}")" -lt 2 ]; then
        echo "CSV has no data rows: ${PACK_DIR}/${csv}" >&2
        exit 1
    fi
done <<< "${required_files}"

tmp_manifest="$(mktemp)"
trap 'rm -f "${tmp_manifest}"' EXIT

{
    printf 'pack=%s\n' "${PACK_DIR}"
    find "${PACK_DIR}" -maxdepth 1 -type f -name '*.csv' -print | sort | while IFS= read -r file; do
        printf '%s  %s\n' "$(shasum -a 256 "${file}" | awk '{print $1}')" "${file#"${PACK_DIR}"/}"
    done
} > "${tmp_manifest}"

echo "Pack files validated for ${PACK_DIR}"
