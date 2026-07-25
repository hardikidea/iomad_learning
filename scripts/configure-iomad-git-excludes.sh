#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOMAD_DIR="${ROOT_DIR}/iomad"
OVERRIDES_DIR="${ROOT_DIR}/iomad-overrides"
BEGIN_MARKER="# BEGIN project synced IOMAD overrides"
END_MARKER="# END project synced IOMAD overrides"

if [ ! -d "${IOMAD_DIR}" ] || ! git -C "${IOMAD_DIR}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "IOMAD Git checkout is missing at ${IOMAD_DIR}. Skipping nested Git excludes."
    exit 0
fi

if [ ! -d "${OVERRIDES_DIR}" ]; then
    echo "No iomad-overrides directory found. Skipping nested Git excludes."
    exit 0
fi

exclude_path="$(git -C "${IOMAD_DIR}" rev-parse --git-path info/exclude)"
case "${exclude_path}" in
    /*) exclude_file="${exclude_path}" ;;
    *) exclude_file="${IOMAD_DIR}/${exclude_path}" ;;
esac
mkdir -p "$(dirname "${exclude_file}")"

entries="$(mktemp)"
clean_exclude="$(mktemp)"
trap 'rm -f "${entries}" "${clean_exclude}"' EXIT

{
    printf '/.iomad-source.env\n'
    find "${OVERRIDES_DIR}" -type f | sort | while IFS= read -r file; do
        relpath="${file#"${OVERRIDES_DIR}"/}"
        printf '/%s\n' "${relpath}"
    done
} > "${entries}"

if [ -f "${exclude_file}" ]; then
    awk -v begin="${BEGIN_MARKER}" -v end="${END_MARKER}" '
        $0 == begin { skip = 1; next }
        $0 == end { skip = 0; next }
        skip != 1 { print }
    ' "${exclude_file}" > "${clean_exclude}"
else
    : > "${clean_exclude}"
fi

{
    cat "${clean_exclude}"
    printf '%s\n' "${BEGIN_MARKER}"
    printf '# Managed by scripts/configure-iomad-git-excludes.sh. Do not edit this block by hand.\n'
    cat "${entries}"
    printf '%s\n' "${END_MARKER}"
} > "${exclude_file}"

echo "Configured IOMAD nested Git excludes in ${exclude_file}"
