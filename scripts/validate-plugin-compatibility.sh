#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

IOMAD_BRANCH=501
failure_count=0

while IFS= read -r version_file; do
    supported_range="$(
        # The expression intentionally matches the literal PHP variable.
        # shellcheck disable=SC2016
        sed -nE 's/.*\$plugin->supported[[:space:]]*=[[:space:]]*\[([0-9]+),[[:space:]]*([0-9]+)\].*/\1 \2/p' \
            "${version_file}" | head -1
    )"

    if [ -z "${supported_range}" ]; then
        echo "Missing explicit IOMAD 5.1 support declaration: ${version_file}" >&2
        failure_count=$((failure_count + 1))
        continue
    fi

    read -r supported_min supported_max <<< "${supported_range}"
    if [ "${IOMAD_BRANCH}" -lt "${supported_min}" ] || [ "${IOMAD_BRANCH}" -gt "${supported_max}" ]; then
        echo "IOMAD 5.1 is outside supported range [${supported_min}, ${supported_max}]: ${version_file}" >&2
        failure_count=$((failure_count + 1))
    fi
done < <(find iomad-overrides/public -type f -name version.php -print | sort)

if [ "${failure_count}" -ne 0 ]; then
    exit 1
fi

echo "All tracked override plugins explicitly support IOMAD 5.1."
